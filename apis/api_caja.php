<?php
require_once __DIR__ . "/../includes/api_json.php";
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
    $stmt = mysqli_prepare($conexion, "
        SELECT 
            p.id_pedido, 
            p.fecha_hora_creacion, 
            p.monto_total, 
            p.cobrado, 
            p.id_cliente, 
            m.numero AS numero_mesa, 
            COALESCE(mp.descripcion, 'Pendiente de cobro') AS metodo_pago, 
            ep.nombre AS estado
        FROM Pedido p 
        LEFT JOIN (
            SELECT id_pedido, id_mp, ROW_NUMBER() OVER (PARTITION BY id_pedido ORDER BY fecha_hora) as rn 
            FROM Pago_pedido
        ) pp ON pp.id_pedido = p.id_pedido AND pp.rn = 1
        LEFT JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
        LEFT JOIN Mesa m ON m.id_mesa = p.id_mesa 
        INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado 
        WHERE p.id_turno = ? AND (LOWER(ep.nombre) = 'listo' OR p.cobrado = TRUE) 
        ORDER BY CASE WHEN p.cobrado = FALSE AND LOWER(ep.nombre) = 'listo' THEN 0 WHEN p.cobrado = TRUE THEN 1 ELSE 2 END, p.fecha_hora_creacion DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $idTurno);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    while ($movimiento = mysqli_fetch_assoc($resultado)) {
        $movimientos[] = $movimiento;
        if ((int) $movimiento["cobrado"] === 1) {
            $totalCobrado += (float) $movimiento["monto_total"];
        }
    }
    mysqli_stmt_close($stmt);
    
    // Calcular efectivo cobrado desde Pago_pedido usando JOIN con Metodo_pago
    $stmtEfectivo = mysqli_prepare($conexion, "
        SELECT COALESCE(SUM(pp.monto), 0) AS efectivo_total
        FROM Pago_pedido pp
        INNER JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
        INNER JOIN Pedido p ON p.id_pedido = pp.id_pedido
        WHERE p.id_turno = ? AND LOWER(mp.descripcion) = 'efectivo'
    ");
    if ($stmtEfectivo) {
        mysqli_stmt_bind_param($stmtEfectivo, "i", $idTurno);
        mysqli_stmt_execute($stmtEfectivo);
        $resultEfectivo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtEfectivo));
        mysqli_stmt_close($stmtEfectivo);
        $efectivoCobrado = (float)($resultEfectivo["efectivo_total"] ?? 0);
    }
    
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
    $descuentos = array();
    $resultadoDescuentos = mysqli_query($conexion, "SELECT id_descuento, valor FROM Descuento WHERE LOWER(tipo) = 'porcentaje' AND valor > 0 AND valor <= 100 ORDER BY valor");
    if (!$resultadoDescuentos) {
        error_log("No se pudieron consultar los descuentos: " . mysqli_error($conexion));
        responder_caja(array("error" => "No se pudieron cargar los descuentos."), 500);
    }
    while ($descuento = mysqli_fetch_assoc($resultadoDescuentos)) $descuentos[] = $descuento;
    responder_caja(array(
        "abierta" => $abierta,
        "movimientos" => $resumen["movimientos"],
        "metodos_pago" => $metodosPago,
        "descuentos" => $descuentos,
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

if (!validar_token_csrf($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "")) {
    responder_caja(array("error" => "La solicitud expiró. Recarga la página e inténtalo nuevamente."), 403);
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

    $resultadoTurno = mysqli_query($conexion, "SELECT id_turno, monto_inicial FROM Turno_caja WHERE fecha_hora_cierre IS NULL LIMIT 1");
    $turnoAbierto = mysqli_fetch_assoc($resultadoTurno);
    if (!$turnoAbierto) responder_caja(array("error" => "No hay una sesión de caja abierta."), 409);
    $resumenTurno = obtener_movimientos_turno($conexion, (int) $turnoAbierto["id_turno"]);
    $efectivoEsperado = (float) $turnoAbierto["monto_inicial"] + (float) $resumenTurno["efectivo_cobrado"];
    if (abs($montoFinal - $efectivoEsperado) > 200) {
        $passwordCierre = is_string($entrada["password_cierre"] ?? null) ? $entrada["password_cierre"] : "";
        $stmtPassword = mysqli_prepare($conexion, "SELECT contraseña FROM Empleado WHERE id_empleado = ? LIMIT 1");
        mysqli_stmt_bind_param($stmtPassword, "i", $_SESSION["usuario_id"]);
        mysqli_stmt_execute($stmtPassword);
        $empleado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPassword));
        mysqli_stmt_close($stmtPassword);
        if (!$empleado || $passwordCierre === "" || !password_verify($passwordCierre, $empleado["contraseña"])) {
            responder_caja(array("error" => "La diferencia supera $ 200,00. Confirma con tu contraseña de usuario."), 403);
        }
    }
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
    $transaccionIniciada = false;
    try {
    $idPedido = filter_var($entrada["id_pedido"] ?? null, FILTER_VALIDATE_INT);
    if ($idPedido === false || $idPedido <= 0) responder_caja(array("error" => "El pedido no es válido."), 400);

    $pagos = $entrada["pagos"] ?? [];
    if (!is_array($pagos) || count($pagos) === 0) responder_caja(array("error" => "Debe incluir al menos un método de pago."), 400);

    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;
    $stmtPedido = mysqli_prepare(
        $conexion,
        "SELECT p.id_cliente, p.monto_total, p.porcentaje_descuento, p.monto_descuento,
                (SELECT COALESCE(SUM(c.total), 0) FROM Contiene c WHERE c.id_pedido = p.id_pedido) AS subtotal
         FROM Pedido p
         WHERE p.id_pedido = ? AND p.cobrado = FALSE
           AND p.id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL)
           AND p.id_estado = (SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'listo' LIMIT 1)
         FOR UPDATE"
    );
    if (!$stmtPedido) {
        mysqli_rollback($conexion);
        responder_caja(array("error" => "No se pudo validar el pedido."), 500);
    }
    mysqli_stmt_bind_param($stmtPedido, "i", $idPedido);
    if (!mysqli_stmt_execute($stmtPedido)) {
        mysqli_stmt_close($stmtPedido);
        mysqli_rollback($conexion);
        responder_caja(array("error" => "No se pudo validar el pedido."), 500);
    }
    $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPedido));
    mysqli_stmt_close($stmtPedido);
    if (!$pedido) {
        mysqli_rollback($conexion);
        responder_caja(array("error" => "El pedido no está listo para cobrar, ya fue cobrado o no pertenece a la caja abierta."), 409);
    }

    $totalCobro = round((float)$pedido["monto_total"], 2);
    $sumaPagos = 0;
    $metodosValidos = [];
    foreach ($pagos as $pago) {
        $idMp = filter_var($pago["id_mp"] ?? null, FILTER_VALIDATE_INT);
        $monto = filter_var($pago["monto"] ?? null, FILTER_VALIDATE_FLOAT);
        if ($idMp === false || $idMp <= 0 || $monto === false || $monto <= 0) {
            mysqli_rollback($conexion);
            responder_caja(array("error" => "Uno o más métodos de pago no son válidos."), 400);
        }
        $stmtMetodo = mysqli_prepare($conexion, "SELECT id_mp, descripcion FROM Metodo_pago WHERE id_mp = ?");
        if (!$stmtMetodo) {
            mysqli_rollback($conexion);
            responder_caja(array("error" => "No se pudo validar un método de pago."), 500);
        }
        mysqli_stmt_bind_param($stmtMetodo, "i", $idMp);
        mysqli_stmt_execute($stmtMetodo);
        $metodo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMetodo));
        mysqli_stmt_close($stmtMetodo);
        if (!$metodo) {
            mysqli_rollback($conexion);
            responder_caja(array("error" => "El método de pago seleccionado no existe."), 400);
        }
        $monto = round($monto, 2);
        $sumaPagos += $monto;
        $metodosValidos[] = array("id_mp" => $idMp, "monto" => $monto, "descripcion" => $metodo["descripcion"]);
    }

    if (abs($sumaPagos - $totalCobro) > 0.01) {
        mysqli_rollback($conexion);
        responder_caja(array("error" => "La suma de los pagos no coincide con el total del pedido. Total: $" . number_format($totalCobro, 2) . ", Suma: $" . number_format($sumaPagos, 2)), 400);
    }

    $idEmpleado = (int) $_SESSION["usuario_id"];
    $idTurnoAbierto = null;
    $resultTurno = mysqli_query($conexion, "SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL LIMIT 1");
    if ($turnoAbierto = mysqli_fetch_assoc($resultTurno)) {
        $idTurnoAbierto = (int) $turnoAbierto["id_turno"];
    }
    if (!$idTurnoAbierto) {
        mysqli_rollback($conexion);
        responder_caja(array("error" => "No hay una sesión de caja abierta."), 409);
    }

    $stmtInsertPago = mysqli_prepare(
        $conexion,
        "INSERT INTO Pago_pedido (id_pedido, id_mp, monto, fecha_hora)
         VALUES (?, ?, ?, NOW())"
    );
    if (!$stmtInsertPago) {
        mysqli_rollback($conexion);
        responder_caja(array("error" => "No se pudo preparar el registro de pagos."), 500);
    }

    foreach ($metodosValidos as $metodo) {
        mysqli_stmt_bind_param($stmtInsertPago, "iid", $idPedido, $metodo["id_mp"], $metodo["monto"]);
        if (!mysqli_stmt_execute($stmtInsertPago)) {
            $detalleError = mysqli_stmt_error($stmtInsertPago);
            mysqli_stmt_close($stmtInsertPago);
            mysqli_rollback($conexion);
            error_log("Error al insertar pago del pedido #{$idPedido}: " . $detalleError);
            responder_caja(array("error" => "No se pudo registrar los pagos."), 500);
        }
    }
    mysqli_stmt_close($stmtInsertPago);

    $stmt = mysqli_prepare(
        $conexion,
        "UPDATE Pedido
         SET cobrado = TRUE, id_descuento = ?, monto_total = ?,
            porcentaje_descuento = ?, monto_descuento = ?
         WHERE id_pedido = ? AND cobrado = FALSE
           AND id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL)
           AND id_estado = (SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'listo' LIMIT 1)"
    );
    if (!$stmt) {
        mysqli_rollback($conexion);
        responder_caja(array("error" => "No se pudo preparar el cobro del pedido."), 500);
    }
    $idDescuento = $entrada["id_descuento"] ?? null;
    $porcentajeDescuento = $entrada["porcentaje_descuento"] ?? 0;
    $montoDescuento = $entrada["monto_descuento"] ?? 0;
    mysqli_stmt_bind_param($stmt, "iddi", $idDescuento, $totalCobro, $porcentajeDescuento, $montoDescuento, $idPedido);
    if (!mysqli_stmt_execute($stmt)) {
        $detalleError = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_rollback($conexion);
        error_log("Error al cobrar el pedido #{$idPedido}: " . $detalleError);
        responder_caja(array("error" => "No se pudo registrar el cobro."), 500);
    }
    $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$actualizado) {
        mysqli_rollback($conexion);
        responder_caja(array("error" => "El pedido no está listo para cobrar, ya fue cobrado o no pertenece a la caja abierta."), 409);
    }
    if (!registrar_auditoria($conexion, "cobrar_pedido", "pedido", $idPedido, array(
        "metodos_pago" => implode(", ", array_map(fn($m) => $m["descripcion"], $metodosValidos)),
        "porcentaje_descuento" => $porcentajeDescuento,
        "monto_descuento" => $montoDescuento
    ))) {
        mysqli_rollback($conexion);
        $transaccionIniciada = false;
        responder_caja(array("error" => "No se pudo registrar la auditoría del cobro."), 500);
    }
    if (!mysqli_commit($conexion)) {
        mysqli_rollback($conexion);
        $transaccionIniciada = false;
        responder_caja(array("error" => "No se pudo confirmar el cobro."), 500);
    }
    $transaccionIniciada = false;
    responder_caja(array(
        "ok" => true,
        "total" => $totalCobro,
        "pagos" => $metodosValidos
    ));
    } catch (mysqli_sql_exception $error) {
        if ($transaccionIniciada) mysqli_rollback($conexion);
        error_log("Error de base de datos al cobrar el pedido: " . $error->getMessage());
        responder_caja(array("error" => "No se pudo registrar el cobro."), 500);
    }
}

responder_caja(array("error" => "Acción no válida."), 400);
