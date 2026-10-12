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

function coordenada_mesa($valor) {
    if (!is_numeric($valor) || !is_finite((float)$valor)) return false;
    return (int)round((float)$valor);
}

function posicion_mesa_disponible($x, $y, $ancho, $posiciones) {
    foreach ($posiciones as $otra) {
        $separacion = 24;
        $seSuperpone = $x < $otra["x"] + $otra["ancho"] + $separacion
            && $x + $ancho + $separacion > $otra["x"]
            && $y < $otra["y"] + 80 + $separacion
            && $y + 80 + $separacion > $otra["y"];
        if ($seSuperpone) return false;
    }

    return true;
}

function buscar_posicion_mesa($ancho, $posiciones) {
    for ($y = 40; $y <= 620; $y += 130) {
        for ($x = 40; $x <= 1200 - $ancho; $x += 180) {
            if (posicion_mesa_disponible($x, $y, $ancho, $posiciones)) {
                return array("x" => $x, "y" => $y);
            }
        }
    }

    return null;
}

function cargar_mesas() {
    global $conexion;
    $mesas = array();
    $posiciones = array();
    $resultado = mysqli_query($conexion, "SELECT m.id_mesa, m.numero, m.pos_x, m.pos_y, m.ancho, m.id_estado, e.nombre AS estado FROM Mesa m INNER JOIN Estado_Mesa e ON e.id_estado = m.id_estado ORDER BY m.numero");

    while ($mesa = mysqli_fetch_assoc($resultado)) {
        $ancho = 100;
        $x = min(1200 - $ancho, max(0, (int)($mesa["pos_x"] ?? 0)));
        $y = min(620, max(0, (int)($mesa["pos_y"] ?? 0)));

        if (!posicion_mesa_disponible($x, $y, $ancho, $posiciones)) {
            $posicion = buscar_posicion_mesa($ancho, $posiciones);
            if ($posicion !== null) {
                $x = $posicion["x"];
                $y = $posicion["y"];
            }
        }

        $mesa["pos_x"] = $x;
        $mesa["pos_y"] = $y;
        $mesa["ancho"] = $ancho;
        $mesas[] = $mesa;
        $posiciones[] = array("x" => $x, "y" => $y, "ancho" => $ancho);
    }

    return $mesas;
}

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo === "GET") {
    $mesas = cargar_mesas();
    responder_mesas(array("mesas" => $mesas));
}

$rolActual = obtener_rol_sesion();
if ($rolActual !== "administrador") {
    responder_mesas(array("error" => "Solo el administrador puede modificar mesas."), 403);
}

$entrada = entrada_mesas();

if ($metodo === "POST") {
    $numero = filter_var($entrada["numero"] ?? null, FILTER_VALIDATE_INT);
    if ($numero === false || $numero <= 0) {
        responder_mesas(array("error" => "Los datos de la mesa no son válidos."), 400);
    }

    $stmtNumero = mysqli_prepare($conexion, "SELECT id_mesa FROM Mesa WHERE numero = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtNumero, "i", $numero);
    mysqli_stmt_execute($stmtNumero);
    $numeroExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNumero));
    mysqli_stmt_close($stmtNumero);
    if ($numeroExiste) responder_mesas(array("error" => "Ya existe una mesa con ese número."), 409);

    $mesasActuales = cargar_mesas();
    $posiciones = array_map(function ($mesa) {
        return array("x" => (int)$mesa["pos_x"], "y" => (int)$mesa["pos_y"], "ancho" => 100);
    }, $mesasActuales);
    $posicion = buscar_posicion_mesa(100, $posiciones);
    if ($posicion === null) responder_mesas(array("error" => "No hay espacio disponible para otra mesa."), 409);

    $posX = $posicion["x"];
    $posY = $posicion["y"];
    $ancho = 100;
    $idEstado = 1;
    $stmt = mysqli_prepare($conexion, "INSERT INTO Mesa (numero, pos_x, pos_y, ancho, id_estado) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiiii", $numero, $posX, $posY, $ancho, $idEstado);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        responder_mesas(array("error" => "No se pudo crear la mesa."), 409);
    }
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
    if (!$ejecutado) responder_mesas(array("error" => "No se pudo eliminar la mesa."), 409);
    if (!$eliminada) responder_mesas(array("error" => "La mesa no existe."), 404);
    responder_mesas(array("ok" => true));
}

if ($metodo === "PATCH") {
    $idMesa = filter_var($entrada["id_mesa"] ?? null, FILTER_VALIDATE_INT);
    $editaNumero = array_key_exists("numero", $entrada);
    $editaPosicion = array_key_exists("pos_x", $entrada) && array_key_exists("pos_y", $entrada);
    $numero = $editaNumero ? filter_var($entrada["numero"], FILTER_VALIDATE_INT) : null;
    $posX = $editaPosicion ? coordenada_mesa($entrada["pos_x"]) : null;
    $posY = $editaPosicion ? coordenada_mesa($entrada["pos_y"]) : null;

    if ($idMesa === false || $idMesa <= 0 || (!$editaNumero && !$editaPosicion) || ($editaNumero && ($numero === false || $numero <= 0)) || (array_key_exists("pos_x", $entrada) !== array_key_exists("pos_y", $entrada)) || ($editaPosicion && ($posX === false || $posY === false || $posX < 0 || $posY < 0 || $posX > 1100 || $posY > 620))) {
        responder_mesas(array("error" => "Los datos de la mesa no son válidos."), 400);
    }

    if ($editaNumero) {
        $stmtNumero = mysqli_prepare($conexion, "SELECT id_mesa FROM Mesa WHERE numero = ? AND id_mesa <> ? LIMIT 1");
        mysqli_stmt_bind_param($stmtNumero, "ii", $numero, $idMesa);
        mysqli_stmt_execute($stmtNumero);
        $numeroExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNumero));
        mysqli_stmt_close($stmtNumero);
        if ($numeroExiste) responder_mesas(array("error" => "Ya existe otra mesa con ese número."), 409);
    }

    if ($editaNumero && $editaPosicion) {
        $stmt = mysqli_prepare($conexion, "UPDATE Mesa SET numero = ?, pos_x = ?, pos_y = ? WHERE id_mesa = ?");
        mysqli_stmt_bind_param($stmt, "iiii", $numero, $posX, $posY, $idMesa);
    } elseif ($editaNumero) {
        $stmt = mysqli_prepare($conexion, "UPDATE Mesa SET numero = ? WHERE id_mesa = ?");
        mysqli_stmt_bind_param($stmt, "ii", $numero, $idMesa);
    } else {
        $stmt = mysqli_prepare($conexion, "UPDATE Mesa SET pos_x = ?, pos_y = ? WHERE id_mesa = ?");
        mysqli_stmt_bind_param($stmt, "iii", $posX, $posY, $idMesa);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    responder_mesas(array("ok" => true));
}

responder_mesas(array("error" => "Método no permitido."), 405);
