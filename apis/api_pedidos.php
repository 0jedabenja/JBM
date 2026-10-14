<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("pedidos", true);

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
    $sql = "SELECT c.id_producto, p.nombre, c.cantidad, c.precio, c.total, c.completado
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

function descontarStockPedido($conexion, $lineas, &$error, $forzar = false) {
    $necesarios = array();
    $lineasOrdenadas = $lineas;
    usort($lineasOrdenadas, function ($a, $b) {
        return $a["id_producto"] <=> $b["id_producto"];
    });
    $stmtReceta = mysqli_prepare($conexion, "SELECT c.id_insumo, c.cantidad, i.nombre
        FROM Compone c INNER JOIN Insumo i ON i.id_insumo = c.id_insumo
        WHERE c.id_producto = ? ORDER BY c.id_insumo");
    if (!$stmtReceta) {
        $error = "No se pudo consultar la receta de los productos.";
        return false;
    }
    foreach ($lineasOrdenadas as $linea) {
        $idProducto = $linea["id_producto"];
        $cantidadProducto = $linea["cantidad"];
        mysqli_stmt_bind_param($stmtReceta, "i", $idProducto);
        mysqli_stmt_execute($stmtReceta);
        $resultadoReceta = mysqli_stmt_get_result($stmtReceta);
        $encontroInsumo = false;
        while ($componente = mysqli_fetch_assoc($resultadoReceta)) {
            $encontroInsumo = true;
            $idInsumo = (int)$componente["id_insumo"];
            $cantidadPorProducto = (float)$componente["cantidad"];
            if ($cantidadPorProducto <= 0) {
                mysqli_stmt_close($stmtReceta);
                $error = "La receta del producto #{$idProducto} contiene una cantidad inválida.";
                return false;
            }
            if (!isset($necesarios[$idInsumo])) {
                $necesarios[$idInsumo] = array("nombre" => $componente["nombre"], "cantidad" => 0);
            }
            $necesarios[$idInsumo]["cantidad"] = round(
                $necesarios[$idInsumo]["cantidad"] + $cantidadPorProducto * $cantidadProducto,
                2
            );
        }
        if (!$encontroInsumo) {
            mysqli_stmt_close($stmtReceta);
            $error = "El producto #{$idProducto} no tiene insumos configurados para verificar su stock.";
            return false;
        }
    }
    mysqli_stmt_close($stmtReceta);

    ksort($necesarios, SORT_NUMERIC);
    $stmtStock = mysqli_prepare($conexion, "SELECT nombre, activo, cantidad_actual FROM Insumo WHERE id_insumo = ? FOR UPDATE");
    if (!$stmtStock) {
        $error = "No se pudo verificar el stock de los insumos.";
        return false;
    }
    $faltantes = array();
    foreach ($necesarios as $idInsumo => $necesario) {
        mysqli_stmt_bind_param($stmtStock, "i", $idInsumo);
        mysqli_stmt_execute($stmtStock);
        $stock = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtStock));
        if (!$stock || !(bool)$stock["activo"]) {
            mysqli_stmt_close($stmtStock);
            $error = "El insumo " . $necesario["nombre"] . " está inactivo y no se puede preparar el pedido.";
            return false;
        }
        $disponible = (float)$stock["cantidad_actual"];
        if ($disponible + 0.000001 < $necesario["cantidad"]) {
            $faltantes[] = $stock["nombre"] . " (necesario: " . number_format($necesario["cantidad"], 2, ",", ".") . ", disponible: " . number_format($disponible, 2, ",", ".") . ")";
        }
    }
    mysqli_stmt_close($stmtStock);
    if (count($faltantes) > 0 && !$forzar) {
        $error = "Stock insuficiente: " . implode("; ", $faltantes) . ".";
        return false;
    }

    $sqlDescontar = $forzar
        ? "UPDATE Insumo SET cantidad_actual = cantidad_actual - ? WHERE id_insumo = ? AND activo = TRUE"
        : "UPDATE Insumo SET cantidad_actual = cantidad_actual - ? WHERE id_insumo = ? AND activo = TRUE AND cantidad_actual >= ?";
    $stmtDescontar = mysqli_prepare($conexion, $sqlDescontar);
    if (!$stmtDescontar) {
        $error = "No se pudo descontar el stock de los insumos.";
        return false;
    }
    foreach ($necesarios as $idInsumo => $necesario) {
        $cantidadNecesaria = $necesario["cantidad"];
        if ($forzar) {
            mysqli_stmt_bind_param($stmtDescontar, "di", $cantidadNecesaria, $idInsumo);
        } else {
            mysqli_stmt_bind_param($stmtDescontar, "did", $cantidadNecesaria, $idInsumo, $cantidadNecesaria);
        }
        if (!mysqli_stmt_execute($stmtDescontar) || mysqli_stmt_affected_rows($stmtDescontar) !== 1) {
            mysqli_stmt_close($stmtDescontar);
            $error = "El stock cambió mientras se procesaba el pedido. Vuelve a intentarlo.";
            return false;
        }
    }
    mysqli_stmt_close($stmtDescontar);
    return true;
}

