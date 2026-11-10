<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\Form\Form;

/**
 * DokuWiki Plugin autologoff (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class admin_plugin_cleanup extends AdminPlugin
{
    /** @var  helper_plugin_cleanup */
    private $helper;

    /** @var string the executed run mode, dry or real, empty if nothing was run */
    private $run = '';

    public function __construct()
    {
        $this->helper = $this->loadHelper('cleanup', false);
    }

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 600;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle()
    {
        global $INPUT;

        if ($INPUT->post->str('run') == 'real' && checkSecurityToken()) {
            $this->helper->run(true);
            $this->run = 'real';
        } elseif ($INPUT->str('run') == 'dry') {
            $this->helper->run();
            $this->run = 'dry';
        }
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html()
    {
        global $ID;

        echo $this->locale_xhtml('intro');
        tpl_flush();

        if (!$this->run) {
            $form = new Form(['action' => script()]);
            $form->setHiddenField('id', $ID);
            $form->setHiddenField('do', 'admin');
            $form->setHiddenField('page', 'cleanup');
            $form->setHiddenField('run', 'dry');
            $form->addButton('', $this->getLang('preview'))->attr('type', 'submit');
            echo $form->toHTML();
        } else {
            echo '<ul class="cleanup_files">';
            foreach ($this->helper->list as $file) {
                echo '<li><div class="li">' . hsc($file) . '</div></li>';
            }
            echo '</ul>';
            echo '<p>' . sprintf(
                $this->getLang('sum'),
                count($this->helper->list),
                filesize_h($this->helper->size)
            ) . '</p>';

            if ($this->run == 'dry') {
                $form = new Form(['action' => script()]);
                $form->setHiddenField('id', $ID);
                $form->setHiddenField('do', 'admin');
                $form->setHiddenField('page', 'cleanup');
                $form->setHiddenField('run', 'real');
                $form->addButton('', $this->getLang('execute'))->attr('type', 'submit');
                echo $form->toHTML();
            } else {
                echo '<p>' . $this->getLang('done') . '</p>';
            }
        }
    }
}
