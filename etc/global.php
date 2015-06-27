<?php
date_default_timezone_set("America/Argentina/Buenos_Aires");
spl_autoload_register('autoload'); // @\helpers\commons.php

$cfg['base_url'] = trim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])), '/');

$cfg['session'] = [
    'lifetime'      => 60 * 60, // in seconds = 1hr
    'ajax_refresh'  => true,   // makes an ajax call to keep session alive
];

/************
 * LANGUAGE *
 ************
 * defaults to [0]
 */
$cfg['languages'] = [
    'es',
    //'en',
];

$cfg['error_log']   = 'errors.log';
//$cfg['cron_error_log'] = '_bin/cron.errors.log';

$cfg['default_module'] = 'user';
$cfg['default_action'] = 'search';

// default paginator rows per page
$cfg['rows_per_pag'] = "20";