$metodo = $_SERVER["REQUEST_METHOD"];
$vista = $_GET["vista"] ?? "";
$condicionVista = "";
if ($vista === "cocina") {
    verificar_permiso("cocina", true);
    $condicionVista = " AND LOWER(ep.nombre) = 'en preparación'";
} elseif ($vista === "pendientes") {
    $condicionVista = " AND LOWER(ep.nombre) IN ('en preparación', 'listo')";
}

if ($metodo === "GET") {
    if (($_GET["caja"] ?? "") === "estado") {
        $resultadoCaja = mysqli_query($conexion, "SELECT id_turno, fecha_hora_apertura FROM Turno_caja WHERE fecha_hora_cierre IS NULL ORDER BY fecha_hora_apertura DESC LIMIT 1");
        responder(array("abierta" => mysqli_fetch_assoc($resultadoCaja) ?: null));
    }
    $limite = isset($_GET["limite"]) ? max(1, (int)$_GET["limite"]) : 20;
    $pagina = isset($_GET["pagina"]) ? max(1, (int)$_GET["pagina"]) : 1;
    $desde = ($pagina - 1) * $limite;
    $filtro = trim($_GET["filtro"] ?? "");

    if ($filtro !== "") {
        $sql = "SELECT p.id_pedido, p.descripcion, p.fecha_hora_creacion, p.fecha_hora_entrega,
                       p.monto_total, p.id_empleado, CONCAT(e.nombre, ' ', e.apellido) AS empleado,
                       p.id_turno, p.id_mesa, m.numero AS mesa, p.id_estado, ep.nombre AS estado,
                       p.id_cliente, CONCAT(c.nombre, ' ', c.apellido) AS cliente,
                       p.id_descuento
                FROM Pedido p
                INNER JOIN Empleado e ON e.id_empleado = p.id_empleado
                INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
                LEFT JOIN Mesa m ON m.id_mesa = p.id_mesa
                LEFT JOIN Cliente c ON c.id_cliente = p.id_cliente
                     WHERE (p.id_pedido LIKE ? OR m.numero LIKE ? OR ep.nombre LIKE ?
                         OR c.nombre LIKE ? OR c.apellido LIKE ?) {$condicionVista}
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
                       p.id_descuento
                FROM Pedido p
                INNER JOIN Empleado e ON e.id_empleado = p.id_empleado
                INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
                LEFT JOIN Mesa m ON m.id_mesa = p.id_mesa
                LEFT JOIN Cliente c ON c.id_cliente = p.id_cliente
                WHERE 1 = 1 {$condicionVista}
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

if ($metodo === "POST") {
    $entradaAccion = obtenerEntrada();
    $accion = $entradaAccion["accion"] ?? "";

    if ($accion === "completar_producto") {
        verificar_permiso("cocina", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        $idProducto = filter_var($entradaAccion["id_producto"] ?? null, FILTER_VALIDATE_INT);
        if ($idPedido === false || $idPedido <= 0 || $idProducto === false || $idProducto <= 0) {
            responder(array("error" => "Datos del producto no válidos."), 400);
        }
        $stmt = mysqli_prepare($conexion, "UPDATE Contiene SET completado = TRUE WHERE id_pedido = ? AND id_producto = ?");
        mysqli_stmt_bind_param($stmt, "ii", $idPedido, $idProducto);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        responder(array("ok" => true));
    }

    if ($accion === "finalizar") {
        verificar_permiso("cocina", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        if ($idPedido === false || $idPedido <= 0) responder(array("error" => "Pedido no válido."), 400);
        $stmt = mysqli_prepare($conexion, "UPDATE Pedido SET id_estado = (SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'listo' LIMIT 1) WHERE id_pedido = ? AND NOT EXISTS (SELECT 1 FROM Contiene c WHERE c.id_pedido = Pedido.id_pedido AND c.completado = FALSE)");
        mysqli_stmt_bind_param($stmt, "i", $idPedido);
        mysqli_stmt_execute($stmt);
        $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if (!$actualizado) responder(array("error" => "Todos los productos deben estar completos."), 409);
        $stmtMesa = mysqli_prepare($conexion, "UPDATE Mesa m INNER JOIN Pedido p ON p.id_mesa = m.id_mesa SET m.id_estado = (SELECT id_estado FROM Estado_Mesa WHERE LOWER(nombre) = 'para limpiar' LIMIT 1) WHERE p.id_pedido = ?");
        if ($stmtMesa) {
            mysqli_stmt_bind_param($stmtMesa, "i", $idPedido);
            mysqli_stmt_execute($stmtMesa);
            mysqli_stmt_close($stmtMesa);
        }
        responder(array("ok" => true));
    }

    if ($accion === "cancelar") {
        verificar_permiso("crear_pedido", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        if ($idPedido === false || $idPedido <= 0) responder(array("error" => "Pedido no válido."), 400);

        mysqli_begin_transaction($conexion);
        $stmtPedido = mysqli_prepare($conexion, "SELECT p.id_mesa, p.cobrado, LOWER(ep.nombre) AS estado
            FROM Pedido p INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
            WHERE p.id_pedido = ? FOR UPDATE");
        if (!$stmtPedido) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo validar el pedido."), 500);
        }
        mysqli_stmt_bind_param($stmtPedido, "i", $idPedido);
        mysqli_stmt_execute($stmtPedido);
        $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPedido));
        mysqli_stmt_close($stmtPedido);
        if (!$pedido || (bool)$pedido["cobrado"] || !in_array($pedido["estado"], array("en preparación", "listo"), true)) {
            mysqli_rollback($conexion);
            responder(array("error" => "Solo se pueden cancelar pedidos pendientes y sin cobrar."), 409);
        }

        $stmtCancelar = mysqli_prepare($conexion, "UPDATE Pedido SET id_estado =
            (SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'cancelado' LIMIT 1) WHERE id_pedido = ?");
        mysqli_stmt_bind_param($stmtCancelar, "i", $idPedido);
        $correcto = mysqli_stmt_execute($stmtCancelar);
        mysqli_stmt_close($stmtCancelar);
        if ($correcto && !empty($pedido["id_mesa"])) {
            $stmtMesa = mysqli_prepare($conexion, "UPDATE Mesa SET id_estado =
                (SELECT id_estado FROM Estado_Mesa WHERE LOWER(nombre) = 'libre' LIMIT 1)
                WHERE id_mesa = ? AND NOT EXISTS (
                    SELECT 1 FROM Pedido p INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
                    WHERE p.id_mesa = Mesa.id_mesa AND p.id_pedido <> ?
                    AND LOWER(ep.nombre) IN ('en preparación', 'listo'))");
            mysqli_stmt_bind_param($stmtMesa, "ii", $pedido["id_mesa"], $idPedido);
            $correcto = mysqli_stmt_execute($stmtMesa);
            mysqli_stmt_close($stmtMesa);
        }
        if (!$correcto) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo cancelar el pedido."), 500);
        }
        mysqli_commit($conexion);
        responder(array("ok" => true));
    }

    if ($accion === "editar") {
        verificar_permiso("crear_pedido", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        $productos = $entradaAccion["productos"] ?? array();
        $forzar = !empty($entradaAccion["forzar_stock"]);
        if ($idPedido === false || $idPedido <= 0 || !is_array($productos) || count($productos) === 0) {
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
            mysqli_stmt_bind_param($stmtPrecio, "i", $idProducto);
            mysqli_stmt_execute($stmtPrecio);
            $filaPrecio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPrecio));
            mysqli_stmt_close($stmtPrecio);
            if (!$filaPrecio) responder(array("error" => "El producto indicado no existe o está inactivo."), 400);
            $precio = (float)$filaPrecio["precio"];
            $total = $precio * $cantidad;
            $montoTotal += $total;
            $lineas[] = array("id_producto" => $idProducto, "cantidad" => $cantidad, "precio" => $precio, "total" => $total);
            $idsProductos[$idProducto] = true;
        }

        mysqli_begin_transaction($conexion);
        $stmtPedido = mysqli_prepare($conexion, "SELECT p.id_mesa, p.cobrado, LOWER(ep.nombre) AS estado
            FROM Pedido p INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
            WHERE p.id_pedido = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmtPedido, "i", $idPedido);
        mysqli_stmt_execute($stmtPedido);
        $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPedido));
        mysqli_stmt_close($stmtPedido);
        if (!$pedido || (bool)$pedido["cobrado"] || $pedido["estado"] !== "en preparación" || empty($pedido["id_mesa"])) {
            mysqli_rollback($conexion);
            responder(array("error" => "Solo se pueden editar pedidos en preparación y sin cobrar."), 409);
        }

        $stmtAnteriores = mysqli_prepare($conexion, "SELECT id_producto, cantidad FROM Contiene WHERE id_pedido = ? AND completado = TRUE LIMIT 1");
        mysqli_stmt_bind_param($stmtAnteriores, "i", $idPedido);
        mysqli_stmt_execute($stmtAnteriores);
        $productoIniciado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAnteriores));
        mysqli_stmt_close($stmtAnteriores);
        if ($productoIniciado) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se puede editar un pedido con productos ya iniciados en cocina."), 409);
        }

        $stmtAnteriores = mysqli_prepare($conexion, "SELECT id_producto, cantidad FROM Contiene WHERE id_pedido = ?");
        mysqli_stmt_bind_param($stmtAnteriores, "i", $idPedido);
        mysqli_stmt_execute($stmtAnteriores);
        $lineasAnteriores = array();
        $resultadoAnteriores = mysqli_stmt_get_result($stmtAnteriores);
        while ($linea = mysqli_fetch_assoc($resultadoAnteriores)) {
            $linea["cantidad"] = -(int)$linea["cantidad"];
            $lineasAnteriores[] = $linea;
        }
        mysqli_stmt_close($stmtAnteriores);

        $errorStock = "";
        if ($lineasAnteriores && !descontarStockPedido($conexion, $lineasAnteriores, $errorStock, true)) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo ajustar el stock anterior: " . $errorStock), 409);
        }
        if (!descontarStockPedido($conexion, $lineas, $errorStock, $forzar)) {
            mysqli_rollback($conexion);
            responder(array("error" => $errorStock), 409);
        }

        $stmtBorrar = mysqli_prepare($conexion, "DELETE FROM Contiene WHERE id_pedido = ?");
        mysqli_stmt_bind_param($stmtBorrar, "i", $idPedido);
        $correcto = mysqli_stmt_execute($stmtBorrar);
        mysqli_stmt_close($stmtBorrar);
        $stmtActualizar = mysqli_prepare($conexion, "UPDATE Pedido SET monto_total = ?, id_cliente = ? WHERE id_pedido = ?");
        $idCliente = empty($entradaAccion["id_cliente"]) ? null : (int)$entradaAccion["id_cliente"];
        if ($correcto && $stmtActualizar) {
            mysqli_stmt_bind_param($stmtActualizar, "dii", $montoTotal, $idCliente, $idPedido);
            $correcto = mysqli_stmt_execute($stmtActualizar);
        }
        if ($stmtActualizar) mysqli_stmt_close($stmtActualizar);
        $stmtContiene = mysqli_prepare($conexion, "INSERT INTO Contiene (id_pedido, id_producto, cantidad, precio, total) VALUES (?, ?, ?, ?, ?)");
        $correcto = $correcto && $stmtContiene !== false;
        if ($correcto) {
            foreach ($lineas as $linea) {
                mysqli_stmt_bind_param($stmtContiene, "iiidd", $idPedido, $linea["id_producto"], $linea["cantidad"], $linea["precio"], $linea["total"]);
                if (!mysqli_stmt_execute($stmtContiene)) {
                    $correcto = false;
                    break;
                }
            }
        }
        if ($stmtContiene) mysqli_stmt_close($stmtContiene);
        if (!$correcto) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo actualizar el pedido."), 500);
        }
        mysqli_commit($conexion);
        responder(array("ok" => true, "id_pedido" => $idPedido));
    }

    if ($accion === "agregar_productos") {
        verificar_permiso("crear_pedido", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        $idMesa = filter_var($entradaAccion["id_mesa"] ?? null, FILTER_VALIDATE_INT);
        $productos = $entradaAccion["productos"] ?? array();
        if ($idPedido === false || $idPedido <= 0 || $idMesa === false || $idMesa <= 0 || !is_array($productos) || count($productos) === 0) {
            responder(array("error" => "Faltan datos obligatorios del pedido."), 400);
        }

        $resultadoTurno = mysqli_query($conexion, "SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL ORDER BY fecha_hora_apertura DESC LIMIT 1");
        if (!mysqli_fetch_assoc($resultadoTurno)) responder(array("error" => "No hay una sesión de caja abierta."), 409);

        $lineas = array();
        $idsProductos = array();
        foreach ($productos as $producto) {
            $idProducto = filter_var($producto["id_producto"] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($producto["cantidad"] ?? null, FILTER_VALIDATE_INT);
            if ($idProducto === false || $idProducto <= 0 || $cantidad === false || $cantidad <= 0 || isset($idsProductos[$idProducto])) {
                responder(array("error" => "La lista de productos no es válida."), 400);
            }
            $stmtPrecio = mysqli_prepare($conexion, "SELECT precio FROM Producto WHERE id_producto = ? AND activo = TRUE");
            if (!$stmtPrecio) responder(array("error" => "No se pudo consultar el producto."), 500);
            mysqli_stmt_bind_param($stmtPrecio, "i", $idProducto);
            mysqli_stmt_execute($stmtPrecio);
            $filaPrecio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPrecio));
            mysqli_stmt_close($stmtPrecio);
            if (!$filaPrecio) responder(array("error" => "El producto indicado no existe o está inactivo."), 400);

            $precio = (float)$filaPrecio["precio"];
            $lineas[] = array("id_producto" => $idProducto, "cantidad" => $cantidad, "precio" => $precio, "total" => $precio * $cantidad);
            $idsProductos[$idProducto] = true;
        }

        mysqli_begin_transaction($conexion);
        $stmtPedidoActivo = mysqli_prepare($conexion, "SELECT p.id_pedido FROM Pedido p
            INNER JOIN Mesa m ON m.id_mesa = p.id_mesa
            INNER JOIN Estado_Mesa em ON em.id_estado = m.id_estado
            INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
            WHERE p.id_pedido = ? AND m.id_mesa = ? AND LOWER(em.nombre) = 'ocupada' AND LOWER(ep.nombre) = 'en preparación'
            FOR UPDATE");
        if (!$stmtPedidoActivo) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo validar el pedido."), 500);
        }
        mysqli_stmt_bind_param($stmtPedidoActivo, "ii", $idPedido, $idMesa);
        mysqli_stmt_execute($stmtPedidoActivo);
        $pedidoActivo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPedidoActivo));
        mysqli_stmt_close($stmtPedidoActivo);
        if (!$pedidoActivo) {
            mysqli_rollback($conexion);
            responder(array("error" => "El pedido ya no está abierto para agregar productos."), 409);
        }

        $errorStock = "";
        if (!descontarStockPedido($conexion, $lineas, $errorStock, !empty($entradaAccion["forzar_stock"]))) {
            mysqli_rollback($conexion);
            responder(array("error" => $errorStock), 409);
        }

        $stmtContiene = mysqli_prepare($conexion, "INSERT INTO Contiene (id_pedido, id_producto, cantidad, precio, total)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE precio = (total + VALUES(total)) / (cantidad + VALUES(cantidad)),
                cantidad = cantidad + VALUES(cantidad), total = total + VALUES(total), completado = FALSE");
        $stmtTotal = mysqli_prepare($conexion, "UPDATE Pedido SET monto_total = monto_total + ? WHERE id_pedido = ?");
        $correcto = $stmtContiene !== false && $stmtTotal !== false;
        $montoAdicional = 0;
        if ($correcto) {
            foreach ($lineas as $linea) {
                $idProducto = $linea["id_producto"];
                $cantidad = $linea["cantidad"];
                $precio = $linea["precio"];
                $total = $linea["total"];
                $montoAdicional += $total;
                mysqli_stmt_bind_param($stmtContiene, "iiidd", $idPedido, $idProducto, $cantidad, $precio, $total);
                if (!mysqli_stmt_execute($stmtContiene)) {
                    $correcto = false;
                    break;
                }
            }
            if ($correcto) {
                mysqli_stmt_bind_param($stmtTotal, "di", $montoAdicional, $idPedido);
                $correcto = mysqli_stmt_execute($stmtTotal);
            }
        }
        if ($stmtContiene) mysqli_stmt_close($stmtContiene);
        if ($stmtTotal) mysqli_stmt_close($stmtTotal);
        if (!$correcto) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudieron agregar los productos al pedido."), 500);
        }
        mysqli_commit($conexion);
        responder(array("ok" => true, "id_pedido" => $idPedido, "monto_adicional" => $montoAdicional));
    }
}

