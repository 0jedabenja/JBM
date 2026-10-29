<?php
$rutaBase = isset($rutaBase) ? $rutaBase : "";
$archivoActual = strtolower(basename($_SERVER["SCRIPT_NAME"] ?? ""));
$seccionesSidebar = array(
    "index.php" => "inicio",
    "pedidos.php" => "pedidos",
    "mapa.php" => "mapa",
    "caja.php" => "caja",
    "clientes.php" => "clientes",
    "alta_cliente.php" => "clientes",
    "modificar_cliente.php" => "clientes",
    "insumos.php" => "insumos",
    "alta_insumo.php" => "insumos",
    "modificar_insumo.php" => "insumos",
    "productos.php" => "productos",
    "alta_producto.php" => "productos",
    "modificar_producto.php" => "productos",
    "empleados.php" => "empleados",
    "alta_empleado.php" => "empleados",
    "modificar_empleado.php" => "empleados",
    "conteos.php" => "conteos",
    "movimientos.php" => (isset($_GET["tipo"]) && strtolower((string) $_GET["tipo"]) === "producto") ? "productos" : "insumos",
    "auditoria.php" => "auditoria",
    "compras.php" => "compras"
);
$seccionActual = $seccionesSidebar[$archivoActual] ?? "";
$iconosFavicon = array(
    "inicio" => "light-home.svg",
    "pedidos" => "light-restaurant.svg",
    "mapa" => "light-map.svg",
    "caja" => "light-payments.svg",
    "clientes" => "light-person.svg",
    "insumos" => "light-box.svg",
    "productos" => "light-shopping.svg",
    "empleados" => "light-team.svg",
    "conteos" => "light-box.svg",
    "auditoria" => "light-audit.svg",
    "compras" => "light-purchase.svg"
);
$faviconSeccion = isset($iconosFavicon[$seccionActual]) ? $iconosFavicon[$seccionActual] : "favicon.svg";
$rutaFavicon = $faviconSeccion === "favicon.svg" ? $rutaBase . "assets/img/favicon.svg" : $rutaBase . "assets/img/sidebar/" . $faviconSeccion;
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($rutaBase ?? '', ENT_QUOTES, 'UTF-8'); ?>assets/css/sidebar.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($rutaBase ?? '', ENT_QUOTES, 'UTF-8'); ?>assets/css/theme.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/theme.css'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($rutaBase ?? '', ENT_QUOTES, 'UTF-8'); ?>assets/css/sin-seleccion.css">
<script src="<?php echo htmlspecialchars($rutaBase ?? '', ENT_QUOTES, 'UTF-8'); ?>assets/js/theme.js"></script>
<script>
(function () {
    var favicon = document.querySelector('link[rel="icon"]');
    if (!favicon) {
        favicon = document.createElement('link');
        favicon.rel = 'icon';
        favicon.type = 'image/svg+xml';
        document.head.appendChild(favicon);
    }
    favicon.href = <?php echo json_encode($rutaFavicon, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
})();
</script>
<div class="container">
        <div class="navigation">
            <ul>
                <?php if (usuario_tiene_permiso("inicio")): ?>
                <li class="<?php echo $seccionActual === 'inicio' ? 'active ' : ''; ?>" data-menu="inicio">
                    <a href="<?php echo $rutaBase ?? ""; ?>index.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-home.svg"></span>
                        <span class="title">Inicio</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("pedidos")): ?>
                <li class="<?php echo $seccionActual === 'pedidos' ? 'active ' : ''; ?>" data-menu="pedidos">
                    <a href="<?php echo $rutaBase ?? ""; ?>pedidos.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-restaurant.svg"></span>
                        <span class="title">Pedidos</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("mapa")): ?>
                <li class="<?php echo $seccionActual === 'mapa' ? 'active ' : ''; ?>" data-menu="mapa">
                    <a href="<?php echo $rutaBase ?? ""; ?>mapa.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-map.svg"></span>
                        <span class="title">Mapa</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("caja")): ?>
                <li class="<?php echo $seccionActual === 'caja' ? 'active ' : ''; ?>" data-menu="caja">
                    <a href="<?php echo $rutaBase ?? ""; ?>caja.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-payments.svg"></span>
                        <span class="title">Caja</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("clientes")): ?>
                <li class="<?php echo $seccionActual === 'clientes' ? 'active ' : ''; ?>" data-menu="clientes">
                    <a href="<?php echo $rutaBase ?? ""; ?>clientes.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-person.svg"></span>
                        <span class="title">Clientes</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("insumos")): ?>
                <li class="<?php echo $seccionActual === 'insumos' ? 'active ' : ''; ?>" data-menu="insumos">
                    <a href="<?php echo $rutaBase ?? ""; ?>insumos.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-box.svg"></span>
                        <span class="title">Insumos</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("productos")): ?>
                <li class="<?php echo $seccionActual === 'productos' ? 'active ' : ''; ?>" data-menu="productos">
                    <a href="<?php echo $rutaBase ?? ""; ?>productos.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-shopping.svg"></span>
                        <span class="title">Productos</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("empleados")): ?>
                <li class="<?php echo $seccionActual === 'empleados' ? 'active ' : ''; ?>" data-menu="empleados">
                    <a href="<?php echo $rutaBase ?? ""; ?>empleados.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-team.svg"></span>
                        <span class="title">Empleados</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("conteos")): ?>
                <li class="<?php echo $seccionActual === 'conteos' ? 'active ' : ''; ?>" data-menu="conteos">
                    <a href="<?php echo $rutaBase ?? ""; ?>conteos.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-box.svg"></span>
                        <span class="title">Conteo de insumos</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("auditoria")): ?>
                <li class="menu-auditoria <?php echo $seccionActual === 'auditoria' ? 'active ' : ''; ?>" data-menu="auditoria">
                    <a href="<?php echo $rutaBase ?? ""; ?>auditoria.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-audit.svg"></span>
                        <span class="title">Auditoría</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (usuario_tiene_permiso("compras")): ?>
                <li class="menu-compras <?php echo $seccionActual === 'compras' ? 'active ' : ''; ?>" data-menu="compras">
                    <a href="<?php echo $rutaBase ?? ""; ?>compras.php">
                        <span class="icon"><img src="<?php echo $rutaBase ?? ""; ?>assets/img/sidebar/light-purchase.svg"></span>
                        <span class="title">Compras</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>