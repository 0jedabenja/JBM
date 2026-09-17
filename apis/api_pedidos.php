<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();

header('Content-Type: application/json; charset=utf-8');

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

function obtenerEntrada() {
    $contenido = file_get_contents("php://input");
    if ($contenido !== "") {
        $datos = json_decode($contenido, true);
        if (is_array($datos)) {
            return $datos;
        }
    }
    return $_POST;
}

function obtenerProductosPedido($conexion, $id_pedido) {
    $productos = array();
    $sql = "SELECT c.id_producto, p.nombre, c.cantidad, c.precio, c.total
            FROM Contiene c
            INNER JOIN Producto p ON p.id_producto = c.id_producto
            WHERE c.id_pedido = ?
            ORDER BY p.nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        return $productos;
    }

    mysqli_stmt_bind_param($stmt, "i", $id_pedido);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($resultado)) {
        $productos[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $productos;
}

$metodo = $_SERVER["REQUEST_METHOD"];

if ($metodo === "GET") {
    $limite = isset($_GET["limite"]) ? max(1, (int)$_GET["limite"]) : 20;
    $pagina = isset($_GET["pagina"]) ? max(1, (int)$_GET["pagina"]) : 1;
    $desde = ($pagina - 1) * $limite;
    $filtro = trim($_GET["filtro"] ?? "");

    if ($filtro !== "") {
        $sql = "SELECT p.id_pedido, p.descripcion, p.fecha_hora_creacion, p.fecha_hora_entrega,
                       p.monto_total, p.id_empleado, CONCAT(e.nombre, ' ', e.apellido) AS empleado,
                       p.id_turno, p.id_mesa, m.numero AS mesa, p.id_estado, ep.nombre AS estado,
                       p.id_cliente, CONCAT(c.nombre, ' ', c.apellido) AS cliente,
                       p.id_descuento, p.id_mp, mp.descripcion AS metodo_pago
                FROM Pedido p
                INNER JOIN Empleado e ON e.id_empleado = p.id_empleado
                INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
                INNER JOIN Metodo_pago mp ON mp.id_mp = p.id_mp
                LEFT JOIN Mesa m ON m.id_mesa = p.id_mesa
                LEFT JOIN Cliente c ON c.id_cliente = p.id_cliente
                WHERE p.id_pedido LIKE ? OR m.numero LIKE ? OR ep.nombre LIKE ?
                   OR c.nombre LIKE ? OR c.apellido LIKE ?
                ORDER BY p.fecha_hora_creacion DESC
                LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            responder(array("error" => "No se pudo preparar la consulta."), 500);
        }
        $busqueda = "%" . $filtro . "%";
        mysqli_stmt_bind_param($stmt, "sssssii", $busqueda, $busqueda, $busqueda, $busqueda, $busqueda, $limite, $desde);
    } else {
        $sql = "SELECT p.id_pedido, p.descripcion, p.fecha_hora_creacion, p.fecha_hora_entrega,
                       p.monto_total, p.id_empleado, CONCAT(e.nombre, ' ', e.apellido) AS empleado,
                       p.id_turno, p.id_mesa, m.numero AS mesa, p.id_estado, ep.nombre AS estado,
                       p.id_cliente, CONCAT(c.nombre, ' ', c.apellido) AS cliente,
                       p.id_descuento, p.id_mp, mp.descripcion AS metodo_pago
                FROM Pedido p
                INNER JOIN Empleado e ON e.id_empleado = p.id_empleado
                INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
                INNER JOIN Metodo_pago mp ON mp.id_mp = p.id_mp
                LEFT JOIN Mesa m ON m.id_mesa = p.id_mesa
                LEFT JOIN Cliente c ON c.id_cliente = p.id_cliente
                ORDER BY p.fecha_hora_creacion DESC
                LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            responder(array("error" => "No se pudo preparar la consulta."), 500);
        }
        mysqli_stmt_bind_param($stmt, "ii", $limite, $desde);
    }

    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $pedidos = array();
    while ($pedido = mysqli_fetch_assoc($resultado)) {
        $pedido["productos"] = obtenerProductosPedido($conexion, (int)$pedido["id_pedido"]);
        $pedidos[] = $pedido;
    }
    mysqli_stmt_close($stmt);
    responder($pedidos);
}

if ($metodo !== "POST") {
    responder(array("error" => "Método no permitido."), 405);
}

$entrada = obtenerEntrada();
$descripcion = trim($entrada["descripcion"] ?? "");
$fechaEntrega = trim($entrada["fecha_hora_entrega"] ?? "");
$fechaEntrega = $fechaEntrega === "" ? null : $fechaEntrega;
$idEmpleado = filter_var($entrada["id_empleado"] ?? null, FILTER_VALIDATE_INT);
$idTurno = filter_var($entrada["id_turno"] ?? null, FILTER_VALIDATE_INT);
$idMesa = filter_var($entrada["id_mesa"] ?? null, FILTER_VALIDATE_INT);
$idEstado = filter_var($entrada["id_estado"] ?? null, FILTER_VALIDATE_INT);
$idCliente = empty($entrada["id_cliente"]) ? null : filter_var($entrada["id_cliente"], FILTER_VALIDATE_INT);
$idDescuento = empty($entrada["id_descuento"]) ? null : filter_var($entrada["id_descuento"], FILTER_VALIDATE_INT);
$idMetodoPago = filter_var($entrada["id_mp"] ?? null, FILTER_VALIDATE_INT);
$productos = $entrada["productos"] ?? array();

