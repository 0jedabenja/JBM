<?php
$host = 'localhost';
$usuario = 'root';
$contra = '';
$db   = 'lodetorres';
$conexion = mysqli_connect($host, $usuario, $contra, $db);
if (!$conexion) {
    error_log('Error de conexión: ' . mysqli_connect_error());
    $codigoError = 500;
    include __DIR__ . '/../errores.php';
    exit();
}
mysqli_set_charset($conexion, 'utf8mb4');