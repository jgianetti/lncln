<?php
$files = [
    'constants.php',
    'global.php',
    'local.php',
    'module_action_list.php'
];

foreach ($files as $file) {
    $file_name = dirname(__FILE__) . '/'.$file;
    if (is_file($file_name)) include_once($file_name);
}