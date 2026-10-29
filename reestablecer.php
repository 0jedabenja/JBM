<?php
require_once __DIR__ . '/includes/conexionBD.php';
require_once __DIR__ . '/includes/funciones.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mensaje = '';
$tipo_alerta = '';
$exito = false;

if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    mostrar_pagina_error(400);
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
        $token_valido = false;
        $token_hash = hash('sha256', $token);
        $sql_validar = "SELECT id_empleado FROM Empleado
                        WHERE token_recuperacion = ?
                        AND token_expiracion > NOW()
                        AND activo = TRUE
                        LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql_validar);

        if (!$stmt) {
            error_log('Restablecimiento de contraseña: no se pudo preparar la validación del token.');
            $mensaje = "Ocurrió un error interno al validar el enlace.";
            $tipo_alerta = "error";
        } else {
            mysqli_stmt_bind_param($stmt, 's', $token_hash);
            if (!mysqli_stmt_execute($stmt)) {
                error_log('Restablecimiento de contraseña: no se pudo validar el token.');
                $mensaje = "Ocurrió un error interno al validar el enlace.";
                $tipo_alerta = "error";
            } else {
                mysqli_stmt_bind_result($stmt, $id_emp);
                $token_valido = mysqli_stmt_fetch($stmt);
                if ($token_valido === false && mysqli_stmt_errno($stmt) !== 0) {
                    error_log('Restablecimiento de contraseña: no se pudo leer el resultado de la validación.');
                    $mensaje = "Ocurrió un error interno al validar el enlace.";
                    $tipo_alerta = "error";
                }
            }
            mysqli_stmt_close($stmt);

            if (!empty($token_valido)) {
                $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE Empleado 
                               SET contraseña = ?, token_recuperacion = NULL, token_expiracion = NULL
                               WHERE id_empleado = ? AND token_recuperacion = ? AND token_expiracion > NOW() AND activo = TRUE";
                $stmt_up = mysqli_prepare($conexion, $sql_update);

                if (!$stmt_up) {
                    error_log('Restablecimiento de contraseña: no se pudo preparar la actualización.');
                    $mensaje = "Ocurrió un error al intentar actualizar la clave.";
                    $tipo_alerta = "error";
                } else {
                    mysqli_stmt_bind_param($stmt_up, 'sis', $password_hash, $id_emp, $token_hash);
                    if (mysqli_stmt_execute($stmt_up) && mysqli_stmt_affected_rows($stmt_up) > 0) {
                        $mensaje = "¡Contraseña actualizada con éxito!";
                        $tipo_alerta = "exito";
                        $exito = true;
                    } else {
                        if (mysqli_stmt_error($stmt_up) !== '') {
                            error_log('Restablecimiento de contraseña: no se pudo actualizar la clave.');
                            $mensaje = "Ocurrió un error al intentar actualizar la clave.";
                            $tipo_alerta = "error";
                        } else {
                            $mensaje = "El enlace es inválido o su tiempo de validez expiró.";
                            $tipo_alerta = "error";
                        }
                    }
                    mysqli_stmt_close($stmt_up);
                }
            } elseif ($token_valido !== false && $mensaje === '') {
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
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Lo de Torres</title>
    <link rel="stylesheet" href="assets/css/recuperacion.css">
    <link rel="stylesheet" href="assets/css/reestablecer.css">
    <link rel="stylesheet" href="assets/css/sin-seleccion.css">
    <link rel="stylesheet" href="assets/css/theme.css?v=<?php echo filemtime(__DIR__ . "/assets/css/theme.css"); ?>">
    <script src="assets/js/theme.js" defer></script>
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
            <form action="reestablecer.php" method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                
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
            <a href="login.php" class="btn btn-login">Ir al Login</a>
        <?php endif; ?>
    </div>

</body>
</html>