if ($idEmpleado === false || $idEmpleado <= 0 || $idTurno === false || $idTurno <= 0 ||
    $idMesa === false || $idMesa <= 0 || $idEstado === false || $idEstado <= 0 ||
    $idMetodoPago === false || $idMetodoPago <= 0 || !is_array($productos) || count($productos) === 0) {
    responder(array("error" => "Faltan datos obligatorios del pedido."), 400);
}

$lineas = array();
$idsProductos = array();
$montoTotal = 0;
foreach ($productos as $producto) {
    $idProducto = filter_var($producto["id_producto"] ?? null, FILTER_VALIDATE_INT);
    $cantidad = filter_var($producto["cantidad"] ?? null, FILTER_VALIDATE_INT);
    if ($idProducto === false || $idProducto <= 0 || $cantidad === false || $cantidad <= 0 || isset($idsProductos[$idProducto])) {
        responder(array("error" => "La lista de productos no es válida."), 400);
    }

    $stmtPrecio = mysqli_prepare($conexion, "SELECT precio FROM Producto WHERE id_producto = ? AND activo = TRUE");
    if (!$stmtPrecio) {
        responder(array("error" => "No se pudo consultar el producto."), 500);
    }
    mysqli_stmt_bind_param($stmtPrecio, "i", $idProducto);
    mysqli_stmt_execute($stmtPrecio);
    $resultadoPrecio = mysqli_stmt_get_result($stmtPrecio);
    $filaPrecio = mysqli_fetch_assoc($resultadoPrecio);
    mysqli_stmt_close($stmtPrecio);
    if (!$filaPrecio) {
        responder(array("error" => "El producto indicado no existe o está inactivo."), 400);
    }

    $precio = (float)$filaPrecio["precio"];
    $total = $precio * $cantidad;
    $montoTotal += $total;
    $idsProductos[$idProducto] = true;
    $lineas[] = array("id_producto" => $idProducto, "cantidad" => $cantidad, "precio" => $precio, "total" => $total);
}

mysqli_begin_transaction($conexion);
$correcto = true;
$sqlPedido = "INSERT INTO Pedido (descripcion, fecha_hora_entrega, monto_total, id_empleado, id_turno, id_mesa, id_estado, id_cliente, id_descuento, id_mp)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmtPedido = mysqli_prepare($conexion, $sqlPedido);
if ($stmtPedido) {
    mysqli_stmt_bind_param($stmtPedido, "ssdiiiiiii", $descripcion, $fechaEntrega, $montoTotal, $idEmpleado, $idTurno, $idMesa, $idEstado, $idCliente, $idDescuento, $idMetodoPago);
    $correcto = mysqli_stmt_execute($stmtPedido);
    $idPedido = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtPedido);
} else {
    $correcto = false;
}

if ($correcto) {
    $stmtContiene = mysqli_prepare($conexion, "INSERT INTO Contiene (id_pedido, id_producto, cantidad, precio, total) VALUES (?, ?, ?, ?, ?)");
    $correcto = $stmtContiene !== false;
    if ($correcto) {
        foreach ($lineas as $linea) {
            $idProducto = $linea["id_producto"];
            $cantidad = $linea["cantidad"];
            $precio = $linea["precio"];
            $total = $linea["total"];
            mysqli_stmt_bind_param($stmtContiene, "iiidd", $idPedido, $idProducto, $cantidad, $precio, $total);
            if (!mysqli_stmt_execute($stmtContiene)) {
                $correcto = false;
                break;
            }
        }
        mysqli_stmt_close($stmtContiene);
    }
}

if ($correcto) {
    $stmtMesa = mysqli_prepare($conexion, "SELECT id_estado FROM Estado_Mesa WHERE LOWER(nombre) = 'ocupada' LIMIT 1");
    if ($stmtMesa) {
        mysqli_stmt_execute($stmtMesa);
        $resultadoEstadoMesa = mysqli_stmt_get_result($stmtMesa);
        $estadoOcupada = mysqli_fetch_assoc($resultadoEstadoMesa);
        mysqli_stmt_close($stmtMesa);
        if ($estadoOcupada) {
            $stmtActualizarMesa = mysqli_prepare($conexion, "UPDATE Mesa SET id_estado = ? WHERE id_mesa = ?");
            if ($stmtActualizarMesa) {
                $idEstadoOcupada = (int)$estadoOcupada["id_estado"];
                mysqli_stmt_bind_param($stmtActualizarMesa, "ii", $idEstadoOcupada, $idMesa);
                $correcto = mysqli_stmt_execute($stmtActualizarMesa);
                mysqli_stmt_close($stmtActualizarMesa);
            }
        }
    }
}

if (!$correcto) {
    mysqli_rollback($conexion);
    responder(array("error" => "No se pudo crear el pedido."), 500);
}

mysqli_commit($conexion);
responder(array("ok" => true, "id_pedido" => $idPedido, "monto_total" => $montoTotal), 201);