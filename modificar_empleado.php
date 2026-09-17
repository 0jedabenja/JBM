<?php
include_once("includes/conexionBD.php");
include_once("includes/funciones.php");
verificar_permiso("empleados");
$id_empleado = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if ($id_empleado === false || $id_empleado <= 0) { http_response_code(400); exit("ID de empleado no válido."); }
$mensaje = "";
$roles = array();
$consulta = mysqli_query($conexion, "SELECT id_rol, nombre FROM Rol ORDER BY nombre");
while ($fila = mysqli_fetch_assoc($consulta)) $roles[] = $fila;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? ""); $apellido = trim($_POST["apellido"] ?? ""); $usuario = trim($_POST["usuario"] ?? ""); $contrasena = $_POST["contrasena"] ?? ""; $id_rol = filter_var($_POST["id_rol"] ?? null, FILTER_VALIDATE_INT);
    if ($nombre === "" || $apellido === "" || $usuario === "") $mensaje = "Nombre, apellido y usuario son obligatorios.";
    elseif (strlen($usuario) > 50 || $id_rol === false || $id_rol <= 0) $mensaje = "Los datos del empleado no son válidos.";
    elseif ($contrasena !== "" && strlen($contrasena) < 8) $mensaje = "La contraseña debe tener al menos 8 caracteres.";
    else {
        if ($contrasena !== "") {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conexion, "UPDATE Empleado SET nombre = ?, apellido = ?, usuario = ?, contraseña = ?, id_rol = ? WHERE id_empleado = ? AND activo = TRUE");
            mysqli_stmt_bind_param($stmt, "ssssii", $nombre, $apellido, $usuario, $hash, $id_rol, $id_empleado);
        } else {
            $stmt = mysqli_prepare($conexion, "UPDATE Empleado SET nombre = ?, apellido = ?, usuario = ?, id_rol = ? WHERE id_empleado = ? AND activo = TRUE");
            mysqli_stmt_bind_param($stmt, "sssii", $nombre, $apellido, $usuario, $id_rol, $id_empleado);
        }
        if (mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); header("Location: empleados.php"); exit(); }
        $mensaje = "No se pudo actualizar el empleado. Verifique que el usuario no esté repetido."; mysqli_stmt_close($stmt);
    }
}
$stmt = mysqli_prepare($conexion, "SELECT id_empleado, nombre, apellido, usuario, id_rol FROM Empleado WHERE id_empleado = ? AND activo = TRUE");
mysqli_stmt_bind_param($stmt, "i", $id_empleado); mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt); $empleado = mysqli_fetch_assoc($resultado); mysqli_stmt_close($stmt);
if (!$empleado) { http_response_code(404); exit("Empleado no encontrado."); }
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Modificar empleado</title><link rel="stylesheet" href="assets/css/alta.css"><script src="assets/js/sidebar.js" defer></script><style>.navigation ul li:nth-child(8){background:#fff}.navigation ul li:nth-child(8) a{color:#001f47}.navigation ul li:nth-child(8) a .icon img{content:url('assets/img/sidebar/theme-team.svg')}</style></head>
<body><?php include_once "includes/sidebar.php"; ?><div class="main"><div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once "includes/profile.php"; ?></div><main class="alta-content"><section class="alta-panel"><h1>Modificar empleado</h1><p>Actualiza los datos del empleado. Deja la contraseña vacía para conservarla.</p><?php if ($mensaje !== ""): ?><p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p><?php endif; ?><form class="alta-form" method="POST"><label for="nombre">Nombre *</label><input id="nombre" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars($empleado["nombre"], ENT_QUOTES, "UTF-8"); ?>"><label for="apellido">Apellido *</label><input id="apellido" name="apellido" maxlength="100" required value="<?php echo htmlspecialchars($empleado["apellido"], ENT_QUOTES, "UTF-8"); ?>"><label for="usuario">Usuario *</label><input id="usuario" name="usuario" maxlength="50" required value="<?php echo htmlspecialchars($empleado["usuario"], ENT_QUOTES, "UTF-8"); ?>"><label for="contrasena">Nueva contraseña</label><input id="contrasena" name="contrasena" type="password" minlength="8"><label for="id_rol">Rol *</label><select id="id_rol" name="id_rol" required><?php foreach ($roles as $rol): ?><option value="<?php echo (int)$rol["id_rol"]; ?>" <?php echo (int)$rol["id_rol"] === (int)$empleado["id_rol"] ? "selected" : ""; ?>><?php echo htmlspecialchars($rol["nombre"], ENT_QUOTES, "UTF-8"); ?></option><?php endforeach; ?></select><div class="alta-actions"><button type="submit">Guardar cambios</button><a href="empleados.php">Volver al listado</a></div></form></section></main></div></body></html>
