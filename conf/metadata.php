<?php

/**
 * Options for the cleanup plugin
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */

$meta['runautomatically']   = array('onoff');

$meta['cacheage']           = array('numeric', '_min' => 0);
$meta['atticage']           = array('numeric', '_min' => 0);
$meta['atticnoexonly']      = array('onoff');
$meta['mediaatticage']      = array('numeric', '_min' => 0);
$meta['mediaatticnoexonly'] = array('onoff');
$meta['metaage']            = array('numeric', '_min' => 0);
$meta['mediametaage']       = array('numeric', '_min' => 0);
$meta['lockage']            = array('numeric', '_min' => 0);
