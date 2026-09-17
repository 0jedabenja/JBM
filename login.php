<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <link rel="stylesheet" href="assets/css/login.css">
    <script src="assets/js/login.js" defer></script>
</head>

<body>
    <div class="form-container">
        <form action="login.php" method="POST">
            <h2 class="form-title">Iniciar sesión</h2>
            <p class="form-description">Bienvenido, ingresa tus datos para acceder<br>a tu cuenta.</p>
            <div class="input-container">
                <input type="text" name="username" placeholder="Usuario" required>
                <img src="assets/img/login/light-user.svg" class="icon-user">
            </div>
            <div class="input-container">
                <input type="password" name="password" placeholder="Contraseña" required>
                <img src="assets/img/login/light-lock.svg" class="icon-lock">
                <img src="assets/img/login/light-eye.svg" class="icon-password">
            </div>
            <a href="" class="forgot-password">¿Olvidaste tu contraseña?</a>
            <button type="submit" class="btn-login">Iniciar sesión</button>
        </form>
    </div>
    <footer>
        <div class="footer-left">
            <p>&copy; 2026 JBM. Todos los derechos reservados.</p>
        </div>
        <div class="footer-right">
            <button type="button" class="about-us">Sobre nosotros</button>
            <button type="button" class="language">ES</button>
            <button type="button" class="theme">
                <img src="assets/img/login/dark-moon.svg" class="icon-theme">
            </button>
        </div>
    </footer>
    <?php include_once 'about-us.php'; ?>
</body>

</html>