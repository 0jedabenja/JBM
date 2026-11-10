<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
require_once __DIR__ . "/../includes/validacion.php";
verificar_sesion();
verificar_permiso("empleados");
$mensaje = "";
$roles = array();

$consulta_roles = mysqli_query($conexion, "SELECT id_rol, nombre FROM Rol ORDER BY nombre ASC");
if ($consulta_roles) {
    while ($rol = mysqli_fetch_assoc($consulta_roles)) {
        $roles[] = $rol;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = is_string($_POST["nombre"] ?? null) ? trim($_POST["nombre"]) : "";
    $apellido = is_string($_POST["apellido"] ?? null) ? trim($_POST["apellido"]) : "";
    $usuario = is_string($_POST["usuario"] ?? null) ? trim($_POST["usuario"]) : "";
    $correo = is_string($_POST["correo"] ?? null) ? strtolower(trim($_POST["correo"])) : "";
    $contrasena = $_POST["contrasena"] ?? "";
    $id_rol = validar_id_positivo($_POST["id_rol"] ?? null);
    $rol_valido = false;

    foreach ($roles as $rol) {
        if ((int) $rol["id_rol"] === $id_rol) {
            $rol_valido = true;
            break;
        }
    }

    if (!validar_texto($nombre, 100) || !validar_texto($apellido, 100) || !validar_texto($usuario, 50)
        || !validar_texto($correo, 255) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "El nombre, apellido, usuario o correo son inválidos o superan la longitud permitida.";
    } elseif (!validar_contrasena_nueva($contrasena)) {
        $mensaje = "La contraseña debe tener entre 8 y 72 bytes.";
    } elseif (!$rol_valido) {
        $mensaje = "Debe seleccionar un tipo de empleado válido.";
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

            error_log("Error MySQL INSERT empleado: " . mysqli_stmt_error($stmt));
            $mensaje = "No se pudo registrar el empleado. Verifique que el usuario no esté repetido.";
            mysqli_stmt_close($stmt);
        } else {
            error_log("Error MySQL al preparar alta de empleado: " . mysqli_error($conexion));
            $mensaje = "No se pudo preparar el registro del empleado.";
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
    <title>Cargar empleado</title>
</head>
<body>
    <main>
        <h1>Cargar empleado</h1>

        <?php if ($mensaje !== ""): ?>
            <p role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>

        <form action="cargar_empleados.php" method="POST">
            <label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" maxlength="100" required
                   value="<?php echo htmlspecialchars(is_string($_POST["nombre"] ?? null) ? $_POST["nombre"] : "", ENT_QUOTES, "UTF-8"); ?>">

            <label for="apellido">Apellido *</label>
            <input type="text" id="apellido" name="apellido" maxlength="100" required
                   value="<?php echo htmlspecialchars(is_string($_POST["apellido"] ?? null) ? $_POST["apellido"] : "", ENT_QUOTES, "UTF-8"); ?>">

            <label for="usuario">Usuario *</label>
            <input type="text" id="usuario" name="usuario" maxlength="50" required
                   value="<?php echo htmlspecialchars(is_string($_POST["usuario"] ?? null) ? $_POST["usuario"] : "", ENT_QUOTES, "UTF-8"); ?>">

            <label for="correo">Correo electrónico *</label>
            <input type="email" id="correo" name="correo" maxlength="255" required
                   value="<?php echo htmlspecialchars(is_string($_POST["correo"] ?? null) ? $_POST["correo"] : "", ENT_QUOTES, "UTF-8"); ?>">

            <label for="contrasena">Contraseña *</label>
            <input type="password" id="contrasena" name="contrasena" minlength="8" maxlength="72" required>

            <label for="id_rol">Tipo de empleado *</label>
            <select id="id_rol" name="id_rol" required>
                <option value="">Seleccione un tipo...</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?php echo (int) $rol["id_rol"]; ?>"
                        <?php echo ((int) $rol["id_rol"] === (int) ($_POST["id_rol"] ?? 0)) ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($rol["nombre"], ENT_QUOTES, "UTF-8"); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Guardar empleado</button>
            <a href="../empleados.php">Volver al listado</a>
        </form>
    </main>
</body>
</html>
