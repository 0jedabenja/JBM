<?php
include_once("includes/conexionBD.php");
include_once("includes/funciones.php");
require_once __DIR__ . "/includes/validacion.php";
verificar_permiso("clientes");
$id_cliente = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if ($id_cliente === false || $id_cliente <= 0) mostrar_pagina_error(400);
$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombreEntrada = $_POST["nombre"] ?? "";
    $apellidoEntrada = $_POST["apellido"] ?? "";
    $telefonoEntrada = $_POST["telefono"] ?? "";
    $direccionEntrada = $_POST["direccion"] ?? "";
    if (!validar_texto($nombreEntrada, 100) || !validar_texto($apellidoEntrada, 100)
        || !validar_texto($telefonoEntrada, 20, false) || !validar_texto($direccionEntrada, 255, false)) $mensaje = "Revise el nombre, apellido, teléfono y dirección; algunos datos son inválidos o superan la longitud permitida.";
    else {
        $nombre = trim($nombreEntrada); $apellido = trim($apellidoEntrada);
        $telefono = trim($telefonoEntrada); $direccion = trim($direccionEntrada);
        $stmt = mysqli_prepare($conexion, "UPDATE Cliente SET nombre = ?, apellido = ?, telefono = ?, direccion = ? WHERE id_cliente = ? AND activo = TRUE");
        mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $apellido, $telefono, $direccion, $id_cliente);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            registrar_auditoria($conexion, "modificar_cliente", "cliente", $id_cliente);
            header("Location: clientes.php");
            exit();
        }
        $mensaje = "No se pudo actualizar el cliente."; mysqli_stmt_close($stmt);
    }
}
$stmt = mysqli_prepare($conexion, "SELECT id_cliente, nombre, apellido, telefono, direccion FROM Cliente WHERE id_cliente = ? AND activo = TRUE");
mysqli_stmt_bind_param($stmt, "i", $id_cliente); mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt); $cliente = mysqli_fetch_assoc($resultado); mysqli_stmt_close($stmt);
if (!$cliente) mostrar_pagina_error(404);
?>
<!DOCTYPE html><html lang="es"><head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Modificar cliente</title><link rel="stylesheet" href="assets/css/alta.css"><script src="assets/js/sidebar.js" defer></script><link rel="stylesheet" href="assets/css/modificar_cliente.css"></head>
<body><?php include_once "includes/sidebar.php"; ?><div class="main"><div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once "includes/profile.php"; ?></div><main class="alta-content"><section class="alta-panel"><h1>Modificar cliente</h1><p>Actualiza los datos del cliente seleccionado.</p><?php if ($mensaje !== ""): ?><p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p><?php endif; ?><form class="alta-form" method="POST"><label for="nombre">Nombre *</label><input id="nombre" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars($cliente["nombre"], ENT_QUOTES, "UTF-8"); ?>"><label for="apellido">Apellido *</label><input id="apellido" name="apellido" maxlength="100" required value="<?php echo htmlspecialchars($cliente["apellido"], ENT_QUOTES, "UTF-8"); ?>"><label for="telefono">Teléfono</label><input id="telefono" name="telefono" maxlength="20" value="<?php echo htmlspecialchars($cliente["telefono"] ?? "", ENT_QUOTES, "UTF-8"); ?>"><label for="direccion">Dirección</label><input id="direccion" name="direccion" maxlength="255" value="<?php echo htmlspecialchars($cliente["direccion"] ?? "", ENT_QUOTES, "UTF-8"); ?>"><div class="alta-actions"><button type="submit">Guardar cambios</button><a href="clientes.php">Volver al listado</a></div></form></section></main></div></body></html>
