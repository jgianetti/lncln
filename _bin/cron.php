<?php
/************
 * CLI ARGS *
 ************
 *
 * date=datestr     : Emulate date
 *      datestr = Y-m-d || d/m/Y
 *
 * debug=bool       : Debug mode (don't execute DB nor MAILER)
 *      bool = boolean value
 *
 ************/

chdir(__DIR__ . '/../');

require_once('helpers/.loader.php');
require_once('etc/.loader.php');

/**
 * @var $cfg array
 */

ini_set('log_errors', 1);
ini_set('error_log', $cfg['error_log']);
ini_set('html_errors', false);


/************
 * CLI ARGS *
 ************/
if ($argc > 1) {
    for ($i=1;$i<$argc;$i++) {
        list($arg, $value) = explode('=',$argv[$i]);
        switch ($arg) {
            case 'date':
                $time = (strpos($value, '/') ? strtotime(str_replace('/','-',$value)) : strtotime($value));
                break;
            case 'debug':
                $debug = $value;
                break;
        }
    }
}

/****************
 * DEFAULT TIME *
 ****************/
if (empty($time)) $time = time();


/**********************
 * DEFAULT DEBUG MODE *
 **********************/
if (!isset($debug)) {
    if (APP_ENV == APP_ENV_DEV) $debug = true;
    else $debug = false;
}

if ($debug) {
    echo "\nDEBUG MODE\n";
    echo "DATE: ".date('d/m/Y', $time)."\n";
}


/*************
 * CONSTANTS *
 *************/
define('TODAY', $time);
define('YESTERDAY', strtotime('-1 day', $time));
define('DEBUG', $debug);
unset($arg, $value, $time, $debug);


/******
 * DB *
 ******/
try { $dbh = new PDO($cfg['db']['dsn'], $cfg['db']['dbu'], $cfg['db']['dbp']); }
catch (PDOException $e) { error_log('Database :: '.$e->getMessage()); die(); }
unset($cfg['db']);
$Db = new Db($dbh);


/**********
 * MAILER *
 *********/
$mailer = new_PHPMailer($cfg['smtp']);
$mailer->Subject    = '[LINCOLN - SISTEMA - ACCESO] Reporte del '.date('d-m-Y', TODAY);
$mailer->Body       = 'Se han adjuntado los listados en formato CSV para ser visualizados en Excel.';

foreach ($cfg['cron']['recipients'] as $recipient) $mailer->AddAddress($recipient['email'], (isset($recipient['name']) ? $recipient['name'] : null));


/***********
 * CRONTAB *
 **********/
$files_to_unlink = array();

$crontab = array(
    'rfid',
    'user_absence'
);

foreach ($crontab as $file) require_once('_bin/cron.'.$file.'.php');

/********
 * MAIL *
 ********/
if ($files_to_unlink && !DEBUG) {
    if ($cfg['smtp']['host']) {
        if (!$mailer->send()) {
            error_log('CRON :: Message could not be sent :: '.$mailer->ErrorInfo);
        }
    }

    foreach ($files_to_unlink as $f_name) {
        unlink($f_name);
    }
}