<?php
require_once __DIR__ . "/vendor/autoload.php";

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\VerifySolutionOptions;

include_once "includes/conexionBD.php";
include_once "includes/funciones.php";

iniciar_sesion_segura();
$mensaje = "";
$captchaSecret = $_SESSION["altcha_secret"] ??= bin2hex(random_bytes(32));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["username"] ?? "");
    $contrasena = $_POST["password"] ?? "";
    $token_csrf = $_POST["csrf_token"] ?? "";
    $captchaValido = false;

    if (validar_token_csrf($token_csrf)) {
        $payloadCaptcha = $_POST["altcha"] ?? "";
        if (is_string($payloadCaptcha) && $payloadCaptcha !== "") {
            try {
                $altcha = new Altcha(hmacSignatureSecret: $captchaSecret);
                $resultadoCaptcha = $altcha->verifySolution(new VerifySolutionOptions(
                    payload: $payloadCaptcha,
                    algorithm: new Pbkdf2()
                ));
                $captchaValido = $resultadoCaptcha->verified;
            } catch (InvalidArgumentException $error) {
                $captchaValido = false;
            }
        }
    }
    $_SESSION["altcha_secret"] = bin2hex(random_bytes(32));

    if (!validar_token_csrf($token_csrf)) {
        $mensaje = "La solicitud no es válida. Intente nuevamente.";
    } elseif (!$captchaValido) {
        $mensaje = "Complete la verificación anti-robots e intente nuevamente.";
    } elseif ($usuario === "" || $contrasena === "") {
        $mensaje = "Ingrese su usuario y contraseña.";
    } elseif (strlen($usuario) > 50) {
        $mensaje = "El usuario no es válido.";
    } else {
        $sql = "SELECT e.id_empleado, e.nombre, e.apellido, e.usuario, e.contraseña, e.id_rol,
                   r.nombre AS rol
            FROM Empleado e
            INNER JOIN Rol r ON r.id_rol = e.id_rol
            WHERE e.usuario = ? AND e.activo = 1
                LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $usuario);
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);
            $empleado = mysqli_fetch_assoc($resultado);
            mysqli_stmt_close($stmt);

            if ($empleado && password_verify($contrasena, $empleado["contraseña"])) {
                session_regenerate_id(true);
                $_SESSION["usuario_id"] = (int) $empleado["id_empleado"];
                $_SESSION["usuario"] = $empleado["usuario"];
                $_SESSION["nombre_usuario"] = $empleado["nombre"] . " " . $empleado["apellido"];
                $_SESSION["id_rol"] = (int) $empleado["id_rol"];
                $_SESSION["rol"] = $empleado["rol"];
                registrar_auditoria($conexion, "iniciar_sesion", "empleado", (int)$empleado["id_empleado"]);
                header("Location: index.php");
                exit();
            }

            $mensaje = "Usuario o contraseña incorrectos.";
        } else {
            error_log("Error MySQL al preparar login: " . mysqli_error($conexion));
            $mensaje = "No se pudo procesar el inicio de sesión.";
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
    <title></title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/sin-seleccion.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <script src="assets/js/theme.js" defer></script>
    <script src="https://cdn.jsdelivr.net/gh/altcha-org/altcha/dist/main/altcha.min.js" type="module"></script>
    <script src="assets/js/login.js" defer></script>
</head>

<body>
    <div class="form-container">
        <form action="login.php" method="POST">
            <h2 class="form-title">Iniciar sesión</h2>
            <p class="form-description">Bienvenido, ingresa tus datos para acceder<br>a tu cuenta.</p>
            <?php if ($mensaje !== ""): ?>
            <p class="form-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars(obtener_token_csrf(), ENT_QUOTES, "UTF-8"); ?>">
            <div class="input-container">
                <input type="text" name="username" placeholder="Usuario" maxlength="50" required
                    value="<?php echo htmlspecialchars($_POST["username"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                <img src="assets/img/login/light-user.svg" class="icon-user">
            </div>
            <div class="input-container">
                <input type="password" name="password" placeholder="Contraseña" required>
                <img src="assets/img/login/light-lock.svg" class="icon-lock">
                <img src="assets/img/login/light-eye.svg" class="icon-password">
            </div>
            <altcha-widget challenge="apis/altcha_challenge.php" language="es"></altcha-widget>
            <a href="recuperar.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
            <button type="submit" class="btn-login">Iniciar sesión</button>
        </form>
    </div>
    <footer>
        <div class="footer-left">
            <p>&copy; 2026 JBM. Todos los derechos reservados.</p>
        </div>
        <div class="footer-right">
            <div class="about-us" id="openPopup">Sobre nosotros</div>
            <div class="language">ES</div>
            <img src="assets/img/login/light-sun.svg" class="icon-theme">
        </div>
    </footer>
    <?php include_once "about-us.php"; ?>
</body>

</html>