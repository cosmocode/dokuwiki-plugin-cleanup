<?php
/**
 * DokuWiki Plugin cleanup (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_cleanup extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('INDEXER_TASKS_RUN', 'AFTER', $this, 'handle_indexer_tasks_run');
   
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_indexer_tasks_run(Doku_Event &$event, $param) {
        if(!$this->getConf('runautomatically')) return;
        global $conf;

        // only run once everyday
        $lastrun = $conf['cachedir'].'/cleanup.run';
        $ranat   = @filemtime($lastrun);
        if($ranat && (time() - $ranat) < 60*60*24 ){
            echo "cleanup: skipped\n";
            return;
        }
        io_saveFile($lastrun,'');

        // our turn!
        $event->preventDefault();
        $event->stopPropagation();

        // and action
        echo "cleanup: started\n";
        /** @var helper_plugin_cleanup $helper */
        $helper = $this->loadHelper('cleanup', false);
        $helper->run(true);
        echo 'cleanup: finished. found '.count($helper->list)." files\n";
    }

}

// vim:ts=4:sw=4:et:
