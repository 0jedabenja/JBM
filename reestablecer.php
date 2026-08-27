<?php
require_once 'includes/coneccionBD.php';
$token = filter_var(trim($_GET['token'] ?? $_POST['token'] ?? ''), FILTER_SANITIZE_SPECIAL_CHARS);
$mensaje = '';
$tipo_alerta = '';
$exito = false;

if (empty($token)) {
    exit('Acceso denegado: Token de seguridad no proporcionado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';

    if (strlen($nueva_password) < 8) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres.";
        $tipo_alerta = "error";
    } elseif ($nueva_password !== $confirmar_password) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_alerta = "error";
    } else {
        $sql_validar = "SELECT id_empleado FROM Empleado 
                        WHERE token_recuperacion = ? 
                        AND token_expiracion > NOW()";
                        
        $stmt = mysqli_prepare($conexion, $sql_validar);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $token);
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);
            $empleado = mysqli_fetch_assoc($resultado);
            mysqli_stmt_close($stmt);

            if ($empleado) {
                $password_hash = password_hash($nueva_password, PASSWORD_BCRYPT);
                $id_emp = $empleado['id_empleado'];

                $sql_update = "UPDATE Empleado 
                               SET contraseña = ?, token_recuperacion = NULL, token_expiracion = NULL 
                               WHERE id_empleado = ?";
                               
                $stmt_up = mysqli_prepare($conexion, $sql_update);
                
                if ($stmt_up) {
                    mysqli_stmt_bind_param($stmt_up, "si", $password_hash, $id_emp);
                    if (mysqli_stmt_execute($stmt_up)) {
                        $mensaje = "¡Contraseña actualizada con éxito!";
                        $tipo_alerta = "exito";
                        $exito = true;
                    } else {
                        $mensaje = "Ocurrió un error al intentar actualizar la clave.";
                        $tipo_alerta = "error";
                    }
                    mysqli_stmt_close($stmt_up);
                }
            } else {
                $mensaje = "El enlace es inválido o su tiempo de validez expiró.";
                $tipo_alerta = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Lo de Torres</title>
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
        <p class="subtitle">Establecer nueva contraseña</p>

        <?php if (!empty($mensaje)): ?>
            <div class="alerta alerta-<?= $tipo_alerta === 'exito' ? 'exito' : 'error' ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if (!$exito): ?>
            <form action="restablecer.php" method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label for="nueva_password">Nueva Contraseña</label>
                    <input type="password" name="nueva_password" id="nueva_password" required minlength="8" placeholder="Mínimo 8 caracteres">
                </div>

                <div class="form-group">
                    <label for="confirmar_password">Confirmar Contraseña</label>
                    <input type="password" name="confirmar_password" id="confirmar_password" required minlength="8" placeholder="Repetí la contraseña">
                </div>

                <button type="submit" class="btn">Actualizar Clave</button>
            </form>
        <?php else: ?>
            <a href="login.php" class="btn" style="display: inline-block; text-align: center; text-decoration: none;">Ir al Login</a>
        <?php endif; ?>
    </div>

</body>
</html>