<?php
include_once("includes/conexionBD.php");
include_once("includes/funciones.php");
verificar_permiso("clientes");
$id_cliente = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if ($id_cliente === false || $id_cliente <= 0) { http_response_code(400); exit("ID de cliente no válido."); }
$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");
    if ($nombre === "" || $apellido === "") $mensaje = "El nombre y el apellido son obligatorios.";
    elseif (strlen($telefono) > 20 || strlen($direccion) > 255) $mensaje = "El teléfono o la dirección superan la longitud permitida.";
    else {
        $stmt = mysqli_prepare($conexion, "UPDATE Cliente SET nombre = ?, apellido = ?, telefono = ?, direccion = ? WHERE id_cliente = ? AND activo = TRUE");
        mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $apellido, $telefono, $direccion, $id_cliente);
        if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); header("Location: clientes.php"); exit(); }
        $mensaje = "No se pudo actualizar el cliente."; mysqli_stmt_close($stmt);
    }
}
$stmt = mysqli_prepare($conexion, "SELECT id_cliente, nombre, apellido, telefono, direccion FROM Cliente WHERE id_cliente = ? AND activo = TRUE");
mysqli_stmt_bind_param($stmt, "i", $id_cliente); mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt); $cliente = mysqli_fetch_assoc($resultado); mysqli_stmt_close($stmt);
if (!$cliente) { http_response_code(404); exit("Cliente no encontrado."); }
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Modificar cliente</title><link rel="stylesheet" href="assets/css/alta.css"><script src="assets/js/sidebar.js" defer></script><style>.navigation ul li:nth-child(5){background:#fff}.navigation ul li:nth-child(5) a{color:#001f47}.navigation ul li:nth-child(5) a .icon img{content:url('assets/img/sidebar/theme-person.svg')}</style></head>
<body><?php include_once "includes/sidebar.php"; ?><div class="main"><div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once "includes/profile.php"; ?></div><main class="alta-content"><section class="alta-panel"><h1>Modificar cliente</h1><p>Actualiza los datos del cliente seleccionado.</p><?php if ($mensaje !== ""): ?><p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p><?php endif; ?><form class="alta-form" method="POST"><label for="nombre">Nombre *</label><input id="nombre" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars($cliente["nombre"], ENT_QUOTES, "UTF-8"); ?>"><label for="apellido">Apellido *</label><input id="apellido" name="apellido" maxlength="100" required value="<?php echo htmlspecialchars($cliente["apellido"], ENT_QUOTES, "UTF-8"); ?>"><label for="telefono">Teléfono</label><input id="telefono" name="telefono" maxlength="20" value="<?php echo htmlspecialchars($cliente["telefono"] ?? "", ENT_QUOTES, "UTF-8"); ?>"><label for="direccion">Dirección</label><input id="direccion" name="direccion" maxlength="255" value="<?php echo htmlspecialchars($cliente["direccion"] ?? "", ENT_QUOTES, "UTF-8"); ?>"><div class="alta-actions"><button type="submit">Guardar cambios</button><a href="clientes.php">Volver al listado</a></div></form></section></main></div></body></html>