if ($metodo !== "POST") {
    responder(array("error" => "Método no permitido."), 405);
}

verificar_permiso("crear_pedido", true);
$entrada = obtenerEntrada();
$descripcion = trim($entrada["descripcion"] ?? "");
$fechaEntrega = trim($entrada["fecha_hora_entrega"] ?? "");
$fechaEntrega = $fechaEntrega === "" ? null : $fechaEntrega;
$idMesa = filter_var($entrada["id_mesa"] ?? null, FILTER_VALIDATE_INT);
$idCliente = empty($entrada["id_cliente"]) ? null : filter_var($entrada["id_cliente"], FILTER_VALIDATE_INT);
$idDescuento = empty($entrada["id_descuento"]) ? null : filter_var($entrada["id_descuento"], FILTER_VALIDATE_INT);
$productos = $entrada["productos"] ?? array();
$forzarStock = !empty($entrada["forzar_stock"]);
$idEmpleado = (int) $_SESSION["usuario_id"];

if ($idMesa === false || $idMesa <= 0 ||
    !is_array($productos) || count($productos) === 0) {
    responder(array("error" => "Faltan datos obligatorios del pedido."), 400);
}

$resultadoTurno = mysqli_query($conexion, "SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL ORDER BY fecha_hora_apertura DESC LIMIT 1");
$turnoAbierto = mysqli_fetch_assoc($resultadoTurno);
if (!$turnoAbierto) responder(array("error" => "No hay una sesión de caja abierta."), 409);
$idTurno = (int) $turnoAbierto["id_turno"];

