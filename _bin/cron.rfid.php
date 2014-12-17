<?php
/**
 * Compute users check in late
 * Compute users check out early
 * Check out users who forgot to do so
 * Build & Email CSV file
 */

namespace cron;
use Jan_Rfid\Work_ShiftModel,
    DbDataMapper
;

/**
 * @var $cfg array
 * @var $dbh \PDO
 * @var $Db \Db
 * @var $mailer \PHPMailer
 */

$work_shiftModel = new Work_ShiftModel($Db);

// Have entered late
$attach_name = '_bin/Lincoln.Sistema.'.date('Y-m-d', TODAY).'.Ingresos-tarde.csv';
$attach_body = "sep=\t\r\n";

$work_shifts = $work_shiftModel->getLateInOn(YESTERDAY);

if ($work_shifts) {
    $attach_body .= "Usuarios que el dia ".date('d/m/Y', YESTERDAY)." han ingresado tarde\r\n\r\n";
    $attach_body .= "Apellido, Nombre \t Turno \t Ingreso \t Categoria\r\n\r\n";
    foreach ($work_shifts as $w_s) $attach_body .= $w_s['user_name'] . "\t" . date('H:i:s',strtotime($w_s['expected_start'])) . ' - ' . date('H:i:s',strtotime($w_s['expected_end'])) . "\t" . date('H:i:s',strtotime($w_s['started_on'])) . "\t" . $w_s['user_cat_names'] . "\r\n";
    $attach_body .= "\r\n\r\n";

    file_put_contents($attach_name, $attach_body);
    $mailer->AddAttachment($attach_name);
    $files_to_unlink[] = $attach_name;
}

// Have left early
$attach_name = '_bin/Lincoln.Sistema.'.date('Y-m-d', TODAY).'.Salidas-temprano.csv';
$attach_body = "sep=\t\r\n";

$work_shifts = $work_shiftModel->getEarlyOutOn(YESTERDAY);

if ($work_shifts) {
    $attach_body .= "Usuarios que el dia ".date('d/m/Y', YESTERDAY)." se han retirado antes\r\n\r\n";
    $attach_body .= "Apellido, Nombre \t Turno \t Salida \t Categoria\r\n\r\n";
    foreach ($work_shifts as $w_s) $attach_body .= $w_s['user_name'] . "\t" . date('H:i:s',strtotime($w_s['expected_start'])) . ' - ' . date('H:i:s',strtotime($w_s['expected_end'])) . "\t" . date('H:i:s',strtotime($w_s['ended_on'])) . "\t" . $w_s['user_cat_names'] . "\r\n";
    $attach_body .= "\r\n\r\n";

    file_put_contents($attach_name, $attach_body);
    $mailer->AddAttachment($attach_name);
    $files_to_unlink[] = $attach_name;
}


/*******************
 * IN SCHOOL USERS *
 *******************
 * Users that didn't check out
 */
$attach_name = '_bin/Lincoln.Sistema.'.date('Y-m-d', TODAY).'.No-registraron-salida.csv';
$attach_body = "sep=\t\r\n";
$db = new DbDataMapper($dbh);

try{
    // All users in_school except for those who are working
    $sql = 'SELECT
                u.id,
                u.name,
                u.last_name,
                u.cat_names,
                u.cc_cat_names
            FROM user AS u
            WHERE
                u.in_school = "1"
                AND NOT EXISTS (
                    SELECT u_w_s.*
                    FROM user_work_shift AS u_w_s
                    WHERE u_w_s.user_id = u.id
                        AND u_w_s.expected_end > "'.date('Y-m-d', TODAY).'"
                )
             ORDER BY u.last_name ASC
    ';

    $users = $db->fetchAll($sql);
}
catch (\PDOException $e) {
    error_log('CRON :: RFID :: '.$e->getMessage());
    return;
}

if ($users) {
    $stmt_u     = $db->prepare('UPDATE `user` SET `in_school` = "0" WHERE id = ? LIMIT 1');
    $stmt_u_m   = $db->prepare('INSERT INTO `user_movement` (`id`, `user_work_shift_id`, `user_id`, `date`, `is_entering`, `entrance`, `deleted`, `comments`) VALUES (?,?,?,?,?,?,?,?)');
    $stmt_u_w_s = $db->prepare('UPDATE `user_work_shift` SET `ended_on` = "'.date('Y-m-d H:i:s', TODAY).'" WHERE id = ? LIMIT 1');

    $attach_body .= "Usuarios que, a la fecha, no han registrado la salida\r\n\r\n";
    $attach_body .= "Apellido, Nombre \t Categoria \t Cat. CopyCenter \t Inicio de Turno \t Fin de Turno\r\n\r\n";

    foreach ($users as $u) {
        // Most recent work shift = array[0]
        $user_work_shift = $work_shiftModel->search(array('user_id' => $u['id']),'u_w_s.id DESC',1);
        // User did not check out while working
        if ($user_work_shift && !$user_work_shift[0]['ended_on']) {
            $u['work_shift_id']             = $user_work_shift[0]['id'];
            $u['work_shift_expected_start'] = $user_work_shift[0]['expected_start'];
            $u['work_shift_expected_end']   = $user_work_shift[0]['expected_end'];
        }
        else $u['work_shift_id'] = $u['work_shift_expected_start'] = $u['work_shift_expected_end'] = null;

        $attach_body .= $u['last_name'] . ', ' . $u['name'] . "\t" . $u['cat_names'] . "\t" . $u['cc_cat_names'] . "\t" . ($u['work_shift_id'] ? (date('d/m/Y H:i',strtotime($u['work_shift_expected_start']))."\t". date('d/m/Y H:i',strtotime($u['work_shift_expected_end']))) : "\t") . "\r\n";

        if (!DEBUG) {
            try {
                $stmt_u->execute(array($u['id']));
                $stmt_u_m->execute(array(uniqid(), ($u['work_shift_id'] ? $u['work_shift_id'] : null), $u['id'], date('Y-m-d H:i:s', TODAY), '0', 'Admin', '0', 'Registrado por el sistema automatico'));
                if ($u['work_shift_id']) $stmt_u_w_s->execute(array($u['work_shift_id']));
            }
            catch (\PDOException $e) {
                error_log('CRON :: RFID :: '.$e->getMessage());
            }

            usleep(100000); // 0.1sec - uniqid();
        }
    }

    file_put_contents($attach_name, $attach_body);
    $mailer->AddAttachment($attach_name);
    $files_to_unlink[] = $attach_name;
}