<?php
require_once('helpers/.loader.php');
require_once('etc/.loader.php');

/**
 * @var $cfg array
 */

ini_set('log_errors', 1);
ini_set('error_log', $cfg['error_log']);
ini_set('html_errors', false);
ini_set('session.gc_maxlifetime', $cfg['session']['lifetime']);


/**************
 * ENVIROMENT *
 **************/
if (!defined('APP_ENV')) define('APP_ENV', getenv('APP_ENV') ?: APP_ENV_PROD);

if (APP_ENV == APP_ENV_DEV) error_reporting(E_ALL ^ E_STRICT);
else error_reporting(0);

define('APP_ROOT', str_replace('\\','/',__DIR__) . '/');

$errors = [];


/***********
 * REQUEST *
 ***********/
$request = new Request($cfg);
if (!($module = $request->getModule())) $module = $cfg['default_module'];
if (!($action = $request->getAction())) $action = $cfg['default_action'];


/******************************
 * MODULE & ACTION VALIDATION *
 ******************************/
if ($module && (!preg_match('/^[_[:alnum:]]+$/', $module) || !in_array($module, array_keys($cfg['modules'])))) $errors[] = '404_module_not_found';
elseif ($action && (!preg_match('/^[_[:alnum:]]+$/', $action) || !in_array($action, $cfg['modules'][$module]))) $errors[] = '404_action_not_found';

if (count($errors)) {
    header("HTTP/1.0 404 Not Found");
    die(json_encode(['textStatus' => 'error', 'errors' => $errors]));
}


/***********
 * SESSION *
 ***********/
$session = new Session($cfg['session_key']);
if ($request->fetch('get_version')) die(json_encode(['app_version' => APP_VERSION])); // keeps session alive
if ($request->fetch('logout')) $session->destroy();
// not logged
if ((!($session_user = $session->get('user')) || !isset($session_user['id'])) && ($module!='user' || $action!='login')) {
    if ($request->fetch('ajax')) die(json_encode(['textStatus' => 'error', 'errors' => ['login_timeout']]));
    header('Location: ' . $cfg['base_url'] .'/user/login'); die();
}

if (!isset($session_user['lang']) || !$session_user['lang']) $session_user['lang'] = $cfg['languages'][0];
$session->set('user', $session_user)->save();


/******
 * DB *
 ******/
try { $dbh = new PDO($cfg['db']['dsn'], $cfg['db']['dbu'], $cfg['db']['dbp']); }
catch (PDOException $e) { die(json_encode(['textStatus' => 'error', 'errors' => 'Fatal error: No Database connection'])); }
unset($cfg['db']);
$db = new Db($dbh);



/************
 * USER ACL *
 ************/
$aclSearch = new Jan_Acl\AclSearchDb($db);
$aclHelper = new Jan_Acl\AclHelper();
$session_user['acl'] = (empty($session_user['id']) ? [] : $aclHelper->toAssoc($aclSearch->getMerged($session_user['id'])));
$session->set('user', $session_user);

// Validate Module & Action
if ($module && $action && (($module != 'user') || ($action != 'login')) && !$aclHelper->is_allowed($session_user['acl'], $module.'.'.$action)) {
    error_log('ACL :: '.$session_user['user'].' :: id='.$session_user['id'].' :: '.$module.'.'.$action);
    //header('HTTP/1.0 403 Forbidden');
    die(json_encode(['textStatus' => 'error', 'errors' => ['not_allowed_action']]));
}


/**************
 * CONTROLLER *
 **************/
// hotfix: TODO: REFACTOR - get if controller set layout / view
if (!$module
    || (!$request->is_ajax && ($module != 'cc_help') && ($action != 'print_in_school'))
) {
    $controller_return = [];
}
else {
    try {
        $controller_class = 'Jan_'.ucfirst($module).'\Controller';
        $controller_return =  (new $controller_class)->{$action}(new App([
            'request' => $request,
            'session' => $session,
            'cfg' => $cfg,
            'db' => $db,
        ]));
    }
    catch (PDOException $e) {
        error_log('PDO :: '.@$session_user['user'].' :: id='.@$session_user['id'].' :: '.$module.'.'.$action.' :: '.$e->getMessage());
        die(json_encode(['textStatus' => 'error', 'errors' => [((APP_ENV == APP_ENV_DEV) ? $e->getMessage() : 'error_db')]]));
    }
    catch (Exception $e) {
        error_log('Generic :: '.@$session_user['user'].' :: id='.@$session_user['id'].' :: '.$module.'.'.$action.' :: '.$e->getMessage());
        die(json_encode(['textStatus' => 'error', 'errors' => [((APP_ENV == APP_ENV_DEV) ? $e->getMessage() : 'error_generic')]]));
    }
}

/*******************
 * OUTPUT RESPONSE *
 *******************/
if (!$request->is_ajax) {
    header("Content-type: text/html;charset=utf8");
    //lang file
    $lang['_base'] = json_decode(file_get_contents('_pub/lang/'.$session_user['lang'].'.json'), true);

    include_file((!isset($controller_return['_layout']) ? 'layout.php' : 'modules/' . $module . '/layouts/' . $controller_return['_layout'] . '.php'), compact('cfg', 'lang', 'request', 'session', 'aclHelper', 'controller_return'));
    die();
}

header("Content-type: application/json;charset=utf8");
echo json_encode($controller_return);