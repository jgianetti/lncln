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

if (basename(getcwd()) == '_bin') chdir('../');

require_once('helpers/.loader.php');
require_once('etc/.loader.php');

/**
 * @var $cfg array
 */


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
catch (PDOException $e) { die(json_encode(array('textStatus' => 'error', 'errors' => 'Fatal error: No Database connection'))); }
unset($cfg['db']);
$Db = new Db($dbh, ((APP_ENV == APP_ENV_DEV) ? ['errmode' => PDO::ERRMODE_EXCEPTION] : null));


/**********
 * MAILER *
 *********/
$mailer = new \PHPMailer();
$mailer->IsSMTP();
$mailer->Host       = $cfg['smtp']['host'];
$mailer->Port       = $cfg['smtp']['port'];
$mailer->From       = $cfg['smtp']['from'] ;
$mailer->FromName   = $cfg['smtp']['from_name'];

if (!empty($cfg['smtp']['secure'])) $mailer->SMTPSecure = $cfg['smtp']['secure']; 

if ($mailer->SMTPAuth = $cfg['smtp']['auth']) {
    $mailer->Username = $cfg['smtp']['user'];
    $mailer->Password = $cfg['smtp']['pwd'];
}

$mailer->Subject    = '[NUESTRA ESCUELA - NEC2] Reporte del '.date('d-m-Y', TODAY);
$mailer->Body       = 'Cuerpo del mensaje.';

$recipients         = $cfg['smtp']['recipients'];
foreach ($recipients as $email) $mailer->AddAddress($email['email'], (isset($email['name'])?$email['name']:null));


/***********
 * CRONTAB *
 **********/
$files_to_unlink = [];

$crontab = [
    
];

foreach ($crontab as $file) require_once('_bin/cron.'.$file.'.php');

/********
 * MAIL *
 ********/
if ($files_to_unlink && !DEBUG) {
    if (!$mailer->send()) log_error('Message could not be sent :: ' . $mailer->ErrorInfo, $cfg['cron_error_file']);
    foreach ($files_to_unlink as $f_name) unlink($f_name);
}