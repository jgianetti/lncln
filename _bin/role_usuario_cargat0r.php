<?php
/**
 * Asocia cada usuario a un rol especifico de acuerdo a su (obsoleto) perfil_id
 * Tal como esta funciona x unica vez luego de correr pnfp.alter_db.2014-11-11.sql
 */
if (basename(getcwd()) == '_bin') chdir('../');
require_once('helpers/.loader.php');
require_once('etc/.loader.php');

/**
 * @var $cfg array
 */

/******
 * DB *
 ******/
try { $dbh = new PDO($cfg['db']['dsn'], $cfg['db']['dbu'], $cfg['db']['dbp']); }
catch (PDOException $e) { die(json_encode(array('textStatus' => 'error', 'errors' => 'Fatal error: No Database connection'))); }
unset($cfg['db']);
$Db = new Db($dbh);


$perfil_rol = [
    50 => 4, // cargador
    80 => 3, // responsable
    100 => 2 // Admin    
];


$new_rol_usuario = $Db->prepare('
    INSERT INTO role_usuario
    SET
        usuario_id = ?,
        role_id = ?
');

$users = $Db->prepareFetchAll('SELECT * FROM usuario AS u');
foreach ($users as $user) {
    $new_rol_usuario->execute([$user['id'], $perfil_rol[$user['perfil_id']]]);
    usleep(100);
}