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

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo === "GET") {
    $resultado = mysqli_query($conexion, "SELECT t.id_turno, t.fecha_hora_apertura, t.fecha_hora_cierre, t.monto_inicial, t.monto_final, CONCAT(e.nombre, ' ', e.apellido) AS empleado FROM Turno_caja t INNER JOIN Empleado e ON e.id_empleado = t.id_empleado WHERE t.fecha_hora_cierre IS NULL ORDER BY t.fecha_hora_apertura DESC LIMIT 1");
    $abierta = mysqli_fetch_assoc($resultado) ?: null;
    $movimientos = array();
    $totalCobrado = 0;
    if ($abierta) {
        $stmtMovimientos = mysqli_prepare($conexion, "SELECT p.id_pedido, p.fecha_hora_creacion, p.monto_total, p.cobrado, mp.descripcion AS metodo_pago, ep.nombre AS estado FROM Pedido p INNER JOIN Metodo_pago mp ON mp.id_mp = p.id_mp INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado WHERE p.id_turno = ? ORDER BY p.fecha_hora_creacion DESC");
        mysqli_stmt_bind_param($stmtMovimientos, "i", $abierta["id_turno"]);
        mysqli_stmt_execute($stmtMovimientos);
        $resultadoMovimientos = mysqli_stmt_get_result($stmtMovimientos);
        while ($movimiento = mysqli_fetch_assoc($resultadoMovimientos)) {
            $movimientos[] = $movimiento;
            if ((int) $movimiento["cobrado"] === 1) $totalCobrado += (float) $movimiento["monto_total"];
        }
        mysqli_stmt_close($stmtMovimientos);
    }
    responder_caja(array("abierta" => $abierta, "movimientos" => $movimientos, "total_cobrado" => $totalCobrado));
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
    responder_caja(array("ok" => true, "id_turno" => $idTurno), 201);
}

if ($accion === "cerrar") {
    $montoFinal = filter_var($entrada["monto_final"] ?? null, FILTER_VALIDATE_FLOAT);
    if ($montoFinal === false || $montoFinal < 0) responder_caja(array("error" => "El monto final no es válido."), 400);
    $stmt = mysqli_prepare($conexion, "UPDATE Turno_caja SET fecha_hora_cierre = NOW(), monto_final = ? WHERE fecha_hora_cierre IS NULL");
    mysqli_stmt_bind_param($stmt, "d", $montoFinal);
    mysqli_stmt_execute($stmt);
    $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$actualizado) responder_caja(array("error" => "No hay una sesión de caja abierta."), 409);
    responder_caja(array("ok" => true));
}

if ($accion === "cobrar") {
    $idPedido = filter_var($entrada["id_pedido"] ?? null, FILTER_VALIDATE_INT);
    if ($idPedido === false || $idPedido <= 0) responder_caja(array("error" => "El pedido no es válido."), 400);
    $stmt = mysqli_prepare($conexion, "UPDATE Pedido p INNER JOIN Estado_Pedido e ON e.id_estado = p.id_estado SET p.cobrado = TRUE WHERE p.id_pedido = ? AND p.id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL) AND p.cobrado = FALSE AND LOWER(e.nombre) = 'listo'");
    mysqli_stmt_bind_param($stmt, "i", $idPedido);
    mysqli_stmt_execute($stmt);
    $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$actualizado) responder_caja(array("error" => "El pedido no está listo para cobrar, ya fue cobrado o no pertenece a la caja abierta."), 409);
    responder_caja(array("ok" => true));
}

responder_caja(array("error" => "Acción no válida."), 400);
