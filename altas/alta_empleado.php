<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion();
verificar_permiso("empleados");

$mensaje = "";
$rutaBase = "../";
$roles = array();
$consultaRoles = mysqli_query($conexion, "SELECT id_rol, nombre FROM Rol ORDER BY nombre ASC");
if ($consultaRoles) {
    while ($row = mysqli_fetch_assoc($consultaRoles)) {
        $roles[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $usuario = trim($_POST["usuario"] ?? "");
    $correo = strtolower(trim($_POST["correo"] ?? ""));
    $contrasena = $_POST["contrasena"] ?? "";
    $id_rol = filter_var($_POST["id_rol"] ?? null, FILTER_VALIDATE_INT);

    if ($nombre === "" || $apellido === "" || $usuario === "" || $correo === "") {
        $mensaje = "El nombre, el apellido, el usuario y el correo son obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Ingrese un correo electrónico válido.";
    } elseif (strlen($usuario) > 50) {
        $mensaje = "El usuario no puede superar los 50 caracteres.";
    } elseif (strlen($contrasena) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($id_rol === false || $id_rol <= 0) {
        $mensaje = "Debe seleccionar un rol válido.";
    } else {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $sql = "INSERT INTO Empleado (nombre, apellido, usuario, correo, contraseña, id_rol)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssssi", $nombre, $apellido, $usuario, $correo, $hash, $id_rol);
            if (mysqli_stmt_execute($stmt)) {
                $idEmpleadoNuevo = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt);
                registrar_auditoria($conexion, "crear_empleado", "empleado", $idEmpleadoNuevo, array("usuario" => $usuario, "id_rol" => $id_rol));
                header("Location: ../empleados.php");
                exit();
            }

            $mensaje = "No se pudo registrar el empleado. Verifique que el usuario no esté repetido.";
            error_log("Error MySQL INSERT empleado: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = "Error al preparar la consulta.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Empleado</title>
    <link rel="stylesheet" href="../assets/css/alta.css">
    <script src="../assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="../assets/css/alta_empleado.css">
</head>
<body>
    <?php include_once "../includes/sidebar.php"; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="../assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once "../includes/profile.php"; ?>
        </div>

        <main class="alta-content">
            <section class="alta-panel">
                <h1>Registrar nuevo empleado</h1>
                <p>Completa los datos y selecciona el rol del empleado.</p>
                <?php if ($mensaje !== ""): ?>
                    <p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p>
                <?php endif; ?>

    <form class="alta-form" action="alta_empleado.php" method="POST">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" maxlength="100" required
               value="<?php echo htmlspecialchars($_POST["nombre"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

        <label for="apellido">Apellido *</label>
        <input type="text" id="apellido" name="apellido" maxlength="100" required
               value="<?php echo htmlspecialchars($_POST["apellido"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

        <label for="usuario">Usuario *</label>
        <input type="text" id="usuario" name="usuario" maxlength="50" required
               value="<?php echo htmlspecialchars($_POST["usuario"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

        <label for="correo">Correo electrónico *</label>
        <input type="email" id="correo" name="correo" maxlength="255" required
               value="<?php echo htmlspecialchars($_POST["correo"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

        <label for="contrasena">Contraseña *</label>
        <input type="password" id="contrasena" name="contrasena" minlength="8" required>

        <label for="id_rol">Rol *</label>
        <select id="id_rol" name="id_rol" required>
            <option value="">Seleccione una opción...</option>
            <?php foreach ($roles as $rol): ?>
                <option value="<?php echo $rol["id_rol"]; ?>"
                    <?php echo ((int)($rol["id_rol"]) === (int)($_POST["id_rol"] ?? 0)) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($rol["nombre"], ENT_QUOTES, "UTF-8"); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="alta-actions">
            <button type="submit">Guardar empleado</button>
            <a href="../empleados.php">Volver al listado</a>
        </div>
    </form>
            </section>
        </main>
    </div>
</body>
</html>