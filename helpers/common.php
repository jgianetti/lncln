<?php
/**
 * Class Autoloader
 * If no namespace is specified it loads the class file from \lib
 *
 * @param $class_name
 */
function autoload($class_name) {
    $class_name = ltrim($class_name, '\\');
    $file_name = '';
    $namespace = '';

    // Jan_Module = modules\module
    if (strtolower(substr($class_name, 0, 4)) == "jan_")
        $class_name = 'modules\\' . substr($class_name, 4);
    else
        $class_name = 'lib\\' . $class_name;

    if ($lastNsPos = strripos($class_name, '\\')) {
        $namespace = substr($class_name, 0, $lastNsPos);
        $class_name = substr($class_name, $lastNsPos + 1);
        $file_name .= strtolower(str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR);
    }

    // $file_name .= str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';
    $file_name .= $class_name . '.php';
    require $file_name;
}

/**
 * Variable scope to prevent replacing an array
 *
 * @param string $file
 * @param array $param
 * @return array
 */
function include_file($file, $param = array()) {
    //extract everything in $param into the current scope
    extract($param);
    return include($file);
}

/**
 * Strip spaces
 *
 * @param $html string
 * @return string
 */
function html_minify($html) {
    $search = array(
        '/\>[^\S ]+/s', //strip whitespaces after tags, except space
        '/[^\S ]+\</s', //strip whitespaces before tags, except space
        '/(\s)+/s'  // shorten multiple whitespace sequences
    );
    $replace = array(
        '>',
        '<',
        '\\1'
    );
    return preg_replace($search, $replace, $html);
}

/**
 * Convert data to JSON valid
 * @param array $array
 * @return array
 */
function sanitizeToJson($array)
{
    array_walk_recursive($array, function (&$value, $key) {
        $value = htmlentities(utf8_encode($value));
    });
    return $array;
}

/**********
 * MAILER *
 *
 * @param array $options
 * @return \PHPMailer
 */
function new_PHPMailer($options) {
    $mailer = new PHPMailer();
    $mailer->IsSMTP();
    $mailer->Host = $options['host'];
    $mailer->Port = $options['port'];
    $mailer->From = $options['from'];
    $mailer->FromName = $options['from_name'];
    $mailer->CharSet  = $options['charset'];

    if (!empty($options['secure'])) $mailer->SMTPSecure = $options['secure'];
    if ($mailer->SMTPAuth = $options['auth']) {
        $mailer->Username = $options['user'];
        $mailer->Password = $options['pwd'];
    }
    return $mailer;
}


/*
 * NOT USED
 */

/**
 * Simplified function to return formated date, default to today
 * @param string $date
 * @param string $format
 * 
 * @eturn string
 */
function sanitize_date($date = null, $format = 'Y-m-d') {
    if (!$date || !strtotime($date = str_replace('/', '-', $date))) return date($format);
    elseif (is_numeric($date)) return date($format, strtotime($date . '-01-01')); // year only = year-01-01
    else return date($format, strtotime($date));
}

/**
 * @param int $idx
 * @return array|string
 */
function days_str($idx = null)
{
    $days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
    return ($idx ? $days[$idx] : $days);
}