<?php
require_once 'includes/conexionBD.php';
$mensaje = '';
$tipo_alerta = '';
$script_consola = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = strtolower(trim($_POST['correo'] ?? ''));

    if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $sql = "UPDATE Empleado 
                SET token_recuperacion = ?, token_expiracion = ? 
                WHERE correo = ? AND activo = TRUE";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $token, $expiracion, $correo);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $consultaUsuario = mysqli_prepare($conexion, "SELECT usuario FROM Empleado WHERE correo = ? AND activo = TRUE LIMIT 1");
                $usr = $correo;
                if ($consultaUsuario) {
                    mysqli_stmt_bind_param($consultaUsuario, "s", $correo);
                    mysqli_stmt_execute($consultaUsuario);
                    $resultadoUsuario = mysqli_stmt_get_result($consultaUsuario);
                    $empleado = $resultadoUsuario ? mysqli_fetch_assoc($resultadoUsuario) : null;
                    if ($empleado && isset($empleado['usuario'])) $usr = $empleado['usuario'];
                    mysqli_stmt_close($consultaUsuario);
                }

                $protocolo = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
                $host_actual = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
                if ($host_actual === '') $host_actual = 'localhost';
                $ruta_script = $_SERVER['PHP_SELF'] ?? '/recuperar.php';
                $url_base = rtrim(dirname($ruta_script), '/\\');
                if ($url_base === '.') $url_base = '';
                $enlace_recuperacion = "{$protocolo}://{$host_actual}{$url_base}/restablecer.php?token=" . urlencode($token);

                $cuerpo_mail = "=== SISTEMA LO DE TORRES ===\\n";
                $cuerpo_mail .= "Solicitud de restablecimiento de contraseña.\\n";
                $cuerpo_mail .= "Para el usuario: {$usr}\\n";
                $cuerpo_mail .= "Enlace seguro: {$enlace_recuperacion}\\n";
                $cuerpo_mail .= "Este enlace vencerá en 1 hora.";

                $script_consola = "<script>console.log(" . json_encode($cuerpo_mail) . ");</script>";
            }

            $mensaje = "Si el usuario ingresado existe en el sistema, se han enviado las instrucciones de recuperación.";
            $tipo_alerta = "exito";
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = "Ocurrió un error interno al procesar la solicitud.";
            $tipo_alerta = "error";
        }
    } else {
        $mensaje = "Por favor, ingresá un correo electrónico válido.";
        $tipo_alerta = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Lo de Torres</title>
    <link rel="stylesheet" href="assets/css/recuperacion.css">
    <link rel="stylesheet" href="assets/css/sin-seleccion.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/theme.js" defer></script>
</head>
<body>

    <div class="card">
        <h2>Lo de Torres</h2>
        <p class="subtitle">Recuperación de credenciales</p>

        <?php if (!empty($mensaje)): ?>
            <div class="alerta alerta-<?= $tipo_alerta === 'exito' ? 'exito' : 'error' ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form action="recuperar.php" method="POST">
            <div class="form-group">
                <label for="correo">Correo electrónico</label>
                                <input type="email" name="correo" id="correo" required autocomplete="email" placeholder="Ingresá tu correo">
            </div>
            <button type="submit" class="btn">Enviar Instrucciones</button>
        </form>

        <a href="login.php" class="nav-link">Volver al Inicio de Sesión</a>
    </div>

    <?= $script_consola ?>
</body>
</html>