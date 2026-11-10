<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/**
 * DokuWiki Plugin cleanup (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class action_plugin_cleanup extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {

        $controller->register_hook('INDEXER_TASKS_RUN', 'AFTER', $this, 'handleIndexerTasksRun');
    }

    /**
     * Runs the cleanup as part of the indexer tasks if automatic runs are enabled
     *
     * @param Event $event event object by reference
     * @param mixed $param unused
     * @return void
     */
    public function handleIndexerTasksRun(Event &$event, $param)
    {
        if (!$this->getConf('runautomatically')) return;

        /** @var helper_plugin_cleanup $helper */
        $helper = $this->loadHelper('cleanup', false);
        if (!$helper->runScheduled()) {
            echo "cleanup: skipped\n";
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();
        echo 'cleanup: finished. found ' . count($helper->list) . " files\n";
    }
}
