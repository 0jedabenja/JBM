<?php
require_once __DIR__ . "/../includes/api_json.php";
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
verificar_sesion_api();
verificar_permiso("insumos", true);
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function responder_ajuste_stock($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

function entrada_ajuste_stock() {
    $datos = json_decode(file_get_contents("php://input"), true);
    return is_array($datos) ? $datos : $_POST;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $tipo = obtener_parametro_get("tipo", "insumo");
    if ($tipo === "insumo") {
        $resultado = mysqli_query($conexion, "SELECT i.id_insumo AS id, i.nombre, i.cantidad_actual, COALESCE(m.simbolo, '') AS unidad FROM Insumo i LEFT JOIN Medida m ON m.id_medida = i.id_medida WHERE i.activo = TRUE ORDER BY i.nombre");
    } elseif ($tipo === "producto") {
        $resultado = mysqli_query($conexion, "SELECT p.id_producto AS id, p.nombre, COUNT(c.id_insumo) AS componentes FROM Producto p LEFT JOIN Compone c ON c.id_producto = p.id_producto WHERE p.activo = TRUE GROUP BY p.id_producto, p.nombre ORDER BY p.nombre");
    } else {
        responder_ajuste_stock(array("error" => "El tipo de elemento no es válido."), 400);
    }
    if (!$resultado) {
        error_log("No se pudieron consultar los elementos para ajustar stock: " . mysqli_error($conexion));
        responder_ajuste_stock(array("error" => "No se pudieron cargar los elementos."), 500);
    }
    $elementos = array();
    while ($fila = mysqli_fetch_assoc($resultado)) $elementos[] = $fila;
    responder_ajuste_stock(array("elementos" => $elementos));
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder_ajuste_stock(array("error" => "Método no permitido."), 405);
}
if (!validar_token_csrf($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "")) {
    responder_ajuste_stock(array("error" => "La solicitud expiró. Recarga la página e inténtalo nuevamente."), 403);
}

$entrada = entrada_ajuste_stock();
$tipoMovimiento = $entrada["tipo_movimiento"] ?? "";
$tipoElemento = $entrada["tipo_elemento"] ?? "";
$idElemento = filter_var($entrada["id_elemento"] ?? null, FILTER_VALIDATE_INT);
$cantidad = $entrada["cantidad"] ?? null;
$observacionEntrada = $entrada["observacion"] ?? "";
if (!is_string($tipoMovimiento) || !is_string($tipoElemento) || !is_string($observacionEntrada)) {
    responder_ajuste_stock(array("error" => "Los datos del movimiento no tienen un formato válido."), 400);
}
$observacion = trim($observacionEntrada);
if (
    !in_array($tipoMovimiento, array("perdida", "averia", "consumo_personal"), true)
    || !in_array($tipoElemento, array("insumo", "producto"), true)
    || $idElemento === false || $idElemento <= 0
    || !is_numeric($cantidad) || !is_finite((float)$cantidad) || (float)$cantidad <= 0 || (float)$cantidad > 99999999.99
) {
    responder_ajuste_stock(array("error" => "Revisa el motivo, elemento y cantidad ingresados."), 400);
}
if ($tipoMovimiento !== "consumo_personal" && $tipoElemento !== "insumo") {
    responder_ajuste_stock(array("error" => "Las pérdidas y averías se registran sobre insumos. Para retirar un producto del menú, selecciona consumo del personal."), 400);
}
if ($tipoElemento === "producto" && (float)$cantidad !== floor((float)$cantidad)) {
    responder_ajuste_stock(array("error" => "La cantidad de productos debe ser un número entero."), 400);
}
if (strlen($observacion) > 180) {
    responder_ajuste_stock(array("error" => "El detalle no puede superar los 180 caracteres."), 400);
}

$cantidad = round((float)$cantidad, 2);
if ($cantidad <= 0) {
    responder_ajuste_stock(array("error" => "La cantidad debe ser al menos 0,01."), 400);
}
$movimientos = array();
mysqli_begin_transaction($conexion);
try {
    if ($tipoElemento === "insumo") {
        $stmt = mysqli_prepare($conexion, "SELECT id_insumo, nombre, cantidad_actual, costo FROM Insumo WHERE id_insumo = ? AND activo = TRUE FOR UPDATE");
        if (!$stmt) throw new RuntimeException("No se pudo validar el insumo.");
        mysqli_stmt_bind_param($stmt, "i", $idElemento);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException("No se pudo consultar el insumo.");
        }
        $insumo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$insumo) throw new DomainException("El insumo no existe o está inactivo.");
        $requeridos = array(array(
            "id_insumo" => (int)$insumo["id_insumo"],
            "nombre" => $insumo["nombre"],
            "cantidad_actual" => (float)$insumo["cantidad_actual"],
            "costo" => (float)$insumo["costo"],
            "cantidad_salida" => $cantidad
        ));
        $nombreElemento = $insumo["nombre"];
    } else {
        $stmtProducto = mysqli_prepare($conexion, "SELECT nombre FROM Producto WHERE id_producto = ? AND activo = TRUE");
        if (!$stmtProducto) throw new RuntimeException("No se pudo validar el producto.");
        mysqli_stmt_bind_param($stmtProducto, "i", $idElemento);
        if (!mysqli_stmt_execute($stmtProducto)) {
            mysqli_stmt_close($stmtProducto);
            throw new RuntimeException("No se pudo consultar el producto.");
        }
        $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtProducto));
        mysqli_stmt_close($stmtProducto);
        if (!$producto) throw new DomainException("El producto no existe o está inactivo.");
        $nombreElemento = $producto["nombre"];
        $stmtReceta = mysqli_prepare(
            $conexion,
            "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.costo, i.activo, c.cantidad AS cantidad_receta
             FROM Compone c
             INNER JOIN Insumo i ON i.id_insumo = c.id_insumo
             WHERE c.id_producto = ?
             ORDER BY i.id_insumo
             FOR UPDATE"
        );
        if (!$stmtReceta) throw new RuntimeException("No se pudo consultar la receta.");
        mysqli_stmt_bind_param($stmtReceta, "i", $idElemento);
        if (!mysqli_stmt_execute($stmtReceta)) {
            mysqli_stmt_close($stmtReceta);
            throw new RuntimeException("No se pudo consultar la receta.");
        }
        $resultadoReceta = mysqli_stmt_get_result($stmtReceta);
        $requeridos = array();
        while ($componente = mysqli_fetch_assoc($resultadoReceta)) {
            if (!(bool)$componente["activo"]) {
                mysqli_stmt_close($stmtReceta);
                throw new DomainException("La receta incluye insumos inactivos y no se puede descontar.");
            }
            $requeridos[] = array(
                "id_insumo" => (int)$componente["id_insumo"],
                "nombre" => $componente["nombre"],
                "cantidad_actual" => (float)$componente["cantidad_actual"],
                "costo" => (float)$componente["costo"],
                "cantidad_salida" => round((float)$componente["cantidad_receta"] * $cantidad, 2)
            );
        }
        mysqli_stmt_close($stmtReceta);
        if (!$requeridos) {
            if (!registrar_auditoria($conexion, "salida_stock_" . $tipoMovimiento, "producto", $idElemento, array(
                "elemento" => $nombreElemento,
                "cantidad" => $cantidad,
                "detalle" => $observacion !== "" ? $observacion : "Salida de producto sin receta configurada.",
                "insumos_afectados" => 0
            ))) {
                throw new RuntimeException("No se pudo registrar la salida del producto.");
            }
            if (!mysqli_commit($conexion)) throw new RuntimeException("No se pudo confirmar la salida del producto.");
            responder_ajuste_stock(array("ok" => true, "movimientos" => array(), "sin_receta" => true), 201);
        }
    }

    foreach ($requeridos as $requerido) {
        if ($requerido["cantidad_salida"] <= 0 || $requerido["cantidad_actual"] < $requerido["cantidad_salida"]) {
            throw new DomainException("Stock insuficiente de " . $requerido["nombre"] . ".");
        }
    }

    $stmtActualizar = mysqli_prepare($conexion, "UPDATE Insumo SET cantidad_actual = cantidad_actual - ? WHERE id_insumo = ? AND cantidad_actual >= ?");
    if (!$stmtActualizar) throw new RuntimeException("No se pudo preparar la actualización del stock.");
    $detalle = $observacion;
    if ($tipoElemento === "producto") {
        $detalle = "Producto: " . $nombreElemento . " x " . $cantidad . ($observacion !== "" ? " - " . $observacion : "");
    }
    foreach ($requeridos as $requerido) {
        $cantidadSalida = $requerido["cantidad_salida"];
        $idInsumo = $requerido["id_insumo"];
        mysqli_stmt_bind_param($stmtActualizar, "did", $cantidadSalida, $idInsumo, $cantidadSalida);
        if (!mysqli_stmt_execute($stmtActualizar) || mysqli_stmt_affected_rows($stmtActualizar) !== 1) {
            throw new RuntimeException("No se pudo actualizar el stock de " . $requerido["nombre"] . ".");
        }
        $detalleMovimiento = $tipoElemento === "producto"
            ? substr("Producto " . $nombreElemento . " x " . $cantidad . ($observacion !== "" ? " - " . $observacion : ""), 0, 255)
            : ($observacion !== "" ? $observacion : null);
        if (!registrar_movimiento_insumo(
            $conexion,
            $idInsumo,
            $tipoMovimiento,
            -$cantidadSalida,
            $requerido["costo"],
            null,
            $detalleMovimiento
        )) {
            throw new RuntimeException("No se pudo guardar el historial del movimiento.");
        }
        $movimientos[] = array("insumo" => $requerido["nombre"], "cantidad" => $cantidadSalida);
    }
    mysqli_stmt_close($stmtActualizar);
    if (!registrar_auditoria($conexion, "salida_stock_" . $tipoMovimiento, $tipoElemento, $idElemento, array(
        "elemento" => $nombreElemento,
        "cantidad" => $cantidad,
        "detalle" => $detalle,
        "insumos_afectados" => count($movimientos)
    ))) {
        throw new RuntimeException("No se pudo registrar la auditoría.");
    }
    if (!mysqli_commit($conexion)) {
        throw new RuntimeException("No se pudo confirmar la salida de stock.");
    }
} catch (DomainException $error) {
    mysqli_rollback($conexion);
    responder_ajuste_stock(array("error" => $error->getMessage()), 409);
} catch (RuntimeException $error) {
    mysqli_rollback($conexion);
    error_log("No se pudo registrar la salida de stock: " . $error->getMessage());
    responder_ajuste_stock(array("error" => "No se pudo registrar la salida ni actualizar el stock."), 500);
}

responder_ajuste_stock(array("ok" => true, "movimientos" => $movimientos), 201);
