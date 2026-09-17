<?php
include_once("../includes/conexionBD.php");
$mensaje = "";
$roles = array();

$consulta_roles = mysqli_query($conexion, "SELECT id_rol, nombre FROM Rol ORDER BY nombre ASC");
if ($consulta_roles) {
    while ($rol = mysqli_fetch_assoc($consulta_roles)) {
        $roles[] = $rol;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $id_rol = filter_var($_POST["id_rol"] ?? null, FILTER_VALIDATE_INT);
    $rol_valido = false;

    foreach ($roles as $rol) {
        if ((int) $rol["id_rol"] === $id_rol) {
            $rol_valido = true;
            break;
        }
    }

    if ($nombre === "" || $apellido === "" || $usuario === "") {
        $mensaje = "El nombre, el apellido y el usuario son obligatorios.";
    } elseif (strlen($nombre) > 100 || strlen($apellido) > 100) {
        $mensaje = "El nombre y el apellido no pueden superar los 100 caracteres.";
    } elseif (strlen($usuario) > 50) {
        $mensaje = "El usuario no puede superar los 50 caracteres.";
    } elseif (strlen($contrasena) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (!$rol_valido) {
        $mensaje = "Debe seleccionar un tipo de empleado válido.";
    } else {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $sql = "INSERT INTO Empleado (nombre, apellido, usuario, contraseña, id_rol)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $apellido, $usuario, $hash, $id_rol);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
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
                   value="<?php echo htmlspecialchars($_POST["nombre"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

            <label for="apellido">Apellido *</label>
            <input type="text" id="apellido" name="apellido" maxlength="100" required
                   value="<?php echo htmlspecialchars($_POST["apellido"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

            <label for="usuario">Usuario *</label>
            <input type="text" id="usuario" name="usuario" maxlength="50" required
                   value="<?php echo htmlspecialchars($_POST["usuario"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

            <label for="contrasena">Contraseña *</label>
            <input type="password" id="contrasena" name="contrasena" minlength="8" required>

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
