<?php
/**
 * Build module/action list
 * Needed by index.php and modules/acl
 */

if (basename(getcwd()) == '_bin') chdir('../');

require_once('helpers/.loader.php');
require_once('etc/.loader.php');

/**
 * @var $cfg array
 */

$output_file = 'etc/module_action_list.php';
if (!$fh = fopen($output_file, 'w+')) die('Coul not open '.$output_file);


fwrite($fh, '
//built with _bin/acl_module_action_list_builder.php
<?php $cfg["modules"] =
 ');

$ret = [];
/********************
 * READ MODULES DIR *
 ********************/
if (($dirh = opendir('modules'))) {
    while (false !== ($module = readdir($dirh))) {
        if (($module == ".") || ($module == "..") || !is_dir('modules/'.$module) || !is_file('modules/' . $module . '/Controller.php')) continue;

        $ret[$module] = get_class_methods('Jan_'.ucfirst($module).'\Controller');
    }
    closedir($dirh);
}

fwrite($fh, var_export($ret, true));
fwrite($fh, ';');
