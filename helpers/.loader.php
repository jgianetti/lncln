<?php
if ($dh = opendir(__DIR__)) {
    while ($file = readdir($dh)) {
        if ($file[0] == "." || (pathinfo($file, PATHINFO_EXTENSION) == 'dist')) continue;
        include_once(dirname(__FILE__) . '/'.$file);
    }
    closedir($dh);
}