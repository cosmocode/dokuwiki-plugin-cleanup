<?php
error_reporting(E_ALL ^ E_NOTICE);

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
define('DOKU_DISABLE_GZIP_OUTPUT', 1);
require_once(DOKU_INC.'inc/init.php');
session_write_close();

/** @var helper_plugin_cleanup $plugin */
$plugin = plugin_load('helper','cleanup');
$plugin->run(true);
