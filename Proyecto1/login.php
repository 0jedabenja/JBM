<?php
session_start();
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$usuario = $_POST['usuario'];
$contrasena = $_POST['contrasena'];

$sql = "SELECT * FROM empleado WHERE usuario = '$usuario' AND contraseña = '$contrasena'";

$resultado = mysqli_query($conn, $sql);

if (mysqli_num_rows($resultado) > 0) {

    $empleado = mysqli_fetch_assoc($resultado);

    $_SESSION['id_empleado'] = $empleado['id_empleado'];
    $_SESSION['usuario'] = $empleado['usuario'];

    header("Location: principal.php");
    exit();

} else {
    echo "Usuario o contraseña incorrectos";
}
}
?>