<div class="menu">
    <div class="profile">
        <img src="./assets/img/usuario.png">
    </div>

    <div class="dropdown">
        <h3><?php echo htmlspecialchars($_SESSION["nombre_usuario"] ?? $_SESSION["usuario"] ?? "Usuario", ENT_QUOTES, "UTF-8"); ?></h3>

        <ul>
            <li>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar tema" aria-pressed="false">Tema: claro</button>
            </li>
            <li>
                <a href="<?php echo $rutaBase ?? ""; ?>close.php">Cerrar sesión</a>
            </li>
        </ul>
    </div>
</div>