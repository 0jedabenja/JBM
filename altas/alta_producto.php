<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion();
verificar_permiso("productos");

$mensaje = "";
$rutaBase = "../";
$categorias = array();
$consultaCategorias = mysqli_query($conexion, "SELECT id_categoria, titulo FROM Categoria ORDER BY titulo ASC");
if ($consultaCategorias) {
    while ($row = mysqli_fetch_assoc($consultaCategorias)) {
        $categorias[] = $row;
    }
}

$insumos = array();
$consultaInsumos = mysqli_query($conexion, "SELECT id_insumo, nombre FROM Insumo WHERE activo = TRUE ORDER BY nombre ASC");
if ($consultaInsumos) {
    while ($row = mysqli_fetch_assoc($consultaInsumos)) {
        $insumos[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $precio = filter_var($_POST["precio"] ?? null, FILTER_VALIDATE_FLOAT);
    $id_categoria = filter_var($_POST["id_categoria"] ?? null, FILTER_VALIDATE_INT);
    $idsInsumos = $_POST["id_insumo"] ?? array();
    $cantidades = $_POST["cantidad"] ?? array();
    $componentes = array();
    $idsUsados = array();
    $componentesValidos = is_array($idsInsumos) && is_array($cantidades) && count($idsInsumos) === count($cantidades) && count($idsInsumos) > 0;

    if ($componentesValidos) {
        foreach ($idsInsumos as $indice => $valorId) {
            $idInsumo = filter_var($valorId, FILTER_VALIDATE_INT);
            $cantidad = filter_var($cantidades[$indice], FILTER_VALIDATE_FLOAT);
            if ($idInsumo === false || $idInsumo <= 0 || $cantidad === false || $cantidad <= 0 || isset($idsUsados[$idInsumo])) {
                $componentesValidos = false;
                break;
            }
            $idsUsados[$idInsumo] = true;
            $componentes[] = array("id_insumo" => $idInsumo, "cantidad" => $cantidad);
        }
    }

    if ($nombre === "") {
        $mensaje = "El nombre del producto es obligatorio.";
    } elseif ($precio === false || $precio < 0 || $id_categoria === false || $id_categoria <= 0) {
        $mensaje = "El precio o la categoría no son válidos.";
    } elseif (!$componentesValidos) {
        $mensaje = "Debe indicar al menos un insumo válido y no repetirlo.";
    } else {
        mysqli_begin_transaction($conexion);
        $correcto = false;
        $sql = "INSERT INTO Producto (nombre, precio, id_categoria) VALUES (?, ?, ?)";
        $stmtProducto = mysqli_prepare($conexion, $sql);
        if ($stmtProducto) {
            mysqli_stmt_bind_param($stmtProducto, "sdi", $nombre, $precio, $id_categoria);
            $correcto = mysqli_stmt_execute($stmtProducto);
            $idProducto = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtProducto);
        }

        if ($correcto) {
            $stmtComponente = mysqli_prepare($conexion, "INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES (?, ?, ?)");
            $correcto = $stmtComponente !== false;
            if ($correcto) {
                foreach ($componentes as $componente) {
                    $idInsumo = $componente["id_insumo"];
                    $cantidad = $componente["cantidad"];
                    mysqli_stmt_bind_param($stmtComponente, "iid", $idProducto, $idInsumo, $cantidad);
                    if (!mysqli_stmt_execute($stmtComponente)) {
                        $correcto = false;
                        break;
                    }
                }
                mysqli_stmt_close($stmtComponente);
            }
        }

        if ($correcto) {
            mysqli_commit($conexion);
            header("Location: ../productos.php");
            exit();
        }

        mysqli_rollback($conexion);
        $mensaje = "No se pudo registrar el producto y sus insumos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto</title>
    <link rel="stylesheet" href="../assets/css/alta.css">
    <script src="../assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(7) { background-color: #fff; }
        .navigation ul li:nth-child(7) a { color: #001f47; }
        .navigation ul li:nth-child(7) a .icon img { content: url('../assets/img/sidebar/theme-shopping.svg'); }
    </style>
</head>
<body>
    <?php include_once "../includes/sidebar.php"; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="../assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once "../includes/profile.php"; ?>
        </div>

        <main class="alta-content">
            <section class="alta-panel">
                <h1>Registrar nuevo producto</h1>
                <p>Completa los datos del producto y sus insumos.</p>
                <?php if ($mensaje !== ""): ?>
                    <p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p>
                <?php endif; ?>
    <form class="alta-form" action="alta_producto.php" method="POST">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" maxlength="100" required
               value="<?php echo htmlspecialchars($_POST["nombre"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
        <label for="precio">Precio *</label>
        <input type="number" id="precio" name="precio" min="0" step="0.01" required
               value="<?php echo htmlspecialchars($_POST["precio"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
        <label for="id_categoria">Categoría *</label>
        <select id="id_categoria" name="id_categoria" required>
            <option value="">Seleccione una opción...</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?php echo $categoria["id_categoria"]; ?>" <?php echo ((int)$categoria["id_categoria"] === (int)($_POST["id_categoria"] ?? 0)) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($categoria["titulo"], ENT_QUOTES, "UTF-8"); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <h3>Insumos del producto</h3>
        <div id="componentes" class="componentes">
            <div class="componente">
                <select name="id_insumo[]" required>
                    <option value="">Seleccione un insumo...</option>
                    <?php foreach ($insumos as $insumo): ?>
                        <option value="<?php echo $insumo["id_insumo"]; ?>"><?php echo htmlspecialchars($insumo["nombre"], ENT_QUOTES, "UTF-8"); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="cantidad[]" min="0.01" step="0.01" required>
                <button type="button" onclick="quitar(this)">Quitar</button>
            </div>
        </div>
        <button type="button" onclick="agregar()">Agregar insumo</button>
        <div class="alta-actions">
            <button type="submit">Guardar producto</button>
            <a href="../productos.php">Volver al listado</a>
        </div>
    </form>
            </section>
        </main>
    </div>
    <template id="plantilla">
        <div class="componente">
            <select name="id_insumo[]" required>
                <option value="">Seleccione un insumo...</option>
                <?php foreach ($insumos as $insumo): ?>
                    <option value="<?php echo $insumo["id_insumo"]; ?>"><?php echo htmlspecialchars($insumo["nombre"], ENT_QUOTES, "UTF-8"); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="cantidad[]" min="0.01" step="0.01" required>
            <button type="button" onclick="quitar(this)">Quitar</button>
        </div>
    </template>
    <script>
        function agregar() { document.getElementById("componentes").appendChild(document.getElementById("plantilla").content.cloneNode(true)); }
        function quitar(boton) { if (document.querySelectorAll("#componentes .componente").length > 1) boton.parentElement.remove(); }
    </script>
</body>
</html>