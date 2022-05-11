<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header('Content-Type: text/html; charset=UTF-8');
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
error_reporting(E_ALL);
date_default_timezone_set('America/Lima');

// require('config.php');
$dbHost     = "localhost";
$dbPort     = 3306;
$dbName     = "dbname";
$dbUser     = "gts";
$dbPass     = "password";
$gpsProvider = "AguilaControl";

$ws_endpoint     = "https://seguridadciudadana.mininter.gob.pe/retransmisionGPS/ubicacionGPS";

$ubigeo         = "130702";
$key            = "97a7d2ee-6c11-4004-83e8-9256569ef8e5";
$empresa        = "synthesis";

$conexion       = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conexion->connect_error) {
    die('Error de conectando a la base de datos: ' . $conexion->connect_error);
}

$sqlQuery         = "SELECT id, placa, latitud, longitud, angulo, altitud, imei, velocidad, fechaHora, horasMotor, distancia, totalHorasMotor, totalDistancia, estado, empresa FROM `Mininter` WHERE `estado`='Nuevo' AND `empresa`='$empresa' ORDER BY `id` DESC LIMIT 100;";

$resultado         = $conexion->query($sqlQuery);

$start      = 0;
$end        = 0;

if ($resultado->num_rows > 0) {

    while ($row = $resultado->fetch_array(MYSQLI_ASSOC)) {

        if ($start == 0) {
            $start = $row['id'];
        }

        $reponseData[] = array(
            'alarma'         => 'none',
            'altitud'        => (int)$row['altitud'],
            'angulo'         => (int)$row['angulo'],
            'distancia'      => 0,
            'fechaHora'      => $row['fechaHora'],
            'horasMotor'     => 0,
            'idMunicipalidad' => $key,
            'ignition'       => true,
            'imei'           => $row['imei'],
            'latitud'        => floatval($row['latitud']),
            'longitud'       => floatval($row['longitud']),
            'motion'         => true,
            'placa'          => $row['placa'],
            'totalDistancia' => 0,
            'totalHorasMotor' => 0,
            'ubigeo'         => $ubigeo,
            'valid'          => true,
            'velocidad'      => (int)$row['velocidad']
        );

        $end = $row['id'];
    }
} else {
    $conexion->close();
    die("Todos los registros han sido enviados! No hay data nueva que enviar...");
}
print_r("<div style='color: #fff; background: #000; padding: 10px; width=500px'>");

$ch             = curl_init();
foreach ($reponseData as $trama) {
    $payload         = json_encode($trama);
    curl_setopt($ch, CURLOPT_URL, $ws_endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $server_output = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    print_r("<div><code>" . json_encode($payload, JSON_PRETTY_PRINT) . "</code></div>");
    print_r("<div><code>" . json_encode($server_output, JSON_PRETTY_PRINT) . "</code></div>");
    print_r("<div><code>" . json_encode($httpcode, JSON_PRETTY_PRINT) . "</code></div>");
    print_r("<hr/>");
    $err         = curl_error($ch);
    print_r($err);
}
curl_close($ch);

$SqlUpdate = "UPDATE `Mininter` SET `estado`='Sent' WHERE `id` BETWEEN $end AND $start AND `empresa`='$empresa'";

$mensajeUpdate = "";

if ($conexion->multi_query($SqlUpdate) === TRUE) {
    $mensajeUpdate    = "Registros Enviados!  ";
} else {
    $mensajeUpdate    = "Error insertando en la tabla " . $conexion->error;
    $conexion->close();
    die($mensajeUpdate);
}

$conexion->close();

print_r("</div>");
