<?php
namespace Jan_Error;
use Jan_Acl\AclHelper;

class Controller {
    
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $errors = [];

        if (is_file($App->cfg['error_log']) && ($fh = fopen($App->cfg['error_log'], "r+"))) {
            if ($App->request->fetch('clean')) ftruncate($fh, 0);
            else {
                while (($line = fgets($fh)) !== false) {
                    if (!($line = trim($line))) continue;

                    $date_end = strpos($line,']')-1;

                    $errors[] = sanitizeToJson([
                        'date'    => substr($line,1,$date_end),
                        'details' => substr($line,$date_end+2),
                    ]);
                }
            }
            fclose($fh);
        }

        return [
            'sEcho'                => $App->request->fetch('sEcho'),
            'iTotalRecords'        => count($errors),
            'iTotalDisplayRecords' => count($errors),
            'aaData'               => &$errors,
        ];

    }
}