<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="form-container">
        <form action="login.php" method="POST">
            <h2 class="title">Inicio de sesión</h2>
            <p class="description">Bienvenido, ingresa tus datos para acceder<br>a tu cuenta.</p>
            <div class="input-container">
                <input type="text" placeholder="Usuario" name="usuario">
                <img src="icons/light-user.svg" class="icon-left" id="userIcon">
            </div>
            <div class="input-container">
                <input type="password" placeholder="Contraseña" name="contrasena" id="password">
                <img src="icons/light-lock.svg" class="icon-left" id="lockIcon">
                <img src="icons/light-eye.svg" class="icon-right" id="togglePassword">
            </div>
            <a href="recuperar.php" class="help-link">¿Olvidaste tu contraseña?</a>
            <button type="submit" class="btn">Iniciar sesión</button>
        </form>
    </div>

    <footer>
        <div class="footer-left">
            <p>© 2026 JBM. Todos los derechos reservados.</p>
        </div>
        <div class="footer-right">
            <div class="about-us" id="openPopup">Sobre nosotros</div>
            <div class="language">ES</div>
            <img src="icons/sun.svg" class="theme" id="themeToggle">
        </div>

        <div id="overlay-background">
            <div class="popup-box">

                <h2 class="popup-title">Sobre nosotros</h2>

                <p class="company-description">
                    Somos un equipo apasionado por la tecnología y la innovación, enfocado en desarrollar soluciones
                    de software eficientes y a la medida de nuestros clientes.
                </p>

                <div class="people-list">
                    <div class="person">
                        <img src="" class="avatar" alt="Avatar">
                        <div class="person-info">
                            <strong>Benjamín Ojeda</strong>
                            <span class="person-role"><u>Frontend Developer</u></span>
                            <a href="https://github.com/" target="_blank" class="github-btn" title="Ver GitHub">
                                <img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png"
                                    alt="Logo de GitHub" width="20">
                            </a>
                        </div>
                    </div>

                    <div class="person">
                        <img src="" class="avatar" alt="Avatar">
                        <div class="person-info">
                            <strong>Juan Leites</strong>
                            <span class="person-role"><u>Backend Developer</u></span>
                            <a href="https://github.com/" target="_blank" class="github-btn" title="Ver GitHub">
                                <img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png"
                                    alt="Logo de GitHub" width="20">
                            </a>
                        </div>
                    </div>

                    <div class="person">
                        <img src="" class="avatar" alt="Avatar">
                        <div class="person-info">
                            <strong>Mateo Dutra</strong>
                            <span class="person-role"><u>Administrator</u></span>
                            <a href="https://github.com/" target="_blank" class="github-btn" title="Ver GitHub">
                                <img src="https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png"
                                    alt="Logo de GitHub" width="20">
                            </a>
                        </div>
                    </div>
            </div>
        </div>
        </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>

</html>