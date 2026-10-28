<?php
require_once 'includes/conexionBD.php';
$mensaje = '';
$tipo_alerta = '';
$script_consola = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usr = filter_var(trim($_POST['usuario'] ?? ''), FILTER_SANITIZE_SPECIAL_CHARS);

    if (!empty($usr)) {
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $sql = "UPDATE Empleado 
                SET token_recuperacion = ?, token_expiracion = ? 
                WHERE usuario = ?";
                
        $stmt = mysqli_prepare($conexion, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $token, $expiracion, $usr);
            mysqli_stmt_execute($stmt);
            
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host_actual = $_SERVER['HTTP_HOST'];
                $url_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
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
        $mensaje = "Por favor, ingresá tu nombre de usuario.";
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
                <label for="usuario">Nombre de Usuario</label>
                <input type="text" name="usuario" id="usuario" required autocomplete="off" placeholder="Ingresá tu usuario">
            </div>
            <button type="submit" class="btn">Enviar Instrucciones</button>
        </form>

        <a href="login.php" class="nav-link">Volver al Inicio de Sesión</a>
    </div>

    <?= $script_consola ?>
</body>
</html>