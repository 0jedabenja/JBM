<div class="menu">
    <div class="profile">
        <img src="">
    </div>

    <div class="dropdown">
        <h3><?php echo htmlspecialchars($_SESSION["nombre_usuario"] ?? "", ENT_QUOTES, "UTF-8"); ?></h3>

        <ul>
            <li>
                <a href="<?php echo $rutaBase ?? ""; ?>close.php">Cerrar sesión</a>
            </li>
        </ul>
    </div>
</div>