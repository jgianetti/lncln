<?php
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

$output_file_sql = fopen('_bin/user_generator.output.sql', 'w+');
$output_file_csv = fopen('_bin/user_generator.output.csv', 'w+');

// CSV Headers
fputs($output_file_csv, "sep=;\n");
fputcsv($output_file_csv, [
    'Institucion',
    'Domicilio',
    'CP',
    'Localidad',
    'Jurisdiccion',
    'Gestion',
    'Region',
    'Sede',
    'Subsede',
    'Perfil',
    'Usuario',
    'Password'
], ';');

// SQL
fputs($output_file_sql, 'INSERT INTO usuario (dni,nombre,apellido,email,usuario,password,perfil_id,institucion_id) VALUES ');

$instituciones = $Db->prepareFetchAll('SELECT * FROM institucion AS i');

$cant_inst = count($instituciones);
$i = 0;
foreach ($instituciones AS $row) {

    // Responsable
    $data = [
        'inst_nombre'=> $row['nombre'],
//        'inst_nombre'=> utf8_decode($row['nombre']),
        'dni'       => $row['id'],
        'nombre'    => $row['id'].'_r',
        'apellido'  => $row['id'].'_r',
        'email'     => $row['id'].'_r@infd.edu.ar',
        'usuario'   => $row['id'].'_r@infd.edu.ar',
        'password'  => $row['id'].'_r12345678',
        'perfil_id' => 80,
        'institucion_id' => $row['id'],
    ];
    
    // CSV
    fputcsv($output_file_csv, [
        $data['inst_nombre'],
        $row['domicilio'],
        $row['cp'],
        $row['localidad'],
        $row['jurisdiccion'],
        $row['gestion'],
        $row['region'],
        $row['sede'],
        $row['subsede'],
        'Responsable',
        $data['usuario'],
        $data['password']
    ], ';');
    
    // SQL
    $sql = ' (
        '.$data['dni'].',
        "'.$data['nombre'].'",
        "'.$data['apellido'].'",
        "'.$data['email'].'",
        "'.$data['usuario'].'",
        "'.password_hash($data['password']).'",
        '.$data['perfil_id'].',
        '.$data['institucion_id'].'
        ), ';
    
    fputs($output_file_sql, $sql);
    
    
    // Cargador
    $data = [
        'inst_nombre'=> $row['nombre'],
//        'inst_nombre'=> utf8_decode($row['nombre']),
        'dni'       => $cant_inst.$row['id'],
        'nombre'    => $row['id'].'_c',
        'apellido'  => $row['id'].'_c',
        'email'     => $row['id'].'_c@infd.edu.ar',
        'usuario'   => $row['id'].'_c@infd.edu.ar',
        'password'  => $row['id'].'_c12345678',
        'perfil_id' => 50,
        'institucion_id' => $row['id'],
    ];
    
    fputcsv($output_file_csv, [
        $data['inst_nombre'],
        $row['domicilio'],
        $row['cp'],
        $row['localidad'],
        $row['jurisdiccion'],
        $row['gestion'],
        $row['region'],
        $row['sede'],
        $row['subsede'],
        'Cargador',
        $data['usuario'],
        $data['password']
    ], ';');

    // SQL
    $sql = ' (
        '.$data['dni'].',
        "'.$data['nombre'].'",
        "'.$data['apellido'].'",
        "'.$data['email'].'",
        "'.$data['usuario'].'",
        "'.password_hash($data['password']).'",
        '.$data['perfil_id'].',
        '.$data['institucion_id'].'
        )';
    
    if (++$i<$cant_inst) $sql .= ',';
            
    fputs($output_file_sql, $sql);

    echo $i."\n";
}
