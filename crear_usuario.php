<?php
include_once ("includes/coneccionBD.php");
include_once ("includes/funciones.php");

$mensaje = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $contraseña = $_POST['contraseña'] ?? '';
    $id_rol = filter_var($_POST['id_rol'] ?? null, FILTER_VALIDATE_INT);

    if (
        !empty($nombre) &&
        preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $nombre) &&
        !empty($apellido) &&
        preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $apellido) &&
        !empty($usuario) &&
        !empty($contraseña) &&
        $id_rol !== false &&
        $id_rol !== null
    ) {
        $contraseña_hash = password_hash($contraseña, PASSWORD_DEFAULT);

        $sql = "INSERT INTO empleado 
                (nombre, apellido, usuario, contraseña, id_rol)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "ssssi",
                $nombre,
                $apellido,
                $usuario,
                $contraseña_hash,
                $id_rol
            );

            if (mysqli_stmt_execute($stmt)) {
                $mensaje = 'Empleado creado correctamente.';
                $tipo_alerta = 'exito';
            } else {
                if (mysqli_errno($conexion) === 1062) {
                    $mensaje = 'Error: el nombre de usuario ya está en uso.';
                    $tipo_alerta = 'error';
                } else {
                    $mensaje = 'Error al crear el empleado. Intente nuevamente.';
                    $tipo_alerta = 'error';
                    error_log(mysqli_error($conexion));
                }
            }

            mysqli_stmt_close($stmt);
        } else {
            $mensaje = 'Error en el sistema. Intente más tarde.';
            $tipo_alerta = 'error';
            error_log(mysqli_error($conexion));
        }
    } else {
        $mensaje = 'Por favor, complete todos los campos.';
        $tipo_alerta = 'error';
    }
}

$sql_roles = "SELECT id_rol, nombre FROM rol ORDER BY nombre";
$resultado_roles = mysqli_query($conexion, $sql_roles);

$roles = [];

if ($resultado_roles) {
    while ($row = mysqli_fetch_assoc($resultado_roles)) {
        $roles[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Lo de Torres</title>
    <style>
        :root {
            --primary: #2c3e50;
            --accent: #27ae60;
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333333;
            --error: #e74c3c;
            --border: #dcdde1;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--bg); display: flex; align-items: center; justify-content: center; min-height: 100vh; color: var(--text); }
        .card { background: var(--card-bg); width: 100%; max-width: 400px; padding: 2.5rem; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-top: 5px solid var(--primary); }
        .card h2 { color: var(--primary); font-size: 1.5rem; margin-bottom: 0.5rem; text-align: center; }
        .card p.subtitle { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 1.5rem; text-align: center; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; color: var(--primary); }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.95rem; outline: none; transition: border 0.2s; }
        .form-group input:focus { border-color: var(--primary); }
        .btn { width: 100%; background: var(--primary); color: #fff; padding: 0.75rem; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #1a252f; }
        .alerta { padding: 0.8rem; border-radius: 4px; font-size: 0.85rem; margin-bottom: 1.2rem; text-align: center; }
        .alerta-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-link { display: block; text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--primary); text-decoration: none; }
        .nav-link:hover { text-decoration: underline; }
    </style>
</head>

<body>
    <div class="card">
        <h2>Lo de Torres</h2>
        <p class="subtitle">Crear nuevo usuario</p>

        <?php if (!empty($mensaje)): ?>
            <div class="alerta alerta-<?= $tipo_alerta === 'exito' ? 'exito' : 'error' ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form action="crear_usuario.php" method="POST">
            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input
                    type="text"
                    name="nombre"
                    id="nombre"
                    maxlength="100"
                    required
                    autocomplete="off"
                    placeholder="Ingresá el nombre"
                >
            </div>

            <div class="form-group">
                <label for="apellido">Apellido</label>
                <input
                    type="text"
                    name="apellido"
                    id="apellido"
                    maxlength="100"
                    required
                    autocomplete="off"
                    placeholder="Ingresá el apellido"
                >
            </div>

            <div class="form-group">
                <label for="usuario">Nombre de Usuario</label>
                <input
                    type="text"
                    name="usuario"
                    id="usuario"
                    maxlength="50"
                    required
                    autocomplete="off"
                    placeholder="Ingresá el usuario"
                >
            </div>

            <div class="form-group">
                <label for="contraseña">Contraseña</label>
                <input
                    type="password"
                    name="contraseña"
                    id="contraseña"
                    required
                    placeholder="Ingresá la contraseña"
                >
            </div>

            <div class="form-group">
                <label for="id_rol">Rol</label>
                <select name="id_rol" id="id_rol" required>
                    <option value="">Seleccioná un rol</option>

                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= htmlspecialchars($rol['id_rol']) ?>">
                            <?= htmlspecialchars($rol['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">Crear Usuario</button>

            <a href="empleados.php" class="btn">Volver</a>
        </form>
    </div>
</body>
</html>
