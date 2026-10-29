<?php
require_once __DIR__ . "/../includes/api_json.php";
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
verificar_permiso("compras", true);
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function responder_compras($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    try {
    $insumos = array();
    $resultadoInsumos = mysqli_query(
        $conexion,
        "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.costo,
                COALESCE(m.simbolo, m.unidad, '-') AS unidad
         FROM Insumo i
         LEFT JOIN Medida m ON m.id_medida = i.id_medida
         WHERE i.activo = TRUE
         ORDER BY i.nombre"
    );
    if (!$resultadoInsumos) {
        error_log("No se pudieron consultar insumos para compras: " . mysqli_error($conexion));
        responder_compras(array("error" => "No se pudieron cargar los insumos."), 500);
    }
    while ($insumo = mysqli_fetch_assoc($resultadoInsumos)) {
        $insumos[] = $insumo;
    }

    $pagina = max(1, (int)obtener_parametro_get("pagina", "1"));
    $porPagina = 20;
    $offset = ($pagina - 1) * $porPagina;
    $resultadoTotal = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM Compra");
    if (!$resultadoTotal) {
        error_log("No se pudo contar el historial de compras: " . mysqli_error($conexion));
        responder_compras(array("error" => "No se pudo consultar el historial de compras."), 500);
    }
    $total = (int)mysqli_fetch_assoc($resultadoTotal)["total"];

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT c.id_compra, c.fecha_hora, c.costo_total, c.observacion,
                CONCAT(e.nombre, ' ', e.apellido) AS responsable
         FROM Compra c
         INNER JOIN Empleado e ON e.id_empleado = c.id_empleado
         ORDER BY c.fecha_hora DESC, c.id_compra DESC
         LIMIT ? OFFSET ?"
    );
    if (!$stmt) {
        error_log("No se pudo preparar el historial de compras: " . mysqli_error($conexion));
        responder_compras(array("error" => "No se pudo consultar el historial de compras."), 500);
    }
    mysqli_stmt_bind_param($stmt, "ii", $porPagina, $offset);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("No se pudo consultar el historial de compras: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        responder_compras(array("error" => "No se pudo consultar el historial de compras."), 500);
    }
    $resultadoCompras = mysqli_stmt_get_result($stmt);
    $compras = array();
    $idsCompra = array();
    while ($compra = mysqli_fetch_assoc($resultadoCompras)) {
        $compra["items"] = array();
        $compras[(int)$compra["id_compra"]] = $compra;
        $idsCompra[] = (int)$compra["id_compra"];
    }
    mysqli_stmt_close($stmt);

    if ($idsCompra) {
        $idsSql = implode(",", array_map("intval", $idsCompra));
        $resultadoDetalle = mysqli_query(
            $conexion,
            "SELECT x.id_compra, x.id_insumo, i.nombre, x.cantidad, x.costo, x.total,
                    COALESCE(m.simbolo, m.unidad, '-') AS unidad
             FROM Incluye x
             INNER JOIN Insumo i ON i.id_insumo = x.id_insumo
             LEFT JOIN Medida m ON m.id_medida = i.id_medida
             WHERE x.id_compra IN ({$idsSql})
             ORDER BY i.nombre"
        );
        if (!$resultadoDetalle) {
            error_log("No se pudo consultar el detalle de compras: " . mysqli_error($conexion));
            responder_compras(array("error" => "No se pudo consultar el detalle de compras."), 500);
        }
        while ($item = mysqli_fetch_assoc($resultadoDetalle)) {
            $compras[(int)$item["id_compra"]]["items"][] = $item;
        }
    }

    responder_compras(array(
        "insumos" => $insumos,
        "compras" => array_values($compras),
        "pagina" => $pagina,
        "total" => $total,
        "hay_anterior" => $pagina > 1,
        "hay_siguiente" => $offset + count($compras) < $total
    ));
    } catch (Throwable $error) {
        error_log("No se pudo consultar la información de compras: " . $error->getMessage());
        responder_compras(array("error" => "No se pudo consultar la información de compras."), 500);
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder_compras(array("error" => "Método no permitido."), 405);
}

