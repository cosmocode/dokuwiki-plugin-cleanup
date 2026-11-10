<?php

use dokuwiki\Extension\Plugin;

/**
 * DokuWiki Plugin cleanup (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class helper_plugin_cleanup extends Plugin
{
    /** @var int log file pointer */
    private $log = 0;

    /** @var bool do no actually delete */
    private $dryrun = true;

    /** @var array list of files */
    public $list = [];

    /** @var int sum of deleted files */
    public $size = 0;

    /** @var int minimum seconds between two completed scheduled runs */
    protected const INTERVAL = 86400;

    /** @var int seconds a started scheduled run blocks other scheduled runs */
    protected const RUNTIME = 3600;

    /**
     * Runs the cleanup if no completed run happened in the last INTERVAL seconds
     *
     * A started run blocks other scheduled runs for RUNTIME seconds. A run that does not complete
     * does not count as completed, so it is done again once RUNTIME has passed.
     *
     * @return bool true if the cleanup was run
     */
    public function runScheduled()
    {
        global $conf;

        $finished = $conf['cachedir'] . '/cleanup.run';
        $running  = $conf['cachedir'] . '/cleanup.running';

        $ranat = @filemtime($finished);
        if ($ranat && (time() - $ranat) < self::INTERVAL) return false;
        $startedat = @filemtime($running);
        if ($startedat && (time() - $startedat) < self::RUNTIME) return false;

        io_saveFile($running, '');
        $this->run(true);
        io_saveFile($finished, '');
        @unlink($running);
        return true;
    }

    /**
     * Runs all the checks
     */
    public function run($run = false)
    {
        global $conf;
        $data = [];

        $this->dryrun = !$run;

        @set_time_limit(0);

        search(
            $data,
            $conf['cachedir'],
            [$this, 'cbCheckCache'],
            [
                 'maxage' => $this->getConf('cacheage'),
                 'useatime' => $this->supportsatime()
            ]
        );

        search(
            $data,
            $conf['olddir'],
            [$this, 'cbCheckAttic'],
            [
                 'maxage' => $this->getConf('atticage'),
                 'nonexonly' => $this->getConf('atticnoexonly')
            ]
        );

        search(
            $data,
            $conf['mediaolddir'],
            [$this, 'cbCheckMediaattic'],
            [
                 'maxage' => $this->getConf('mediaatticage'),
                 'nonexonly' => $this->getConf('mediaatticnoexonly')
            ]
        );

        search(
            $data,
            $conf['metadir'],
            [$this, 'cbCheckMeta'],
            [
                 'maxage' => $this->getConf('metaage'),
            ]
        );

        search(
            $data,
            $conf['mediametadir'],
            [$this, 'cbCheckMediameta'],
            [
                 'maxage' => $this->getConf('mediametaage'),
            ]
        );

        search(
            $data,
            $conf['lockdir'],
            [$this, 'cbCheckLocks'],
            [
                 'maxage' => $this->getConf('lockage'),
            ]
        );
    }

    /**
     * Deletes the given file if $this->dryrun isn't set
     *
     * @param string $file file to delete
     * @param string $type type of file to delete
     */
    public function delete($file, $type)
    {
        global $conf;

        $size = filesize($file);
        $time = time();

        // delete the file
        if (!$this->dryrun) {
            if (@unlink($file)) {
                // log to file
                if (!$this->log) $this->log = fopen($conf['cachedir'] . '/cleanup.log', 'a');
                if ($this->log) {
                    fwrite($this->log, "$time\t$size\t$type\t$file\n");
                }

                $this->size += $size;
                $this->list[] = $file;
            }
        } else {
            $this->size += $size;
            $this->list[] = $file;
        }
    }

    /**
     * Checks if the filesystem supports atimes
     *
     * @return bool
     */
    protected function supportsatime()
    {
        global $conf;

        $testfile = $conf['cachedir'] . '/atime';
        io_saveFile($testfile, 'x');
        $mtime = filemtime($testfile);
        sleep(1);
        io_readFile($testfile);
        clearstatcache(false, $testfile);
        $atime = @fileatime($testfile);
        @unlink($testfile);

        return ($mtime != $atime);
    }

    /**
     * Callback for checking the cache directories
     */
    public function cbCheckCache(&$data, $base, $file, $type, $lvl, $opts)
    {
        if ($type == 'd') {
            // we only recurse into our known cache key directories
            if ($lvl == 1 && !preg_match('/^\/[a-f0-9]$/', $file)) return false;
            return true;
        }
        if ($lvl == 1) return false; // ignore all files in top directory

        $time = $opts['useatime'] ? fileatime($base . $file) : filemtime($base . $file);

        if (time() - $time > $opts['maxage']) {
            $this->delete($base . $file, 'cache');
        }
        return true;
    }

    /**
     * Callback for checking the page attic directories
     */
    public function cbCheckAttic(&$data, $base, $file, $type, $lvl, $opts)
    {
        if ($type == 'd') {
            return true;
        }

        $time = filemtime($base . $file);
        if (time() - $time > $opts['maxage']) {
            // skip existing?
            if ($opts['nonexonly']) {
                $path = preg_replace('/\.\d+\.txt(\.gz)?$/', '', $file);
                $id = pathID($path, true);
                if (page_exists($id)) return false;
            }

            $this->delete($base . $file, 'attic');
        }
        return true;
    }

    /**
     * Callback for checking the media attic directories
     */
    public function cbCheckMediaattic(&$data, $base, $file, $type, $lvl, $opts)
    {
        if ($type == 'd') {
            return true;
        }

        $time = filemtime($base . $file);
        if (time() - $time > $opts['maxage']) {
            // skip existing?
            if ($opts['nonexonly']) {
                [$ext] = mimetype($file);
                $ext = preg_quote($ext, '/');
                $path = preg_replace('/\.\d+\.' . $ext . '?$/', ".$ext", $file);
                $id = pathID($path, true);

                if (file_exists(mediaFN($id))) return false;
            }

            $this->delete($base . $file, 'mediattic');
        }
        return true;
    }

    /**
     * Callback for checking the page meta directories
     */
    public function cbCheckMeta(&$data, $base, $file, $type, $lvl, $opts)
    {
        if ($type == 'd') {
            return true;
        }

        // only handle known extensions
        if (!preg_match('/\.(meta|changes|indexed)$/', $file, $m)) return false;
        $type = $m[1];

        $time = filemtime($base . $file);
        if (time() - $time > $opts['maxage']) {
            $path = substr($file, 0, -1 * (strlen($type) + 1));
            $id = pathID($path);
            if (page_exists($id)) return false;

            $this->delete($base . $file, 'meta');
        }
        return true;
    }

    /**
     * Callback for checking the media meta directories
     */
    public function cbCheckMediameta(&$data, $base, $file, $type, $lvl, $opts)
    {
        if ($type == 'd') {
            return true;
        }

        // only handle known extensions
        if (!preg_match('/\.(changes)$/', $file, $m)) return false;
        $type = $m[1];

        $time = filemtime($base . $file);
        if (time() - $time > $opts['maxage']) {
            $path = substr($file, 0, -1 * (strlen($type) + 1));
            $id = pathID($path);
            if (file_exists(mediaFN($id))) return false;

            $this->delete($base . $file, 'mediameta');
        }
        return true;
    }

    /**
     * Callback for checking the locks directories
     */
    public function cbCheckLocks(&$data, $base, $file, $type, $lvl, $opts)
    {
        if ($type == 'd') {
            return true;
        }

        // only handle known extensions
        if (!preg_match('/\.(lock)$/', $file, $m)) return false;

        $time = filemtime($base . $file);
        if (time() - $time > $opts['maxage']) {
            $this->delete($base . $file, 'lock');
        }
        return true;
    }
}
