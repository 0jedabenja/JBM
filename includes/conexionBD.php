<?php
$host = 'localhost';
$usuario = 'root';
$contra = '';
$db   = 'lodetorres';
$conexion = mysqli_connect($host, $usuario, $contra, $db);
if (!$conexion) {
    error_log('Error de conexión: ' . mysqli_connect_error());
    exit('Error al conectar con la base de datos.');
}
mysqli_set_charset($conexion, 'utf8mb4');