<?php
/**
 * Compute users absences from yesterday
 * Build & Email CSV file
 */
namespace cron;
use DbDataMapper
;

/**
 * @var $cfg array
 * @var $dbh \PDO
 * @var $Db \Db
 * @var $mailer \PHPMailer
 */


$attach_name = '_bin/Lincoln.Sistema.'.date('Y-m-d', TODAY).'.Inasistencias.csv';
$attach_body = "sep=\t\r\n";

$db = new DbDataMapper($dbh);

try{
    /*
     * Selecciona usuarios que no hayan registrado movimientos el dia anterior
     * Trae:
     *   - el usuario,
     *   - su horario del dia anterior y
     *   - su inasistencia mas reciente. Permite comparar y evitar cargar 2 veces la misma inasistencia
     *
     * Siempre que:
     *   - su horario del dia anterior no sea nulo
     *   - el dia anterior no sea una excepcion (se considera excepcion si se le modifico el horario el dia de hoy)
     *   - el dia anterior no tuvo un movimiento que perteneciera a un turno.
     *     (puede que haya tenido un movimiento pero que no corresponda al turno del dia (ie: salida del turno noche))
     *   - el dia anterior no era un dia "no laboral" (aka: era un dia laboral)
     */

    $sql = ' SELECT u.*,
                 u_a.id AS user_absence_id,
                 u_a.date AS user_absence_date
             FROM user AS u
             LEFT JOIN user_schedule AS u_s ON u_s.id = u.id
             LEFT JOIN (
                SELECT MAX(id) AS id, user_id, date
                FROM user_absence
                GROUP BY user_id
             ) AS u_a ON (u_a.user_id = u.id)
             WHERE
                    (u.deleted = 0 OR u.deleted IS NULL)
                AND
                    u_s.'.date('l', YESTERDAY).'_in IS NOT NULL
                AND ( u_s.exception IS NULL OR u_s.exception != "'.date('Y-m-d', YESTERDAY).'" )
                AND
                    NOT EXISTS (
                        SELECT u_w_s.*
                        FROM user_work_shift AS u_w_s
                        WHERE u_w_s.user_id = u.id
                            AND DATE(u_w_s.expected_start) = "'.date('Y-m-d', YESTERDAY).'"
                    )
                AND
                    NOT EXISTS (
                        SELECT u_nwd.*
                        FROM user_non_working_days AS u_nwd
                        WHERE u_nwd.user_id = u.id
                            AND u_nwd.from <= "'.date('Y-m-d', YESTERDAY).'"
                            AND u_nwd.to >= "'.date('Y-m-d', YESTERDAY).'"
                    )
             ORDER BY u.last_name ASC
            ';

    $users = $db->fetchAll($sql);
}
catch (\PDOException $e) {
    error_log('CRON :: USER ABSENCE :: '.$e->getMessage());
    return false;
}

if ($users) {
    $stmt = $db->prepare('INSERT INTO user_absence SET `user_id` = ?, `date` = ?');

    $attach_body .= "Usuarios que no han asistido el dia ".date('d-m-Y', YESTERDAY)."\r\n\r\n";
    $attach_body .= "Apellido, Nombre \t Categoria \t Cat. CopyCenter\r\n\r\n";

    foreach ($users as $u) {
        $attach_body .= $u['last_name'] . ', ' . $u['name'] . "\t" . $u['cat_names'] . "\t" . $u['cc_cat_names'] . "\r\n";
        if (($u['user_absence_date'] != date('Y-m-d', YESTERDAY)) && !DEBUG) {
				try {
					$stmt->execute(array($u['id'], date('Y-m-d', YESTERDAY)));
				}
				catch (\PDOException $e) {
                    error_log('CRON :: USER ABSENCE :: '.$e->getMessage());
				}

				usleep(100000); // 0.1sec - uniqid();
		}
    }

    file_put_contents($attach_name, $attach_body);
    $mailer->AddAttachment($attach_name);
    $files_to_unlink[] = $attach_name;
}