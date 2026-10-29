<?php

$mensaje = '';
$tipo_alerta = '';
$error_recuperacion = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = strtolower(trim($_POST['correo'] ?? ''));

    if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/librerias/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/librerias/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/includes/conexionBD.php';

        $correo_remitente = 'contacto@lodetorres.shop';
        $url_base = 'https://lodetorres.shop';

        if (mysqli_connect_errno()) {
            $error_recuperacion = true;
        } else {
            $sql = "SELECT id_empleado FROM Empleado WHERE correo = ? AND activo = TRUE LIMIT 1";
            $stmt = mysqli_prepare($conexion, $sql);

            if (!$stmt) {
                error_log('Recuperación de contraseña: no se pudo preparar la búsqueda del empleado: ' . mysqli_error($conexion));
                $error_recuperacion = true;
            } else {
                mysqli_stmt_bind_param($stmt, 's', $correo);
                if (!mysqli_stmt_execute($stmt)) {
                    error_log('Recuperación de contraseña: no se pudo buscar el empleado: ' . mysqli_stmt_error($stmt));
                    $error_recuperacion = true;
                } else {
                    mysqli_stmt_bind_result($stmt, $empleado_id);
                    $empleado_encontrado = mysqli_stmt_fetch($stmt);
                    if ($empleado_encontrado === false && mysqli_stmt_errno($stmt) !== 0) {
                        error_log('Recuperación de contraseña: no se pudo leer el resultado de la búsqueda: ' . mysqli_stmt_error($stmt));
                        $error_recuperacion = true;
                    }

                    if ($empleado_encontrado === true) {
                        $token = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $token);
                        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        $actualizar = mysqli_prepare(
                            $conexion,
                            "UPDATE Empleado SET token_recuperacion = ?, token_expiracion = ? WHERE id_empleado = ? AND activo = TRUE"
                        );

                        if (!$actualizar) {
                            error_log('Recuperación de contraseña: no se pudo preparar la actualización del token: ' . mysqli_error($conexion));
                            $error_recuperacion = true;
                        } else {
                            mysqli_stmt_bind_param($actualizar, 'ssi', $token_hash, $expiracion, $empleado_id);
                            if (!mysqli_stmt_execute($actualizar)) {
                                error_log('Recuperación de contraseña: no se pudo guardar el token: ' . mysqli_stmt_error($actualizar));
                                $error_recuperacion = true;
                            } elseif (mysqli_stmt_affected_rows($actualizar) > 0) {
                                $enlace_recuperacion = $url_base . '/reestablecer.php?token=' . urlencode($token);
                                $enlace_html = htmlspecialchars($enlace_recuperacion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                $cuerpo_texto = "LO DE TORRES\n";
                                $cuerpo_texto .= "Restablecimiento de contraseña\n\n";
                                $cuerpo_texto .= "Recibimos una solicitud para restablecer la contraseña de tu cuenta.\n\n";
                                $cuerpo_texto .= "Para continuar, abrí este enlace:\n{$enlace_recuperacion}\n\n";
                                $cuerpo_texto .= "Por seguridad, el enlace es de un solo uso y vence en una hora.\n\n";
                                $cuerpo_texto .= "AVISO DE CONFIDENCIALIDAD\n";
                                $cuerpo_texto .= "No compartas este enlace ni el código de recuperación con nadie. Si no solicitaste este cambio, no abras el enlace ni compartas el mensaje; podés ignorar este correo. Tu contraseña actual no cambiará.\n\n";
                                $cuerpo_texto .= "Atentamente,\nEl equipo de Lo de Torres";

                                $cuerpo_html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Restablecimiento de contraseña</title></head>';
                                $cuerpo_html .= '<body style="margin:0;padding:32px 16px;background-color:#f3f5f8;font-family:Arial,Helvetica,sans-serif;color:#243247;">';
                                $cuerpo_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:0 auto;background-color:#ffffff;border:1px solid #e4e9f0;border-radius:12px;overflow:hidden;">';
                                $cuerpo_html .= '<tr><td style="padding:28px 36px;background-color:#173b63;color:#ffffff;"><div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:.85;">Lo de Torres</div><div style="margin-top:8px;font-size:23px;font-weight:700;">Restablecimiento de contraseña</div></td></tr>';
                                $cuerpo_html .= '<tr><td style="padding:32px 36px 12px;font-size:15px;line-height:1.7;"><p style="margin:0 0 16px;">Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p><p style="margin:0 0 24px;">Para continuar, usá el siguiente botón. Por seguridad, el enlace es de un solo uso y vence en una hora.</p>';
                                $cuerpo_html .= '<p style="margin:0 0 28px;"><a href="' . $enlace_html . '" style="display:inline-block;padding:13px 22px;background-color:#1d5b91;border-radius:7px;color:#ffffff;text-decoration:none;font-weight:700;">Restablecer contraseña</a></p>';
                                $cuerpo_html .= '<p style="margin:0 0 8px;color:#66758a;font-size:13px;">Si el botón no funciona, copiá y pegá este enlace en tu navegador:</p><p style="margin:0 0 24px;word-break:break-all;font-size:13px;"><a href="' . $enlace_html . '" style="color:#1d5b91;">' . $enlace_html . '</a></p>';
                                $cuerpo_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#fff8e8;border-left:4px solid #d5a23b;border-radius:4px;"><tr><td style="padding:16px 18px;font-size:13px;line-height:1.6;color:#5a4a2d;"><strong>Aviso de confidencialidad</strong><br>No compartas este enlace ni el código de recuperación con nadie. Si no solicitaste este cambio, no abras el enlace ni compartas este mensaje; podés ignorar este correo. Tu contraseña actual no cambiará.</td></tr></table>';
                                $cuerpo_html .= '<p style="margin:28px 0 0;">Atentamente,<br><strong>El equipo de Lo de Torres</strong></p></td></tr>';
                                $cuerpo_html .= '<tr><td style="padding:18px 36px;background-color:#f7f9fb;color:#7a8798;font-size:12px;line-height:1.5;">Este es un mensaje automático relacionado con la seguridad de tu cuenta. Por favor, no respondas a este correo.</td></tr></table></body></html>';

                                try {
                                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                                    $mail->isMail();
                                    $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
                                    $mail->setFrom($correo_remitente, 'Lo de Torres');
                                    $mail->addAddress($correo);
                                    $mail->isHTML(true);
                                    $mail->Subject = 'Restablecimiento de contraseña - Lo de Torres';
                                    $mail->Body = $cuerpo_html;
                                    $mail->AltBody = $cuerpo_texto;
                                    $mail->send();
                                } catch (\PHPMailer\PHPMailer\Exception $error) {
                                    error_log('Recuperación de contraseña: PHPMailer no pudo enviar el mensaje: ' . $error->getMessage());
                                    $error_recuperacion = true;
                                    $invalidar = mysqli_prepare(
                                        $conexion,
                                        "UPDATE Empleado SET token_recuperacion = NULL, token_expiracion = NULL WHERE id_empleado = ? AND token_recuperacion = ?"
                                    );
                                    if ($invalidar) {
                                        mysqli_stmt_bind_param($invalidar, 'is', $empleado_id, $token_hash);
                                        if (!mysqli_stmt_execute($invalidar)) {
                                            error_log('Recuperación de contraseña: no se pudo invalidar el token tras fallar el envío: ' . mysqli_stmt_error($invalidar));
                                            $error_recuperacion = true;
                                        }
                                        mysqli_stmt_close($invalidar);
                                    } else {
                                        error_log('Recuperación de contraseña: no se pudo preparar la invalidación del token: ' . mysqli_error($conexion));
                                        $error_recuperacion = true;
                                    }
                                }
                            } else {
                                error_log('Recuperación de contraseña: no se actualizó ningún empleado activo.');
                                $error_recuperacion = true;
                            }
                            mysqli_stmt_close($actualizar);
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($error_recuperacion) {
            http_response_code(503);
            $mensaje = "No se pudo procesar la solicitud en este momento. Intentalo más tarde o contactá al administrador del sistema.";
            $tipo_alerta = 'error';
        } else {
            $mensaje = "Si el correo está registrado, recibirás las instrucciones de recuperación.";
            $tipo_alerta = 'exito';
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
    <link rel="stylesheet" href="assets/css/theme.css?v=<?php echo filemtime(__DIR__ . "/assets/css/theme.css"); ?>">
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
</body>
</html>