$stmtEstado = mysqli_prepare($conexion, "SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'en preparación' LIMIT 1");
mysqli_stmt_execute($stmtEstado);
$resultadoEstado = mysqli_stmt_get_result($stmtEstado);
$estadoPreparacion = mysqli_fetch_assoc($resultadoEstado);
mysqli_stmt_close($stmtEstado);
if (!$estadoPreparacion) responder(array("error" => "No existe el estado En preparación."), 500);
$idEstado = (int) $estadoPreparacion["id_estado"];

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
$errorStock = "";
if (!descontarStockPedido($conexion, $lineas, $errorStock, $forzarStock)) {
    mysqli_rollback($conexion);
    responder(array("error" => $errorStock), 409);
}
$stmtMesaLibre = mysqli_prepare($conexion, "UPDATE Mesa SET id_estado = (SELECT id_estado FROM Estado_Mesa WHERE LOWER(nombre) = 'ocupada' LIMIT 1) WHERE id_mesa = ? AND id_estado = (SELECT id_estado FROM Estado_Mesa WHERE LOWER(nombre) = 'libre' LIMIT 1)");
if ($stmtMesaLibre) {
    mysqli_stmt_bind_param($stmtMesaLibre, "i", $idMesa);
    $correcto = mysqli_stmt_execute($stmtMesaLibre) && mysqli_stmt_affected_rows($stmtMesaLibre) === 1;
    mysqli_stmt_close($stmtMesaLibre);
}
if (!$correcto) {
    mysqli_rollback($conexion);
    responder(array("error" => "La mesa seleccionada no está libre o ya fue tomada por otro usuario."), 409);
}
$sqlPedido = "INSERT INTO Pedido (descripcion, fecha_hora_entrega, monto_total, id_empleado, id_turno, id_mesa, id_estado, id_cliente, id_descuento)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmtPedido = mysqli_prepare($conexion, $sqlPedido);
if ($stmtPedido) {
    mysqli_stmt_bind_param($stmtPedido, "ssdiiiiii", $descripcion, $fechaEntrega, $montoTotal, $idEmpleado, $idTurno, $idMesa, $idEstado, $idCliente, $idDescuento);
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

if (!$correcto) {
    mysqli_rollback($conexion);
    responder(array("error" => "No se pudo crear el pedido."), 500);
}

mysqli_commit($conexion);
responder(array("ok" => true, "id_pedido" => $idPedido, "monto_total" => $montoTotal), 201);