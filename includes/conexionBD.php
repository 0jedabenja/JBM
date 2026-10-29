<?php
// En hosting, definir DB_HOST, DB_USER, DB_PASSWORD y DB_NAME; estos valores conservan el entorno local de XAMPP.
date_default_timezone_set('America/Montevideo');
// Mantiene el manejo existente de errores MySQL mediante valores de retorno en PHP 8.1+.
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST') ?: 'localhost';
$usuario = getenv('DB_USER') ?: 'root';
$contra = getenv('DB_PASSWORD');
$db = getenv('DB_NAME') ?: 'lodetorres';
if ($contra === false) {
    $contra = '';
}
$conexion = mysqli_connect($host, $usuario, $contra, $db);
if (!$conexion) {
    error_log('Error de conexión: ' . mysqli_connect_error());
    if (defined('JBM_JSON_API')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'No se pudo conectar con la base de datos.'), JSON_UNESCAPED_UNICODE);
        exit();
    }
    $codigoError = 500;
    include __DIR__ . '/../errores.php';
    exit();
}
mysqli_set_charset($conexion, 'utf8mb4');
if (!mysqli_query($conexion, "SET time_zone = '-03:00'")) {
    error_log('No se pudo configurar la zona horaria de la base de datos: ' . mysqli_error($conexion));
    if (defined('JBM_JSON_API')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => 'No se pudo configurar la zona horaria del sistema.'), JSON_UNESCAPED_UNICODE);
        exit();
    }
    $codigoError = 500;
    include __DIR__ . '/../errores.php';
    exit();
}