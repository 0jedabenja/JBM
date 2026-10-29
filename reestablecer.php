<?php
require_once 'includes/conexionBD.php';
require_once 'includes/funciones.php';

$token = filter_var(trim($_GET['token'] ?? $_POST['token'] ?? ''), FILTER_SANITIZE_SPECIAL_CHARS);
$mensaje = '';
$tipo_alerta = '';
$exito = false;

if (empty($token)) {
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
            <a href="login.php" class="btn btn-login">Ir al Login</a>
        <?php endif; ?>
    </div>

</body>
</html>