$entrada = json_decode(file_get_contents("php://input"), true);
if (!is_array($entrada)) {
    responder_compras(array("error" => "Los datos de la compra no son válidos."), 400);
}
if (!validar_token_csrf($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "")) {
    responder_compras(array("error" => "La sesión venció. Recargá la página e intentá nuevamente."), 403);
}

$observacion = $entrada["observacion"] ?? "";
$itemsEntrada = $entrada["items"] ?? null;
if (!is_string($observacion) || strlen($observacion) > 5000 || !is_array($itemsEntrada) || count($itemsEntrada) < 1 || count($itemsEntrada) > 100) {
    responder_compras(array("error" => "Ingresá una observación válida y al menos un insumo."), 400);
}

$items = array();
$idsInsumo = array();
$totalCompra = 0.0;
foreach ($itemsEntrada as $item) {
    if (!is_array($item)) {
        responder_compras(array("error" => "Revisá las líneas de la compra."), 400);
    }
    $idInsumo = filter_var($item["id_insumo"] ?? null, FILTER_VALIDATE_INT);
    $cantidadEntrada = $item["cantidad"] ?? null;
    $costoEntrada = $item["costo_unitario"] ?? null;
    $cantidad = is_numeric($cantidadEntrada) && is_finite((float)$cantidadEntrada) ? (float)$cantidadEntrada : false;
    $costo = is_numeric($costoEntrada) && is_finite((float)$costoEntrada) ? (float)$costoEntrada : false;
    if (
        $idInsumo === false || $idInsumo <= 0 ||
        $cantidad === false || $cantidad <= 0 || $cantidad > 99999999.99 ||
        $costo === false || $costo <= 0 || $costo > 99999999.99 ||
        isset($idsInsumo[$idInsumo])
    ) {
        responder_compras(array("error" => "Cada insumo debe ser único y tener cantidad y costo unitario mayores que cero."), 400);
    }
    $cantidad = round($cantidad, 2);
    $costo = round($costo, 2);
    $subtotal = round($cantidad * $costo, 2);
    if ($cantidad <= 0 || $costo <= 0 || $subtotal <= 0 || $subtotal > 99999999.99) {
        responder_compras(array("error" => "La cantidad o el subtotal de una línea supera el valor permitido."), 400);
    }
    $totalCompra += $subtotal;
    $items[] = array(
        "id_insumo" => (int)$idInsumo,
        "cantidad" => $cantidad,
        "costo" => $costo,
        "total" => $subtotal
    );
    $idsInsumo[$idInsumo] = true;
}

$totalCompra = round($totalCompra, 2);
if ($totalCompra <= 0 || $totalCompra > 99999999.99) {
    responder_compras(array("error" => "El total de la compra supera el valor permitido."), 400);
}

