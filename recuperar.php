<?php
require_once 'includes/coneccionBD.php';
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Lo de Torres</title>
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