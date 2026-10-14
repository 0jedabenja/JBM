<?php
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
verificar_permiso("auditoria", true);
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function responder_auditoria($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    responder_auditoria(array("error" => "Método no permitido."), 405);
}

$pagina = max(1, (int)obtener_parametro_get("pagina", "1"));
$porPagina = 100;
$offset = ($pagina - 1) * $porPagina;
$resultadoTotal = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM Auditoria");
if (!$resultadoTotal) {
    error_log("No se pudo contar la auditoría: " . mysqli_error($conexion));
    responder_auditoria(array("error" => "No se pudo consultar la auditoría."), 500);
}
$total = (int)mysqli_fetch_assoc($resultadoTotal)["total"];

$stmt = mysqli_prepare(
    $conexion,
    "SELECT a.id_auditoria, a.fecha_hora, a.accion, a.entidad, a.id_entidad,
            a.detalle, a.ip,
            COALESCE(CONCAT(e.nombre, ' ', e.apellido), 'Usuario eliminado') AS responsable,
            COALESCE(e.usuario, '-') AS usuario
     FROM Auditoria a
     LEFT JOIN Empleado e ON e.id_empleado = a.id_empleado
     ORDER BY a.fecha_hora DESC, a.id_auditoria DESC
     LIMIT ? OFFSET ?"
);
if (!$stmt) {
    error_log("No se pudo preparar la auditoría: " . mysqli_error($conexion));
    responder_auditoria(array("error" => "No se pudo consultar la auditoría."), 500);
}
mysqli_stmt_bind_param($stmt, "ii", $porPagina, $offset);
if (!mysqli_stmt_execute($stmt)) {
    error_log("No se pudo consultar la auditoría: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    responder_auditoria(array("error" => "No se pudo consultar la auditoría."), 500);
}
$resultado = mysqli_stmt_get_result($stmt);
$registros = array();
while ($registro = mysqli_fetch_assoc($resultado)) {
    $registro["detalle"] = $registro["detalle"] === null ? null : json_decode($registro["detalle"], true);
    $registros[] = $registro;
}
mysqli_stmt_close($stmt);
responder_auditoria(array(
    "registros" => $registros,
    "pagina" => $pagina,
    "por_pagina" => $porPagina,
    "total" => $total,
    "hay_anterior" => $pagina > 1,
    "hay_siguiente" => $offset + count($registros) < $total
));
