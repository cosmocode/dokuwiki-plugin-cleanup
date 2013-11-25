<?php
/**
 * DokuWiki Plugin maintenance (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_maintenance extends DokuWiki_Plugin {
    /** @var int log file pointer */
    private $log = 0;

    /** @var bool do no actually delete */
    private $dryrun = true;

    /**
     * Runs all the checks
     */
    public function run() {
        global $meta;
        $data = array();

        search(
            $data,
            $meta['cachedir'],
            array($this, 'cb_check_cache'),
            array(
                 'maxage' => $this->getConf('cacheage'),
                 'useatime' => true
            )
        );

        search(
            $data,
            $meta['olddir'],
            array($this, 'cb_check_attic'),
            array(
                 'maxage' => $this->getConf('atticage'),
                 'nonexonly' => $this->getConf('atticnoexonly')
            )
        );

        search(
            $data,
            $meta['mediaolddir'],
            array($this, 'cb_check_mediaattic'),
            array(
                 'maxage' => $this->getConf('mediaatticage'),
                 'nonexonly' => $this->getConf('mediaatticnoexonly')
            )
        );

        search(
            $data,
            $meta['metadir'],
            array($this, 'cb_check_meta'),
            array(
                 'maxage' => $this->getConf('metaage'),
            )
        );

        search(
            $data,
            $meta['lockdir'],
            array($this, 'cb_check_locks'),
            array(
                 'maxage' => $this->getConf('lockage'),
            )
        );
    }

    /**
     * Deletes the given file if $this->dryrun isn't set
     *
     * @param string $file file to delete
     * @param string $type type of file to delete
     */
    public function delete($file, $type) {
        global $meta;

        // delete the file
        if(!$this->dryrun){
            @unlink($file);
        }

        // log to file
        if(!$this->log) $this->log = fopen($meta['cachedir'] . '/maintenance.log', 'a');
        if($this->log) {
            $size = filesize($file);
            $time = time();
            fwrite($this->log, "$time\t$size\t$type\t$file\n");
        }
    }

    /**
     * Callback for checking the cache directories
     */
    public function cb_check_cache(&$data, $base, $file, $type, $lvl, $opts) {
        if($type == 'd') {
            // we only recurse into our known cache key directories
            if($lvl == 1 && !preg_match('/^\/[a-f0-9]$/', $file)) return false;
            return true;
        }
        if($lvl == 1) return false; // ignore all files in top directory

        $time = $opts['useatime'] ? fileatime($base . $file) : filemtime($base . $file);

        if(time() - $time > $opts['maxage']) {
            $this->delete($base.$file, 'cache');
        }
        return true;
    }

    /**
     * Callback for checking the page attic directories
     */
    public function cb_check_attic(&$data, $base, $file, $type, $lvl, $opts) {
        if($type == 'd') {
            return true;
        }

        $time = filemtime($base . $file);
        if(time() - $time > $opts['maxage']) {
            // skip existing?
            if($opts['nonexonly']) {
                $path = preg_replace('/\.\d+\.txt(\.gz)?$/', '', $file);
                $id = pathID($path, true);
                if(page_exists($id)) return false;
            }

            $this->delete($base.$file, 'attic');
        }
        return true;
    }

    /**
     * Callback for checking the media attic directories
     */
    public function cb_check_mediaattic(&$data, $base, $file, $type, $lvl, $opts) {
        if($type == 'd') {
            return true;
        }

        $time = filemtime($base . $file);
        if(time() - $time > $opts['maxage']) {
            // skip existing?
            if($opts['nonexonly']) {
                list($ext) = mimetype($file);
                $ext = preg_quote($ext, '/');
                $path = preg_replace('/\.\d+\.' . $ext . '?$/', ".$ext", $file);
                $id = pathID($path, true);

                if(file_exists(mediaFN($id))) return false;
            }

            $this->delete($base.$file, 'mediattic');
        }
        return true;
    }

    /**
     * Callback for checking the page meta directories
     */
    public function cb_check_meta(&$data, $base, $file, $type, $lvl, $opts) {
        if($type == 'd') {
            return true;
        }

        // only handle known extensions
        if(!preg_match('/\.(meta|changes|indexed)$/', $file, $m)) return false;
        $type = $m[1];

        $time = filemtime($base . $file);
        if(time() - $time > $opts['maxage']) {
            $path = substr($file, 0, -1 * (strlen($type) + 1));
            $id = pathID($path);
            if(page_exists($id)) return false;

            $this->delete($base.$file, 'meta');
        }
        return true;
    }

    /**
     * Callback for checking the media meta directories
     */
    public function cb_check_mediameta(&$data, $base, $file, $type, $lvl, $opts) {
        if($type == 'd') {
            return true;
        }

        // only handle known extensions
        if(!preg_match('/\.(changes)$/', $file, $m)) return false;
        $type = $m[1];

        $time = filemtime($base . $file);
        if(time() - $time > $opts['maxage']) {
            $path = substr($file, 0, -1 * (strlen($type) + 1));
            $id = pathID($path);
            if(file_exists(mediaFN($id))) return false;

            $this->delete($base.$file, 'mediameta');
        }
        return true;
    }

    /**
     * Callback for checking the locks directories
     */
    public function cb_check_locks(&$data, $base, $file, $type, $lvl, $opts) {
        if($type == 'd') {
            return true;
        }

        // only handle known extensions
        if(!preg_match('/\.(lock)$/', $file, $m)) return false;
        $type = $m[1];

        $time = filemtime($base . $file);
        if(time() - $time > $opts['maxage']) {
            $this->delete($base.$file, 'lock');
        }
        return true;
    }

}

// vim:ts=4:sw=4:et:
