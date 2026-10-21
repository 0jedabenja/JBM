<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("caja", true);
header("Content-Type: application/json; charset=utf-8");

function responder_caja($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

function entrada_caja() {
    $datos = json_decode(file_get_contents("php://input"), true);
    return is_array($datos) ? $datos : $_POST;
}

function obtener_movimientos_turno($conexion, $idTurno) {
    $movimientos = array();
    $totalCobrado = 0;
    $efectivoCobrado = 0;
    $stmt = mysqli_prepare($conexion, "SELECT p.id_pedido, p.fecha_hora_creacion, p.monto_total, p.cobrado, p.id_mp, COALESCE(mp.descripcion, 'Pendiente de cobro') AS metodo_pago, ep.nombre AS estado FROM Pedido p LEFT JOIN Metodo_pago mp ON mp.id_mp = p.id_mp INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado WHERE p.id_turno = ? AND (LOWER(ep.nombre) = 'listo' OR p.cobrado = TRUE) ORDER BY p.fecha_hora_creacion DESC");
    mysqli_stmt_bind_param($stmt, "i", $idTurno);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    while ($movimiento = mysqli_fetch_assoc($resultado)) {
        $movimientos[] = $movimiento;
        if ((int) $movimiento["cobrado"] === 1) {
            $totalCobrado += (float) $movimiento["monto_total"];
            if (strtolower($movimiento["metodo_pago"]) === "efectivo") {
                $efectivoCobrado += (float) $movimiento["monto_total"];
            }
        }
    }
    mysqli_stmt_close($stmt);
    return array("movimientos" => $movimientos, "total_cobrado" => $totalCobrado, "efectivo_cobrado" => $efectivoCobrado);
}

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo === "GET") {
    $esAdministrador = obtener_rol_sesion() === "administrador";
    $idEmpleado = (int) $_SESSION["usuario_id"];
    $idDetalle = filter_var(obtener_parametro_get("detalle"), FILTER_VALIDATE_INT);
    if ($idDetalle) {
        $stmtTurno = mysqli_prepare($conexion, "SELECT t.id_turno, t.fecha_hora_apertura, t.fecha_hora_cierre, t.monto_inicial, t.monto_final, t.id_empleado, CONCAT(e.nombre, ' ', e.apellido) AS empleado FROM Turno_caja t INNER JOIN Empleado e ON e.id_empleado = t.id_empleado WHERE t.id_turno = ? LIMIT 1");
        mysqli_stmt_bind_param($stmtTurno, "i", $idDetalle);
        mysqli_stmt_execute($stmtTurno);
        $turno = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTurno));
        mysqli_stmt_close($stmtTurno);
        if (!$turno) responder_caja(array("error" => "No se encontró la sesión de caja."), 404);
        if (!$esAdministrador && (int) $turno["id_empleado"] !== $idEmpleado) responder_caja(array("error" => "No tiene permiso para consultar esta sesión."), 403);
        $resumen = obtener_movimientos_turno($conexion, (int) $turno["id_turno"]);
        $turno["total_cobrado"] = $resumen["total_cobrado"];
        $turno["efectivo_cobrado"] = $resumen["efectivo_cobrado"];
        $turno["efectivo_esperado"] = (float) $turno["monto_inicial"] + $resumen["efectivo_cobrado"];
        responder_caja(array("sesion" => $turno, "movimientos" => $resumen["movimientos"]));
    }

    $resultado = mysqli_query($conexion, "SELECT t.id_turno, t.fecha_hora_apertura, t.fecha_hora_cierre, t.monto_inicial, t.monto_final, CONCAT(e.nombre, ' ', e.apellido) AS empleado FROM Turno_caja t INNER JOIN Empleado e ON e.id_empleado = t.id_empleado WHERE t.fecha_hora_cierre IS NULL ORDER BY t.fecha_hora_apertura DESC LIMIT 1");
    $abierta = mysqli_fetch_assoc($resultado) ?: null;
    $resumen = array("movimientos" => array(), "total_cobrado" => 0, "efectivo_cobrado" => 0);
    if ($abierta) {
        $resumen = obtener_movimientos_turno($conexion, (int) $abierta["id_turno"]);
    }
    $stmtPropias = mysqli_prepare($conexion, "SELECT t.id_turno, t.fecha_hora_apertura, t.fecha_hora_cierre, t.monto_inicial, t.monto_final, CONCAT(e.nombre, ' ', e.apellido) AS empleado FROM Turno_caja t INNER JOIN Empleado e ON e.id_empleado = t.id_empleado WHERE t.id_empleado = ? AND t.fecha_hora_cierre IS NOT NULL ORDER BY t.fecha_hora_apertura DESC LIMIT 5");
    mysqli_stmt_bind_param($stmtPropias, "i", $idEmpleado);
    mysqli_stmt_execute($stmtPropias);
    $sesionesPropias = array();
    $resultadoPropias = mysqli_stmt_get_result($stmtPropias);
    while ($sesion = mysqli_fetch_assoc($resultadoPropias)) $sesionesPropias[] = $sesion;
    mysqli_stmt_close($stmtPropias);

    $pagina = max(1, (int)obtener_parametro_get("pagina", "1"));
    $porPagina = 10;
    $historial = array();
    $totalSesiones = 0;
    if ($esAdministrador) {
        $resultadoTotal = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM Turno_caja");
        $totalSesiones = (int) mysqli_fetch_assoc($resultadoTotal)["total"];
        $pagina = min($pagina, max(1, (int) ceil($totalSesiones / $porPagina)));
        $desplazamiento = ($pagina - 1) * $porPagina;
        $stmtHistorial = mysqli_prepare($conexion, "SELECT t.id_turno, t.fecha_hora_apertura, t.fecha_hora_cierre, t.monto_inicial, t.monto_final, CONCAT(e.nombre, ' ', e.apellido) AS empleado FROM Turno_caja t INNER JOIN Empleado e ON e.id_empleado = t.id_empleado ORDER BY t.fecha_hora_apertura DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($stmtHistorial, "ii", $porPagina, $desplazamiento);
        mysqli_stmt_execute($stmtHistorial);
        $resultadoHistorial = mysqli_stmt_get_result($stmtHistorial);
        while ($sesion = mysqli_fetch_assoc($resultadoHistorial)) $historial[] = $sesion;
        mysqli_stmt_close($stmtHistorial);
    }
    $metodosPago = array();
    $resultadoMetodos = mysqli_query($conexion, "SELECT id_mp, descripcion FROM Metodo_pago ORDER BY id_mp");
    while ($metodoPago = mysqli_fetch_assoc($resultadoMetodos)) $metodosPago[] = $metodoPago;
    responder_caja(array(
        "abierta" => $abierta,
        "movimientos" => $resumen["movimientos"],
        "metodos_pago" => $metodosPago,
        "total_cobrado" => $resumen["total_cobrado"],
        "efectivo_esperado" => $abierta ? (float) $abierta["monto_inicial"] + $resumen["efectivo_cobrado"] : 0,
        "sesiones_propias" => $sesionesPropias,
        "es_administrador" => $esAdministrador,
        "historial" => $historial,
        "pagina" => $pagina,
        "por_pagina" => $porPagina,
        "total_sesiones" => $totalSesiones
    ));
}

$entrada = entrada_caja();
$accion = $entrada["accion"] ?? "";
if ($accion === "abrir") {
    $montoInicial = filter_var($entrada["monto_inicial"] ?? null, FILTER_VALIDATE_FLOAT);
    if ($montoInicial === false || $montoInicial < 0) responder_caja(array("error" => "El monto inicial no es válido."), 400);
    $resultado = mysqli_query($conexion, "SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL LIMIT 1");
    if (mysqli_fetch_assoc($resultado)) responder_caja(array("error" => "Ya existe una sesión de caja abierta."), 409);
    $idEmpleado = (int) $_SESSION["usuario_id"];
    $stmt = mysqli_prepare($conexion, "INSERT INTO Turno_caja (fecha_hora_apertura, monto_inicial, id_empleado) VALUES (NOW(), ?, ?)");
    mysqli_stmt_bind_param($stmt, "di", $montoInicial, $idEmpleado);
    mysqli_stmt_execute($stmt);
    $idTurno = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    registrar_auditoria($conexion, "abrir_caja", "turno_caja", $idTurno, array("monto_inicial" => $montoInicial));
    responder_caja(array("ok" => true, "id_turno" => $idTurno), 201);
}

if ($accion === "cerrar") {
    $montoFinal = filter_var($entrada["monto_final"] ?? null, FILTER_VALIDATE_FLOAT);
    if ($montoFinal === false || $montoFinal < 0) responder_caja(array("error" => "El monto final no es válido."), 400);
    $resultadoPendientes = mysqli_query($conexion, "SELECT p.id_pedido FROM Pedido p INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado WHERE p.id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL) AND p.cobrado = FALSE AND LOWER(ep.nombre) IN ('en preparación', 'comiendo', 'listo') LIMIT 1");
    if (mysqli_fetch_assoc($resultadoPendientes)) responder_caja(array("error" => "No se puede cerrar la caja mientras haya pedidos en curso o pendientes de cobro."), 409);
    $stmt = mysqli_prepare($conexion, "UPDATE Turno_caja SET fecha_hora_cierre = NOW(), monto_final = ? WHERE fecha_hora_cierre IS NULL");
    mysqli_stmt_bind_param($stmt, "d", $montoFinal);
    mysqli_stmt_execute($stmt);
    $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$actualizado) responder_caja(array("error" => "No hay una sesión de caja abierta."), 409);
    registrar_auditoria($conexion, "cerrar_caja", "turno_caja", null, array("monto_final" => $montoFinal));
    responder_caja(array("ok" => true));
}

