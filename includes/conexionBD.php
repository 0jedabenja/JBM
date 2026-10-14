<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $host = 'localhost';
    $usuario = 'root';
    $contra = '280307';
    $db = 'lodetorres';

    $conexion = mysqli_connect($host, $usuario, $contra, $db);
    mysqli_set_charset($conexion, 'utf8mb4');
} catch (Throwable $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    exit('Error al conectar con la base de datos.');
}
