<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($rutaBase ?? "", ENT_QUOTES, "UTF-8"); ?>assets/css/sidebar.css">
</head>
<body>
    <div class="container">
        <div class="navigation">
            <ul>
                <?php if (usuario_tiene_permiso("inicio")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>index.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-home.svg"></span>
                        <span class="title">Inicio</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("pedidos")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>pedidos.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-restaurant.svg"></span>
                        <span class="title">Pedidos</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("mapa")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>mapa.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-map.svg"></span>
                        <span class="title">Mapa</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("caja")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>caja.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-payments.svg"></span>
                        <span class="title">Caja</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("clientes")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>clientes.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-person.svg"></span>
                        <span class="title">Clientes</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("insumos")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>insumos.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-box.svg"></span>
                        <span class="title">Insumos</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("productos")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>productos.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-shopping.svg"></span>
                        <span class="title">Productos</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("empleados")): ?>
                <li>
                    <a href="<?php echo $rutaBase ?? ""; ?>empleados.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-team.svg"></span>
                        <span class="title">Empleados</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>
</html>