<?php
require_once __DIR__ . "/../includes/api_json.php";
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
$condiciones = array();
$tipos = "";
$valores = array();

$usuarioFiltro = obtener_parametro_get("usuario");
if ($usuarioFiltro !== "") {
    $condiciones[] = "(CONCAT(e.nombre, ' ', e.apellido) LIKE ? OR e.usuario LIKE ?)";
    $like = "%" . $usuarioFiltro . "%";
    $tipos .= "ss";
    array_push($valores, $like, $like);
}
$accionFiltro = obtener_parametro_get("accion");
if ($accionFiltro !== "") {
    $condiciones[] = "a.accion = ?";
    $tipos .= "s";
    $valores[] = $accionFiltro;
}
$entidadFiltro = obtener_parametro_get("entidad");
if ($entidadFiltro !== "") {
    $condiciones[] = "a.entidad = ?";
    $tipos .= "s";
    $valores[] = $entidadFiltro;
}
$desde = obtener_parametro_get("desde");
if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $desde)) {
    $condiciones[] = "a.fecha_hora >= ?";
    $tipos .= "s";
    $valores[] = str_replace("T", " ", $desde) . ":00";
}
$hasta = obtener_parametro_get("hasta");
if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $hasta)) {
    $condiciones[] = "a.fecha_hora <= ?";
    $tipos .= "s";
    $valores[] = str_replace("T", " ", $hasta) . ":59";
}
$where = $condiciones ? " WHERE " . implode(" AND ", $condiciones) : "";
$desdeSql = " FROM Auditoria a LEFT JOIN Empleado e ON e.id_empleado = a.id_empleado" . $where;

$stmtTotal = mysqli_prepare($conexion, "SELECT COUNT(*) AS total" . $desdeSql);
if ($stmtTotal && $valores) {
    mysqli_stmt_bind_param($stmtTotal, $tipos, ...$valores);
}
if (!$stmtTotal || !mysqli_stmt_execute($stmtTotal)) {
    error_log("No se pudo contar la auditoría: " . mysqli_error($conexion));
    responder_auditoria(array("error" => "No se pudo consultar la auditoría."), 500);
}
$total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTotal))["total"];
mysqli_stmt_close($stmtTotal);

$opciones = array("acciones" => array(), "entidades" => array());
$resAcciones = mysqli_query($conexion, "SELECT DISTINCT accion FROM Auditoria ORDER BY accion");
while ($resAcciones && ($fila = mysqli_fetch_assoc($resAcciones))) {
    $opciones["acciones"][] = $fila["accion"];
}
$resEntidades = mysqli_query($conexion, "SELECT DISTINCT entidad FROM Auditoria ORDER BY entidad");
while ($resEntidades && ($fila = mysqli_fetch_assoc($resEntidades))) {
    $opciones["entidades"][] = $fila["entidad"];
}

$stmt = mysqli_prepare(
    $conexion,
    "SELECT a.id_auditoria, a.fecha_hora, a.accion, a.entidad, a.id_entidad,
            a.detalle, a.ip,
            COALESCE(CONCAT(e.nombre, ' ', e.apellido), 'Usuario eliminado') AS responsable,
            COALESCE(e.usuario, '-') AS usuario" . $desdeSql . "
     ORDER BY a.fecha_hora DESC, a.id_auditoria DESC
     LIMIT ? OFFSET ?"
);
if (!$stmt) {
    error_log("No se pudo preparar la auditoría: " . mysqli_error($conexion));
    responder_auditoria(array("error" => "No se pudo consultar la auditoría."), 500);
}
$valoresPagina = array_merge($valores, array($porPagina, $offset));
mysqli_stmt_bind_param($stmt, $tipos . "ii", ...$valoresPagina);
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
    "opciones" => $opciones,
    "hay_anterior" => $pagina > 1,
    "hay_siguiente" => $offset + count($registros) < $total
));