if ($accion === "cobrar") {
    $idPedido = filter_var($entrada["id_pedido"] ?? null, FILTER_VALIDATE_INT);
    $idMetodoPago = filter_var($entrada["id_mp"] ?? null, FILTER_VALIDATE_INT);
    if ($idPedido === false || $idPedido <= 0 || $idMetodoPago === false || $idMetodoPago <= 0) responder_caja(array("error" => "Selecciona un medio de pago válido para cobrar el pedido."), 400);
    $stmtMetodo = mysqli_prepare($conexion, "SELECT id_mp, descripcion FROM Metodo_pago WHERE id_mp = ?");
    mysqli_stmt_bind_param($stmtMetodo, "i", $idMetodoPago);
    mysqli_stmt_execute($stmtMetodo);
    $metodoValido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMetodo));
    mysqli_stmt_close($stmtMetodo);
    if (!$metodoValido) responder_caja(array("error" => "El medio de pago seleccionado no existe."), 400);
    $esEfectivo = strtolower(trim($metodoValido["descripcion"])) === "efectivo";
    $efectivoRecibido = null;
    $vuelto = null;
    if ($esEfectivo) {
        $entradaEfectivo = $entrada["efectivo_recibido"] ?? null;
        if (!is_numeric($entradaEfectivo) || !is_finite((float)$entradaEfectivo) || (float)$entradaEfectivo < 0) {
            responder_caja(array("error" => "Ingresa un monto de efectivo válido."), 400);
        }
        $efectivoRecibido = round((float)$entradaEfectivo, 2);
        $stmtPedido = mysqli_prepare($conexion, "SELECT monto_total FROM Pedido WHERE id_pedido = ? AND cobrado = FALSE AND id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL) AND id_estado = (SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'listo' LIMIT 1)");
        if (!$stmtPedido) responder_caja(array("error" => "No se pudo validar el pedido."), 500);
        mysqli_stmt_bind_param($stmtPedido, "i", $idPedido);
        mysqli_stmt_execute($stmtPedido);
        $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPedido));
        mysqli_stmt_close($stmtPedido);
        if (!$pedido) responder_caja(array("error" => "El pedido no está listo para cobrar, ya fue cobrado o no pertenece a la caja abierta."), 409);
        $totalCentavos = (int)round((float)$pedido["monto_total"] * 100);
        $recibidoCentavos = (int)round($efectivoRecibido * 100);
        if ($recibidoCentavos < $totalCentavos) {
            responder_caja(array("error" => "El efectivo recibido no puede ser menor que el total del pedido."), 400);
        }
        $efectivoRecibido = $recibidoCentavos / 100;
        $vuelto = ($recibidoCentavos - $totalCentavos) / 100;
        $stmt = mysqli_prepare($conexion, "UPDATE Pedido p INNER JOIN Estado_Pedido e ON e.id_estado = p.id_estado SET p.cobrado = TRUE, p.id_mp = ? WHERE p.id_pedido = ? AND p.id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL) AND p.cobrado = FALSE AND LOWER(e.nombre) = 'listo'");
        if (!$stmt) responder_caja(array("error" => "No se pudo preparar el cobro del pedido."), 500);
        mysqli_stmt_bind_param($stmt, "ii", $idMetodoPago, $idPedido);
    } else {
        $stmt = mysqli_prepare($conexion, "UPDATE Pedido p INNER JOIN Estado_Pedido e ON e.id_estado = p.id_estado SET p.cobrado = TRUE, p.id_mp = ? WHERE p.id_pedido = ? AND p.id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL) AND p.cobrado = FALSE AND LOWER(e.nombre) = 'listo'");
        if (!$stmt) responder_caja(array("error" => "No se pudo preparar el cobro del pedido."), 500);
        mysqli_stmt_bind_param($stmt, "ii", $idMetodoPago, $idPedido);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $detalleError = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        error_log("Error al cobrar el pedido #{$idPedido}: " . $detalleError);
        responder_caja(array("error" => "No se pudo registrar el cobro."), 500);
    }
    $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$actualizado) responder_caja(array("error" => "El pedido no está listo para cobrar, ya fue cobrado o no pertenece a la caja abierta."), 409);
    registrar_auditoria($conexion, "cobrar_pedido", "pedido", $idPedido, array(
        "metodo_pago" => $metodoValido["descripcion"]
    ));
    responder_caja(array("ok" => true, "efectivo_recibido" => $efectivoRecibido, "vuelto" => $vuelto));
}

responder_caja(array("error" => "Acción no válida."), 400);
