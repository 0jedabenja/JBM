<?php
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
verificar_sesion_api();
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function responder_movimientos($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    responder_movimientos(array("error" => "Método no permitido."), 405);
}

$tipoEntidad = obtener_parametro_get("tipo");
$idEntidad = filter_var(obtener_parametro_get("id"), FILTER_VALIDATE_INT);
if (!in_array($tipoEntidad, array("insumo", "producto"), true) || $idEntidad === false || $idEntidad <= 0) {
    responder_movimientos(array("error" => "El producto o insumo solicitado no es válido."), 400);
}
verificar_permiso($tipoEntidad === "insumo" ? "insumos" : "productos", true);

$filtroTipo = obtener_parametro_get("movimiento", "todos");
$tiposPermitidos = $tipoEntidad === "insumo"
    ? array("todos", "saldo_inicial", "inventario", "compra")
    : array("todos", "venta");
if (!in_array($filtroTipo, $tiposPermitidos, true)) {
    responder_movimientos(array("error" => "El tipo de movimiento no es válido."), 400);
}

$desde = obtener_parametro_get("desde");
$hasta = obtener_parametro_get("hasta");
$fechaInicio = null;
$fechaFin = null;
foreach (array("desde" => $desde, "hasta" => $hasta) as $nombre => $valor) {
    if ($valor === "") continue;
    $fecha = DateTime::createFromFormat("!Y-m-d", $valor);
    $errores = DateTime::getLastErrors();
    if (!$fecha || ($errores && ($errores["warning_count"] || $errores["error_count"])) || $fecha->format("Y-m-d") !== $valor) {
        responder_movimientos(array("error" => "El rango de fechas no es válido."), 400);
    }
    if ($nombre === "desde") $fechaInicio = $fecha->format("Y-m-d 00:00:00");
    else $fechaFin = $fecha->modify("+1 day")->format("Y-m-d 00:00:00");
}
if ($fechaInicio !== null && $fechaFin !== null && $fechaInicio >= $fechaFin) {
    responder_movimientos(array("error" => "La fecha inicial debe ser anterior o igual a la final."), 400);
}

$pagina = max(1, (int)obtener_parametro_get("pagina", "1"));
$porPagina = 20;
$offset = ($pagina - 1) * $porPagina;
if ($tipoEntidad === "producto") {
    $sqlBase = "SELECT p.fecha_hora_creacion AS fecha_hora, 'venta' AS tipo,
                       c.cantidad, c.precio AS costo_unitario, c.total,
                       CONCAT('Pedido #', p.id_pedido) AS referencia,
                       CONCAT(e.nombre, ' ', e.apellido) AS responsable
                FROM Contiene c
                INNER JOIN Pedido p ON p.id_pedido = c.id_pedido
                INNER JOIN Empleado e ON e.id_empleado = p.id_empleado
                WHERE c.id_producto = ?";
} else {
    $sqlBase = "SELECT m.fecha_hora, m.tipo, m.cantidad, m.costo_unitario,
                       m.cantidad * m.costo_unitario AS total,
                       CASE
                           WHEN m.referencia_id IS NULL THEN '-'
                           WHEN m.tipo = 'compra' THEN CONCAT('Compra #', m.referencia_id)
                           ELSE CONCAT('Conteo #', m.referencia_id)
                       END AS referencia,
                       COALESCE(CONCAT(e.nombre, ' ', e.apellido), '-') AS responsable
                FROM Movimiento_Insumo m
                LEFT JOIN Empleado e ON e.id_empleado = m.id_empleado
                WHERE m.id_insumo = ?";
}

$condiciones = array();
$parametros = array($idEntidad);
$tiposBind = "i";
if ($filtroTipo !== "todos") {
    $condiciones[] = "tipo = ?";
    $parametros[] = $filtroTipo;
    $tiposBind .= "s";
}
if ($fechaInicio !== null) {
    $condiciones[] = "fecha_hora >= ?";
    $parametros[] = $fechaInicio;
    $tiposBind .= "s";
}
if ($fechaFin !== null) {
    $condiciones[] = "fecha_hora < ?";
    $parametros[] = $fechaFin;
    $tiposBind .= "s";
}
$sql = "SELECT * FROM ({$sqlBase}) movimientos";
if ($condiciones) $sql .= " WHERE " . implode(" AND ", $condiciones);
$sql .= " ORDER BY fecha_hora DESC LIMIT ? OFFSET ?";
$parametros[] = $porPagina + 1;
$parametros[] = $offset;
$tiposBind .= "ii";

try {
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("Error al preparar movimientos: " . mysqli_error($conexion));
        responder_movimientos(array("error" => "No se pudieron consultar los movimientos."), 500);
    }
    $argumentosBind = array($stmt, $tiposBind);
    foreach ($parametros as &$parametro) {
        $argumentosBind[] = &$parametro;
    }
    unset($parametro);
    call_user_func_array("mysqli_stmt_bind_param", $argumentosBind);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Error al consultar movimientos: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        responder_movimientos(array("error" => "No se pudieron consultar los movimientos."), 500);
    }
    $resultado = mysqli_stmt_get_result($stmt);
    $movimientos = array();
    while ($movimiento = mysqli_fetch_assoc($resultado)) $movimientos[] = $movimiento;
    mysqli_stmt_close($stmt);
} catch (mysqli_sql_exception $error) {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) mysqli_stmt_close($stmt);
    error_log("Error al consultar movimientos: " . $error->getMessage());
    responder_movimientos(array("error" => "No se pudieron consultar los movimientos. Verificá que la migración de auditoría y movimientos esté aplicada."), 500);
}
$hayMas = count($movimientos) > $porPagina;
if ($hayMas) array_pop($movimientos);
responder_movimientos(array("movimientos" => $movimientos, "pagina" => $pagina, "hay_mas" => $hayMas));
