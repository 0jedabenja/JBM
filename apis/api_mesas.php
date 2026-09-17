<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
header("Content-Type: application/json; charset=utf-8");

function responder_mesas($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

function entrada_mesas() {
    $contenido = file_get_contents("php://input");
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : $_POST;
}

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo === "GET") {
    $mesas = array();
    $resultado = mysqli_query($conexion, "SELECT m.id_mesa, m.numero, m.pos_x, m.pos_y, m.ancho, m.id_estado, e.nombre AS estado FROM Mesa m INNER JOIN Estado_Mesa e ON e.id_estado = m.id_estado ORDER BY m.numero");
    while ($mesa = mysqli_fetch_assoc($resultado)) $mesas[] = $mesa;
    $estados = array();
    $resultadoEstados = mysqli_query($conexion, "SELECT id_estado, nombre FROM Estado_Mesa ORDER BY nombre");
    while ($estado = mysqli_fetch_assoc($resultadoEstados)) $estados[] = $estado;
    responder_mesas(array("mesas" => $mesas, "estados" => $estados));
}

verificar_permiso("empleados", true);
$entrada = entrada_mesas();

if ($metodo === "POST") {
    $numero = filter_var($entrada["numero"] ?? null, FILTER_VALIDATE_INT);
    $posX = filter_var($entrada["pos_x"] ?? 0, FILTER_VALIDATE_INT);
    $posY = filter_var($entrada["pos_y"] ?? 0, FILTER_VALIDATE_INT);
    $ancho = filter_var($entrada["ancho"] ?? 100, FILTER_VALIDATE_INT);
    if ($numero === false || $numero <= 0 || $posX === false || $posY === false || $ancho === false || $ancho < 60) {
        responder_mesas(array("error" => "Los datos de la mesa no son válidos."), 400);
    }
    $stmtNumero = mysqli_prepare($conexion, "SELECT id_mesa FROM Mesa WHERE numero = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtNumero, "i", $numero);
    mysqli_stmt_execute($stmtNumero);
    $numeroExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNumero));
    mysqli_stmt_close($stmtNumero);
    if ($numeroExiste) responder_mesas(array("error" => "Ya existe una mesa con ese número."), 409);
    $consultaEstado = mysqli_prepare($conexion, "SELECT id_estado FROM Estado_Mesa WHERE LOWER(nombre) = 'libre' LIMIT 1");
    mysqli_stmt_execute($consultaEstado);
    $resultadoEstado = mysqli_stmt_get_result($consultaEstado);
    $estadoLibre = mysqli_fetch_assoc($resultadoEstado);
    mysqli_stmt_close($consultaEstado);
    if (!$estadoLibre) responder_mesas(array("error" => "No existe el estado Libre en la base de datos."), 500);
    $idEstado = (int) $estadoLibre["id_estado"];
    $stmt = mysqli_prepare($conexion, "INSERT INTO Mesa (numero, pos_x, pos_y, ancho, id_estado) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiiii", $numero, $posX, $posY, $ancho, $idEstado);
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); responder_mesas(array("error" => "No se pudo crear la mesa."), 409); }
    $idMesa = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);
    responder_mesas(array("ok" => true, "id_mesa" => $idMesa), 201);
}

if ($metodo === "DELETE") {
    $idMesa = filter_var($entrada["id_mesa"] ?? null, FILTER_VALIDATE_INT);
    if ($idMesa === false || $idMesa <= 0) responder_mesas(array("error" => "La mesa no es válida."), 400);
    $stmt = mysqli_prepare($conexion, "DELETE FROM Mesa WHERE id_mesa = ?");
    mysqli_stmt_bind_param($stmt, "i", $idMesa);
    $ejecutado = mysqli_stmt_execute($stmt);
    $eliminada = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$ejecutado) responder_mesas(array("error" => "No se puede eliminar una mesa con pedidos relacionados."), 409);
    if (!$eliminada) responder_mesas(array("error" => "La mesa no existe."), 404);
    responder_mesas(array("ok" => true));
}

if ($metodo === "PATCH") {
    $idMesa = filter_var($entrada["id_mesa"] ?? null, FILTER_VALIDATE_INT);
    $numero = filter_var($entrada["numero"] ?? null, FILTER_VALIDATE_INT);
    $posX = filter_var($entrada["pos_x"] ?? null, FILTER_VALIDATE_INT);
    $posY = filter_var($entrada["pos_y"] ?? null, FILTER_VALIDATE_INT);
    $ancho = filter_var($entrada["ancho"] ?? null, FILTER_VALIDATE_INT);
    $idEstado = filter_var($entrada["id_estado"] ?? null, FILTER_VALIDATE_INT);
    if ($idMesa === false || $idMesa <= 0 || $numero === false || $numero <= 0 || $posX === false || $posY === false || $ancho === false || $ancho < 60 || $idEstado === false || $idEstado <= 0) responder_mesas(array("error" => "Los datos de la mesa no son válidos."), 400);
    $stmtNumero = mysqli_prepare($conexion, "SELECT id_mesa FROM Mesa WHERE numero = ? AND id_mesa <> ? LIMIT 1");
    mysqli_stmt_bind_param($stmtNumero, "ii", $numero, $idMesa);
    mysqli_stmt_execute($stmtNumero);
    $numeroExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNumero));
    mysqli_stmt_close($stmtNumero);
    if ($numeroExiste) responder_mesas(array("error" => "Ya existe otra mesa con ese número."), 409);
    $stmt = mysqli_prepare($conexion, "UPDATE Mesa SET numero = ?, pos_x = ?, pos_y = ?, ancho = ?, id_estado = ? WHERE id_mesa = ?");
    mysqli_stmt_bind_param($stmt, "iiiiii", $numero, $posX, $posY, $ancho, $idEstado, $idMesa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    responder_mesas(array("ok" => true));
}

responder_mesas(array("error" => "Método no permitido."), 405);