try {
    mysqli_begin_transaction($conexion);
    $idsSql = implode(",", array_map("intval", array_keys($idsInsumo)));
    $resultadoInsumos = mysqli_query(
        $conexion,
        "SELECT id_insumo, cantidad_actual, costo
         FROM Insumo
         WHERE activo = TRUE AND id_insumo IN ({$idsSql})
         ORDER BY id_insumo
         FOR UPDATE"
    );
    if (!$resultadoInsumos) {
        throw new RuntimeException("No se pudieron bloquear los insumos: " . mysqli_error($conexion));
    }
    $stockActual = array();
    while ($fila = mysqli_fetch_assoc($resultadoInsumos)) {
        $stockActual[(int)$fila["id_insumo"]] = array(
            "cantidad" => (float)$fila["cantidad_actual"],
            "costo" => (float)$fila["costo"]
        );
    }
    if (count($stockActual) !== count($items)) {
        mysqli_rollback($conexion);
        responder_compras(array("error" => "Uno o más insumos ya no están activos."), 409);
    }

    $idEmpleado = (int)$_SESSION["usuario_id"];
    $observacion = trim($observacion);
    $observacion = $observacion === "" ? null : $observacion;
    $stmtCompra = mysqli_prepare(
        $conexion,
        "INSERT INTO Compra (costo_total, observacion, id_empleado) VALUES (?, ?, ?)"
    );
    if (!$stmtCompra) {
        throw new RuntimeException("No se pudo preparar la compra: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmtCompra, "dsi", $totalCompra, $observacion, $idEmpleado);
    if (!mysqli_stmt_execute($stmtCompra)) {
        throw new RuntimeException("No se pudo guardar la compra: " . mysqli_stmt_error($stmtCompra));
    }
    $idCompra = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtCompra);

    $stmtDetalle = mysqli_prepare(
        $conexion,
        "INSERT INTO Incluye (id_compra, id_insumo, cantidad, costo, total)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmtActualizar = mysqli_prepare(
        $conexion,
        "UPDATE Insumo SET cantidad_actual = ?, costo = ? WHERE id_insumo = ? AND activo = TRUE"
    );
    if (!$stmtDetalle || !$stmtActualizar) {
        if ($stmtDetalle) mysqli_stmt_close($stmtDetalle);
        if ($stmtActualizar) mysqli_stmt_close($stmtActualizar);
        throw new RuntimeException("No se pudieron preparar los detalles de la compra.");
    }

    foreach ($items as $item) {
        $idInsumo = $item["id_insumo"];
        $actual = $stockActual[$idInsumo];
        $cantidadNueva = round($actual["cantidad"] + $item["cantidad"], 2);
        if ($cantidadNueva > 99999999.99) {
            mysqli_stmt_close($stmtDetalle);
            mysqli_stmt_close($stmtActualizar);
            mysqli_rollback($conexion);
            responder_compras(array("error" => "La compra supera la capacidad de stock de uno de los insumos."), 400);
        }
        $costoPromedio = $actual["cantidad"] > 0
            ? round((($actual["cantidad"] * $actual["costo"]) + $item["total"]) / $cantidadNueva, 2)
            : $item["costo"];
        $cantidad = $item["cantidad"];
        $costoUnitario = $item["costo"];
        $subtotal = $item["total"];
        mysqli_stmt_bind_param($stmtDetalle, "iiddd", $idCompra, $idInsumo, $cantidad, $costoUnitario, $subtotal);
        if (!mysqli_stmt_execute($stmtDetalle)) {
            throw new RuntimeException("No se pudo guardar un insumo de la compra: " . mysqli_stmt_error($stmtDetalle));
        }
        mysqli_stmt_bind_param($stmtActualizar, "ddi", $cantidadNueva, $costoPromedio, $idInsumo);
        if (!mysqli_stmt_execute($stmtActualizar) || mysqli_stmt_affected_rows($stmtActualizar) !== 1) {
            throw new RuntimeException("No se pudo actualizar el stock y costo del insumo: " . mysqli_stmt_error($stmtActualizar));
        }
        if (!registrar_movimiento_insumo($conexion, $idInsumo, "compra", $cantidad, $costoUnitario, $idCompra)) {
            throw new RuntimeException("No se pudo registrar el movimiento de inventario.");
        }
    }
    mysqli_stmt_close($stmtDetalle);
    mysqli_stmt_close($stmtActualizar);
    if (!registrar_auditoria($conexion, "registrar_compra", "compra", $idCompra, array("total" => $totalCompra, "items" => count($items)))) {
        throw new RuntimeException("No se pudo registrar la auditoría de la compra.");
    }
    mysqli_commit($conexion);
} catch (Throwable $error) {
    mysqli_rollback($conexion);
    error_log("No se pudo registrar la compra: " . $error->getMessage());
    responder_compras(array("error" => "No se pudo registrar la compra. No se actualizaron el stock ni los costos."), 500);
}

responder_compras(array("ok" => true, "id_compra" => $idCompra, "costo_total" => $totalCompra), 201);
