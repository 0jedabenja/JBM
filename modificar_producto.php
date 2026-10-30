<?php
include_once("includes/conexionBD.php");
include_once("includes/funciones.php");
verificar_permiso("productos");
$id_producto = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if ($id_producto === false || $id_producto <= 0) mostrar_pagina_error(400);
$mensaje = "";
$categorias = array();
$insumos = array();
$consulta = mysqli_query($conexion, "SELECT id_categoria, titulo FROM Categoria ORDER BY titulo");
while ($fila = mysqli_fetch_assoc($consulta)) $categorias[] = $fila;
$consulta = mysqli_query($conexion, "SELECT i.id_insumo, i.nombre, COALESCE(m.unidad, '') AS unidad FROM Insumo i LEFT JOIN Medida m ON m.id_medida = i.id_medida WHERE i.activo = TRUE ORDER BY i.nombre");
while ($fila = mysqli_fetch_assoc($consulta)) $insumos[] = $fila;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $precio = filter_var($_POST["precio"] ?? null, FILTER_VALIDATE_FLOAT);
    $id_categoria = filter_var($_POST["id_categoria"] ?? null, FILTER_VALIDATE_INT);
    $idsInsumos = $_POST["id_insumo"] ?? array();
    $cantidades = $_POST["cantidad"] ?? array();
    $componentes = array();
    $usados = array();
    $componentesValidos = is_array($idsInsumos) && is_array($cantidades) && count($idsInsumos) === count($cantidades) && count($idsInsumos) > 0;
    if ($componentesValidos) {
        foreach ($idsInsumos as $indice => $valor) {
            $idInsumo = filter_var($valor, FILTER_VALIDATE_INT);
            $cantidad = filter_var($cantidades[$indice] ?? null, FILTER_VALIDATE_FLOAT);
            if ($idInsumo === false || $idInsumo <= 0 || $cantidad === false || $cantidad <= 0 || isset($usados[$idInsumo])) { $componentesValidos = false; break; }
            $usados[$idInsumo] = true;
            $componentes[] = array("id_insumo" => $idInsumo, "cantidad" => $cantidad);
        }
    }
    if ($nombre === "") $mensaje = "El nombre es obligatorio.";
    elseif ($precio === false || $precio < 0 || $id_categoria === false || $id_categoria <= 0) $mensaje = "El precio o la categoría no son válidos.";
    elseif (!$componentesValidos) $mensaje = "Debe indicar al menos un insumo válido y no repetirlo.";
    else {
        mysqli_begin_transaction($conexion);
        $correcto = false;
        $stmt = mysqli_prepare($conexion, "UPDATE Producto SET nombre = ?, precio = ?, id_categoria = ? WHERE id_producto = ? AND activo = TRUE");
        if ($stmt) { mysqli_stmt_bind_param($stmt, "sdii", $nombre, $precio, $id_categoria, $id_producto); $correcto = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
        if ($correcto) {
            $stmt = mysqli_prepare($conexion, "DELETE FROM Compone WHERE id_producto = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_producto); $correcto = mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        if ($correcto) {
            $stmt = mysqli_prepare($conexion, "INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES (?, ?, ?)");
            foreach ($componentes as $componente) {
                $idInsumo = $componente["id_insumo"]; $cantidad = $componente["cantidad"];
                mysqli_stmt_bind_param($stmt, "iid", $id_producto, $idInsumo, $cantidad);
                if (!mysqli_stmt_execute($stmt)) { $correcto = false; break; }
            }
            mysqli_stmt_close($stmt);
        }
        if ($correcto) {
            mysqli_commit($conexion);
            registrar_auditoria($conexion, "modificar_producto", "producto", $id_producto, array("nombre" => $nombre, "precio" => $precio, "componentes" => count($componentes)));
            header("Location: productos.php");
            exit();
        }
        mysqli_rollback($conexion); $mensaje = "No se pudo actualizar el producto y su receta.";
    }
}
$stmt = mysqli_prepare($conexion, "SELECT id_producto, nombre, precio, id_categoria FROM Producto WHERE id_producto = ? AND activo = TRUE");
mysqli_stmt_bind_param($stmt, "i", $id_producto); mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt); $producto = mysqli_fetch_assoc($resultado); mysqli_stmt_close($stmt);
if (!$producto) mostrar_pagina_error(404);
$receta = array();
$stmt = mysqli_prepare($conexion, "SELECT id_insumo, cantidad FROM Compone WHERE id_producto = ? ORDER BY id_insumo");
mysqli_stmt_bind_param($stmt, "i", $id_producto); mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt);
while ($fila = mysqli_fetch_assoc($resultado)) $receta[] = $fila;
mysqli_stmt_close($stmt);
if ($_SERVER["REQUEST_METHOD"] === "POST" && $mensaje !== "") $receta = array_map(function ($indice) { return array("id_insumo" => $_POST["id_insumo"][$indice] ?? "", "cantidad" => $_POST["cantidad"][$indice] ?? ""); }, array_keys($_POST["id_insumo"] ?? array()));
?>
<!DOCTYPE html><html lang="es"><head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Modificar producto</title><link rel="stylesheet" href="assets/css/alta.css"><script src="assets/js/sidebar.js" defer></script><link rel="stylesheet" href="assets/css/modificar_producto.css"></head>
<body><?php include_once "includes/sidebar.php"; ?><div class="main"><div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once "includes/profile.php"; ?></div><main class="alta-content"><section class="alta-panel"><h1>Modificar producto</h1><p>Actualiza los datos del producto y los insumos que componen su receta.</p><?php if ($mensaje !== ""): ?><p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p><?php endif; ?><form class="alta-form" method="POST"><label for="nombre">Nombre *</label><input id="nombre" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars($producto["nombre"], ENT_QUOTES, "UTF-8"); ?>"><label for="precio">Precio *</label><input id="precio" name="precio" type="number" min="0" step="0.01" required value="<?php echo htmlspecialchars($producto["precio"], ENT_QUOTES, "UTF-8"); ?>"><label for="id_categoria">Categoría *</label><select id="id_categoria" name="id_categoria" required><?php foreach ($categorias as $categoria): ?><option value="<?php echo (int)$categoria["id_categoria"]; ?>" <?php echo (int)$categoria["id_categoria"] === (int)$producto["id_categoria"] ? "selected" : ""; ?>><?php echo htmlspecialchars($categoria["titulo"], ENT_QUOTES, "UTF-8"); ?></option><?php endforeach; ?></select><h3>Receta</h3><div id="componentes" class="componentes"><?php foreach ($receta as $componente): ?><div class="componente"><select name="id_insumo[]" required><option value="">Seleccione un insumo...</option><?php foreach ($insumos as $insumo): ?><option value="<?php echo (int)$insumo["id_insumo"]; ?>" <?php echo (int)$insumo["id_insumo"] === (int)$componente["id_insumo"] ? "selected" : ""; ?> data-unidad="<?php echo htmlspecialchars($insumo["unidad"], ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($insumo["nombre"] . ($insumo["unidad"] !== "" ? " (" . $insumo["unidad"] . ")" : ""), ENT_QUOTES, "UTF-8"); ?></option><?php endforeach; ?></select><input type="number" name="cantidad[]" min="0.01" step="0.01" required value="<?php echo htmlspecialchars($componente["cantidad"], ENT_QUOTES, "UTF-8"); ?>"><span class="unidad-componente"></span><button type="button" onclick="quitar(this)">Quitar</button></div><?php endforeach; ?></div><button type="button" onclick="agregar()">Agregar insumo</button><div class="alta-actions"><button type="submit">Guardar cambios</button><a href="productos.php">Volver al listado</a></div></form></section></main></div><template id="plantilla"><div class="componente"><select name="id_insumo[]" required><option value="">Seleccione un insumo...</option><?php foreach ($insumos as $insumo): ?><option value="<?php echo (int)$insumo["id_insumo"]; ?>"><?php echo htmlspecialchars($insumo["nombre"], ENT_QUOTES, "UTF-8"); ?></option><?php endforeach; ?></select><input type="number" name="cantidad[]" min="0.01" step="0.01" required><span class="unidad-componente"></span><button type="button" onclick="quitar(this)">Quitar</button></div></template><script>function agregar(){document.getElementById("componentes").appendChild(document.getElementById("plantilla").content.cloneNode(true));}function actualizarUnidad(componente){var opcion=componente.querySelector("select").selectedOptions[0];componente.querySelector(".unidad-componente").textContent=opcion&&opcion.dataset.unidad?opcion.dataset.unidad:"";}document.addEventListener("change",function(e){if(e.target.matches("#componentes select"))actualizarUnidad(e.target.parentElement);});document.querySelectorAll("#componentes .componente").forEach(actualizarUnidad);function quitar(boton){if(document.querySelectorAll("#componentes .componente").length>1)boton.parentElement.remove();}</script></body></html>
