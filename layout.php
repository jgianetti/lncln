<?php
/**
 * @var $cfg array
 * @var $lang array
 * @var $request Request
 * @var $session Session
 * @var $aclHelper AclHelper
 * @var $controller_return array
 */

$session_user = $session->get('user');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"> 
  <!--  <meta http-equiv="content-type" content="text/html; charset=iso-8859-1" /> -->
    <title><?php echo $lang['_base']['document_title'] ?> :: <?php echo $lang['_base'][$request->getModule()]." - ".$lang['_base'][$request->getAction()]; ?></title>
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/jquery-ui-1.10.0.custom.min.css" />
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/cmxformTemplate.css" />
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/jquery.dataTables.css" />
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/jquery.dataTables.TableTools.css" />
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/jquery-ui-timepicker-addon.min.css" />
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/app.css" />
</head>
<body>
<div id="container">
    <header>
        <div id="header">
            <h1>Nuestra Escuela</h1>
        </div>
        <nav><?php echo include_file('navbar.php', compact('cfg','lang','session', 'aclHelper')); ?><br class="clr"></nav>
    </header>
    
    <div id="main">
        <?php if (isset($controller_return) && isset($controller_return['_view'])) include('modules/'.$request->getModule() . '/' . $controller_return['_view'] . '.php'); ?>
    </div>
    
    <div id="footer">Asociaci&oacute;n Escuelas Lincoln<br>ver. <?php echo APP_VERSION .' ('.date('d/m/Y',filemtime('etc/constants.php')).')'; ?>
    </div>
</div>
<div id="cache">
    <div name="app-window" class="ui-widget ui-widget-content app-window">
        <div class="app-window-header">
            <h1></h1>
            <button class="ui-state-default ui-corner-all app-btn app-window-minimize"><span class="ui-icon ui-icon-carat-1-n">&raquo;</span></button>
            <br class="clr">
        </div>
        <div class="ui-corner-bottom app-window-content"></div>
    </div>
    <div name="del-confirm">
        <form method="post" action="del" data-ajax-callback="on_del" >
            <div class="confirm-text"></div>
            <div class="db-checkbox">
                <input type="checkbox" id="del-confirm-del-data" name="del_data" value="1">
                <label for="del-confirm-del-data"></label>
            </div>
            <div class="comments">
                <label for="del-confirm-comments"></label>
                <textarea id="del-confirm-comments" name="comments"></textarea>
            </div>
            <input type="hidden" name="id">
            <input type="hidden" name="confirm" value="1">
        </form>
    </div>
    <div name="undel-confirm">
        <form method="post" action="undel" data-ajax-callback="on_undel" >
            <p class="confirm-text"></p>
            <input type="hidden" name="id">
            <input type="hidden" name="confirm" value="1">
        </form>
    </div>
    <button name="app-button" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only app-btn">
        <span class="ui-button-text"></span>
    </button>
</div>
<div class="loading-modal"></div>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery-2.0.1.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery-ui-1.10.0.custom.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/globalize.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/globalize_cultures/globalize.culture.es.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/mustache.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery.validate.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery.dform-1.0.1.min.js"></script>
<?php
// TODO: every resource
echo '<script src="'.$cfg['base_url'] .'/_pub/js/jquery.dataTables'. ((APP_ENV == APP_ENV_PROD) ? '.min' : '') . '.js"></script>';
?>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery.dataTables.columnFilter.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery.dataTables.TableTools.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery-ui-timepicker-addon.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/jquery-ui-timepicker-es.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/tinymce/tinymce.min.js"></script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/tinymce/jquery.tinymce.min.js"></script>
<script>
var App = (function (my) {
    my.init_url     = '<?php echo $request->getUrl(); ?>';
    my.url_base     = '<?php echo $cfg['base_url'] ?>';
    my.lang         = '<?php echo $session_user['lang'] ?>';
    my.query_string = <?php  echo json_encode($_GET, JSON_HEX_TAG);?>;
    my.session =  {
        "lifetime"     : <?php echo $cfg['session']['lifetime']; ?>,
        "ajax_refresh" : <?php echo $cfg['session']['ajax_refresh']; ?>
    };

    my.APP_ENV_DEV  = '<?php echo APP_ENV_DEV; ?>';
    my.APP_ENV_PROD = '<?php echo APP_ENV_PROD; ?>';
    my.APP_ENV      = '<?php echo APP_ENV; ?>';
    my.JS_VERSION   = '<?php echo APP_VERSION; ?>';

    return my;
}(App || {}));
</script>
<script src="<?php echo $cfg['base_url'] ?>/_pub/js/app.js?v=<?php echo APP_VERSION; ?>"></script>
</body>
</html>