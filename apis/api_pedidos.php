<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("pedidos", true);

header('Content-Type: application/json; charset=utf-8');

function responder($datos, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

function obtenerEntrada()
{
    $contenido = file_get_contents("php://input");
    if ($contenido !== "") {
        $datos = json_decode($contenido, true);
        if (is_array($datos)) {
            return $datos;
        }
    }
    return $_POST;
}

function obtenerProductosPedido($conexion, $id_pedido, $soloCocina = false)
{
    $productos = array();
    $sql = "SELECT c.id_producto, p.nombre, c.cantidad, c.precio, c.total, c.completado,
                   (LOWER(COALESCE(cat.titulo, '')) = 'bebidas') AS es_bebida
            FROM Contiene c
            INNER JOIN Producto p ON p.id_producto = c.id_producto
            LEFT JOIN Categoria cat ON cat.id_categoria = p.id_categoria
            WHERE c.id_pedido = ?";
    if ($soloCocina) {
        $sql .= " AND LOWER(COALESCE(cat.titulo, '')) <> 'bebidas'";
    }
    $sql .= " ORDER BY p.nombre";
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

function obtenerFaltantesStock($conexion, $productos)
{
    $requeridoPorInsumo = array();
    $sqlReceta = "SELECT cp.id_insumo, i.nombre, i.cantidad_actual, m.simbolo,
                         SUM(cp.cantidad * ?) AS cantidad_requerida
                  FROM Compone cp
                  INNER JOIN Insumo i ON i.id_insumo = cp.id_insumo
                  LEFT JOIN Medida m ON m.id_medida = i.id_medida
                  WHERE cp.id_producto = ?
                  GROUP BY cp.id_insumo, i.nombre, i.cantidad_actual, m.simbolo";
    $stmtReceta = mysqli_prepare($conexion, $sqlReceta);
    if (!$stmtReceta) {
        throw new RuntimeException("No se pudo comprobar el inventario.");
    }

    foreach ($productos as $producto) {
        $cantidadProducto = (int) $producto["cantidad"];
        $idProducto = (int) $producto["id_producto"];
        mysqli_stmt_bind_param($stmtReceta, "di", $cantidadProducto, $idProducto);
        if (!mysqli_stmt_execute($stmtReceta)) {
            mysqli_stmt_close($stmtReceta);
            throw new RuntimeException("No se pudo comprobar el inventario.");
        }
        $resultadoReceta = mysqli_stmt_get_result($stmtReceta);
        while ($insumo = mysqli_fetch_assoc($resultadoReceta)) {
            $idInsumo = (int) $insumo["id_insumo"];
            if (!isset($requeridoPorInsumo[$idInsumo])) {
                $requeridoPorInsumo[$idInsumo] = array(
                    "id_insumo" => $idInsumo,
                    "nombre" => $insumo["nombre"],
                    "unidad" => $insumo["simbolo"] ?? "",
                    "disponible" => (float) $insumo["cantidad_actual"],
                    "requerido" => 0
                );
            }
            $requeridoPorInsumo[$idInsumo]["requerido"] += (float) $insumo["cantidad_requerida"];
        }
    }
    mysqli_stmt_close($stmtReceta);

    return array_values(array_filter($requeridoPorInsumo, function ($insumo) {
        return $insumo["requerido"] > $insumo["disponible"];
    }));
}

$metodo = $_SERVER["REQUEST_METHOD"];
$vista = obtener_parametro_get("vista");
$condicionVista = "";
if ($vista === "cocina") {
    verificar_permiso("cocina", true);
    $condicionVista = " AND p.cobrado = FALSE
                        AND LOWER(ep.nombre) = 'en preparación'
                        AND EXISTS (
                            SELECT 1
                            FROM Contiene cc
                            INNER JOIN Producto pp ON pp.id_producto = cc.id_producto
                            LEFT JOIN Categoria cp ON cp.id_categoria = pp.id_categoria
                            WHERE cc.id_pedido = p.id_pedido
                              AND LOWER(COALESCE(cp.titulo, '')) <> 'bebidas'
                        )";
} elseif ($vista === "pendientes") {
    $condicionVista = " AND p.cobrado = FALSE
                        AND LOWER(ep.nombre) IN ('en preparación', 'comiendo', 'listo')";
}
if ($metodo === "GET") {
    if (obtener_parametro_get("caja") === "estado") {
        $sqlCaja = "SELECT id_turno, fecha_hora_apertura
                    FROM Turno_caja
                    WHERE fecha_hora_cierre IS NULL
                    ORDER BY fecha_hora_apertura DESC
                    LIMIT 1";
        $resultadoCaja = mysqli_query($conexion, $sqlCaja);
        $turnoCaja = mysqli_fetch_assoc($resultadoCaja);
        responder(array("abierta" => $turnoCaja ? true : false));
    }
    $limite = max(1, (int) obtener_parametro_get("limite", "20"));
    $pagina = max(1, (int) obtener_parametro_get("pagina", "1"));
    $desde = ($pagina - 1) * $limite;
    $filtro = obtener_parametro_get("filtro");

    $sql = "SELECT p.id_pedido, p.descripcion, p.fecha_hora_creacion, p.fecha_hora_entrega,
                   p.monto_total, p.id_empleado, CONCAT(e.nombre, ' ', e.apellido) AS empleado,
                   p.id_turno, p.id_mesa, m.numero AS mesa, p.id_estado, ep.nombre AS estado,
                   p.id_cliente, CONCAT(c.nombre, ' ', c.apellido) AS cliente,
                   p.id_descuento
            FROM Pedido p
            INNER JOIN Empleado e ON e.id_empleado = p.id_empleado
            INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
            LEFT JOIN Mesa m ON m.id_mesa = p.id_mesa
            LEFT JOIN Cliente c ON c.id_cliente = p.id_cliente";
    if ($filtro !== "") {
        $sql .= " WHERE (p.id_pedido LIKE ? OR m.numero LIKE ? OR ep.nombre LIKE ?
                         OR c.nombre LIKE ? OR c.apellido LIKE ?) {$condicionVista}";
        $busqueda = "%" . $filtro . "%";
    } else {
        $sql .= " WHERE 1 = 1{$condicionVista}";
    }
    $sql .= " ORDER BY p.fecha_hora_creacion DESC LIMIT ? OFFSET ?";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        responder(array("error" => "No se pudo preparar la consulta."), 500);
    }
    if ($filtro !== "") {
        mysqli_stmt_bind_param(
            $stmt,
            "sssssii",
            $busqueda,
            $busqueda,
            $busqueda,
            $busqueda,
            $busqueda,
            $limite,
            $desde
        );
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $limite, $desde);
    }

    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $pedidos = array();
    while ($pedido = mysqli_fetch_assoc($resultado)) {
        $pedido["productos"] = obtenerProductosPedido($conexion, (int)$pedido["id_pedido"], $vista === "cocina");
        $pedidos[] = $pedido;
    }
    mysqli_stmt_close($stmt);
    responder($pedidos);
}

if ($metodo === "POST") {
    $entradaAccion = obtenerEntrada();
    $accion = $entradaAccion["accion"] ?? "";

    if ($accion === "agregar_productos") {
        verificar_permiso("crear_pedido", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        $productos = $entradaAccion["productos"] ?? array();
        $forzarStock = ($entradaAccion["forzar_stock"] ?? false) === true;
        if ($idPedido === false || $idPedido <= 0 || !is_array($productos) || count($productos) === 0) {
            responder(array("error" => "Datos del pedido no válidos."), 400);
        }

        mysqli_begin_transaction($conexion);
        $sqlPedido = "SELECT p.monto_total
                      FROM Pedido p
                      INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
                      WHERE p.id_pedido = ?
                        AND LOWER(ep.nombre) IN ('en preparación', 'comiendo')
                        AND p.cobrado = FALSE
                        AND p.id_turno IN (
                            SELECT id_turno
                            FROM Turno_caja
                            WHERE fecha_hora_cierre IS NULL
                        )
                      FOR UPDATE";
        $stmtPedido = mysqli_prepare($conexion, $sqlPedido);
        if (!$stmtPedido) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo consultar el pedido."), 500);
        }
        mysqli_stmt_bind_param($stmtPedido, "i", $idPedido);
        mysqli_stmt_execute($stmtPedido);
        $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPedido));
        mysqli_stmt_close($stmtPedido);
        if (!$pedido) {
            mysqli_rollback($conexion);
            responder(array("error" => "Solo se pueden agregar productos a un pedido que aún no esté cerrado ni cobrado."), 409);
        }

        $productosActualizados = array();
        $totalAgregado = 0;
        $hayProductoCocina = false;
        foreach ($productos as $producto) {
            $idProducto = filter_var($producto["id_producto"] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($producto["cantidad"] ?? null, FILTER_VALIDATE_INT);
            if (
                $idProducto === false || $idProducto <= 0
                || $cantidad === false || $cantidad <= 0
                || isset($productosActualizados[$idProducto])
            ) {
                mysqli_rollback($conexion);
                responder(array("error" => "La lista de productos no es válida."), 400);
            }

            $stmtPrecio = mysqli_prepare(
                $conexion,
                "SELECT p.precio, c.titulo AS categoria
                 FROM Producto p
                 LEFT JOIN Categoria c ON c.id_categoria = p.id_categoria
                 WHERE p.id_producto = ? AND p.activo = TRUE"
            );
            if (!$stmtPrecio) {
                mysqli_rollback($conexion);
                responder(array("error" => "No se pudo consultar el producto."), 500);
            }
            mysqli_stmt_bind_param($stmtPrecio, "i", $idProducto);
            mysqli_stmt_execute($stmtPrecio);
            $filaPrecio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPrecio));
            mysqli_stmt_close($stmtPrecio);
            if (!$filaPrecio) {
                mysqli_rollback($conexion);
                responder(array("error" => "El producto indicado no existe o está inactivo."), 400);
            }

            $precio = (float) $filaPrecio["precio"];
            $esBebida = strtolower(trim($filaPrecio["categoria"] ?? "")) === "bebidas";
            if (!$esBebida) {
                $hayProductoCocina = true;
            }
            $total = $precio * $cantidad;
            $totalAgregado += $total;
            $productosActualizados[$idProducto] = array(
                "cantidad" => $cantidad,
                "precio" => $precio,
                "total" => $total,
                "es_bebida" => $esBebida
            );
        }

        try {
            $productosParaValidar = array_map(
                function ($idProducto, $linea) {
                    return array(
                        "id_producto" => $idProducto,
                        "cantidad" => $linea["cantidad"]
                    );
                },
                array_keys($productosActualizados),
                array_values($productosActualizados)
            );
            $faltantes = obtenerFaltantesStock($conexion, $productosParaValidar);
        } catch (Throwable $error) {
            error_log("No se pudo comprobar stock al agregar productos al pedido #{$idPedido}: " . $error->getMessage());
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo comprobar el inventario. Intenta nuevamente."), 500);
        }
        if ($faltantes && !$forzarStock) {
            mysqli_rollback($conexion);
            responder(array(
                "error" => "No hay stock suficiente para agregar estos productos. Activa “Forzar pedido aunque falte stock” para continuar.",
                "faltantes" => $faltantes
            ), 409);
        }

        $sqlLinea = "INSERT INTO Contiene (id_pedido, id_producto, cantidad, precio, total)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        cantidad = cantidad + VALUES(cantidad),
                        precio = VALUES(precio),
                        total = total + VALUES(total),
                        completado = FALSE";
        $stmtLinea = mysqli_prepare($conexion, $sqlLinea);
        if (!$stmtLinea) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudieron agregar los productos."), 500);
        }
        foreach ($productosActualizados as $idProducto => $linea) {
            $cantidad = $linea["cantidad"];
            $precio = $linea["precio"];
            $total = $linea["total"];
            mysqli_stmt_bind_param($stmtLinea, "iiidd", $idPedido, $idProducto, $cantidad, $precio, $total);
            if (!mysqli_stmt_execute($stmtLinea)) {
                mysqli_stmt_close($stmtLinea);
                mysqli_rollback($conexion);
                responder(array("error" => "No se pudieron agregar los productos."), 500);
            }
        }
        mysqli_stmt_close($stmtLinea);
        $sqlActualizarPedido = "UPDATE Pedido SET monto_total = monto_total + ?";
        if (!empty($hayProductoCocina)) {
            $sqlActualizarPedido .= ",
                                    id_estado = (
                                        SELECT id_estado
                                        FROM Estado_Pedido
                                        WHERE LOWER(nombre) = 'en preparación'
                                        LIMIT 1
                                    )";
        }
        $sqlActualizarPedido .= " WHERE id_pedido = ?";
        $stmtActualizarPedido = mysqli_prepare($conexion, $sqlActualizarPedido);
        mysqli_stmt_bind_param($stmtActualizarPedido, "di", $totalAgregado, $idPedido);
        $correcto = mysqli_stmt_execute($stmtActualizarPedido);
        mysqli_stmt_close($stmtActualizarPedido);
        if (!$correcto) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudieron actualizar el total y estado del pedido."), 500);
        }
        mysqli_commit($conexion);
        registrar_auditoria($conexion, "agregar_productos_pedido", "pedido", $idPedido, array(
            "productos" => count($productosActualizados),
            "monto_agregado" => $totalAgregado
        ));
        if ($faltantes && $forzarStock) {
            registrar_auditoria($conexion, "forzar_stock_pedido", "pedido", $idPedido, array("faltantes" => $faltantes));
        }
        responder(array("ok" => true, "monto_agregado" => $totalAgregado));
    }

    if ($accion === "completar_producto") {
        verificar_permiso("cocina", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        $idProducto = filter_var($entradaAccion["id_producto"] ?? null, FILTER_VALIDATE_INT);
        if (
            $idPedido === false || $idPedido <= 0
            || $idProducto === false || $idProducto <= 0
        ) {
            responder(array("error" => "Datos del producto no válidos."), 400);
        }
        $sql = "UPDATE Contiene c
                INNER JOIN Pedido p ON p.id_pedido = c.id_pedido
                INNER JOIN Producto px ON px.id_producto = c.id_producto
                LEFT JOIN Categoria cx ON cx.id_categoria = px.id_categoria
                SET c.completado = TRUE
                WHERE c.id_pedido = ?
                  AND c.id_producto = ?
                  AND LOWER(COALESCE(cx.titulo, '')) <> 'bebidas'
                  AND p.id_estado = (
                      SELECT id_estado
                      FROM Estado_Pedido
                      WHERE LOWER(nombre) = 'en preparación'
                      LIMIT 1
                  )";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $idPedido, $idProducto);
        mysqli_stmt_execute($stmt);
        $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if (!$actualizado) {
            responder(array("error" => "El producto ya está completo, es una bebida o el pedido ya no está en preparación."), 409);
        }
        registrar_auditoria($conexion, "completar_producto_pedido", "pedido", $idPedido, array(
            "id_producto" => $idProducto
        ));
        responder(array("ok" => true));
    }

    if ($accion === "marcar_bebida_pickeada") {
        verificar_permiso("crear_pedido", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        $idProducto = filter_var($entradaAccion["id_producto"] ?? null, FILTER_VALIDATE_INT);
        if ($idPedido === false || $idPedido <= 0 || $idProducto === false || $idProducto <= 0) {
            responder(array("error" => "Datos de la bebida no válidos."), 400);
        }
        $sql = "UPDATE Contiene c
                INNER JOIN Pedido p ON p.id_pedido = c.id_pedido
                INNER JOIN Producto px ON px.id_producto = c.id_producto
                INNER JOIN Categoria cx ON cx.id_categoria = px.id_categoria
                SET c.completado = TRUE
                WHERE c.id_pedido = ?
                  AND c.id_producto = ?
                  AND LOWER(cx.titulo) = 'bebidas'
                  AND p.cobrado = FALSE
                  AND p.id_estado IN (
                      SELECT id_estado
                      FROM Estado_Pedido
                      WHERE LOWER(nombre) IN ('en preparación', 'comiendo')
                  )";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            responder(array("error" => "No se pudo preparar la confirmación de la bebida."), 500);
        }
        mysqli_stmt_bind_param($stmt, "ii", $idPedido, $idProducto);
        $correcto = mysqli_stmt_execute($stmt);
        $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if (!$correcto) {
            responder(array("error" => "No se pudo marcar la bebida como retirada."), 500);
        }
        if (!$actualizado) {
            responder(array("error" => "La bebida no pertenece a un pedido activo o ya fue pickeada."), 409);
        }
        registrar_auditoria($conexion, "marcar_bebida_pickeada", "pedido", $idPedido, array(
            "id_producto" => $idProducto
        ));
        responder(array("ok" => true));
    }

    if ($accion === "finalizar") {
        verificar_permiso("cocina", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        if ($idPedido === false || $idPedido <= 0) {
            responder(array("error" => "Pedido no válido."), 400);
        }
        $sql = "UPDATE Pedido
                SET id_estado = (
                    SELECT id_estado
                    FROM Estado_Pedido
                    WHERE LOWER(nombre) = 'comiendo'
                    LIMIT 1
                )
                WHERE id_pedido = ?
                  AND id_estado = (
                      SELECT id_estado
                      FROM Estado_Pedido
                      WHERE LOWER(nombre) = 'en preparación'
                      LIMIT 1
                  )
                  AND EXISTS (
                      SELECT 1
                      FROM Contiene cc
                      INNER JOIN Producto pp ON pp.id_producto = cc.id_producto
                      LEFT JOIN Categoria cp ON cp.id_categoria = pp.id_categoria
                      WHERE cc.id_pedido = Pedido.id_pedido
                        AND LOWER(COALESCE(cp.titulo, '')) <> 'bebidas'
                  )
                  AND NOT EXISTS (
                      SELECT 1
                      FROM Contiene c
                      INNER JOIN Producto p ON p.id_producto = c.id_producto
                      LEFT JOIN Categoria cat ON cat.id_categoria = p.id_categoria
                      WHERE c.id_pedido = Pedido.id_pedido
                        AND c.completado = FALSE
                        AND LOWER(COALESCE(cat.titulo, '')) <> 'bebidas'
                  )";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $idPedido);
        mysqli_stmt_execute($stmt);
        $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        if (!$actualizado) {
            responder(array("error" => "Todos los productos deben estar completos."), 409);
        }
        registrar_auditoria($conexion, "marcar_pedido_listo", "pedido", $idPedido);
        responder(array("ok" => true, "estado" => "Comiendo"));
    }

    if ($accion === "cerrar_pedido") {
        verificar_permiso("crear_pedido", true);
        $idPedido = filter_var($entradaAccion["id_pedido"] ?? null, FILTER_VALIDATE_INT);
        if ($idPedido === false || $idPedido <= 0) {
            responder(array("error" => "Pedido no válido."), 400);
        }
        mysqli_begin_transaction($conexion);
        $sqlCerrar = "UPDATE Pedido
                      SET id_estado = (
                          SELECT id_estado
                          FROM Estado_Pedido
                          WHERE LOWER(nombre) = 'listo'
                          LIMIT 1
                      )
                      WHERE id_pedido = ?
                        AND id_estado = (
                            SELECT id_estado
                            FROM Estado_Pedido
                            WHERE LOWER(nombre) = 'comiendo'
                            LIMIT 1
                        )
                        AND cobrado = FALSE
                        AND NOT EXISTS (
                            SELECT 1
                            FROM Contiene c
                            INNER JOIN Producto p ON p.id_producto = c.id_producto
                            LEFT JOIN Categoria cat ON cat.id_categoria = p.id_categoria
                            WHERE c.id_pedido = Pedido.id_pedido
                              AND c.completado = FALSE
                              AND LOWER(COALESCE(cat.titulo, '')) <> 'bebidas'
                            )
                            AND NOT EXISTS (
                                SELECT 1
                                FROM Contiene c
                                INNER JOIN Producto p ON p.id_producto = c.id_producto
                                LEFT JOIN Categoria cat ON cat.id_categoria = p.id_categoria
                                WHERE c.id_pedido = Pedido.id_pedido
                                  AND c.completado = FALSE
                                  AND LOWER(COALESCE(cat.titulo, '')) = 'bebidas'
                            )";
        $stmtCerrar = mysqli_prepare($conexion, $sqlCerrar);
        mysqli_stmt_bind_param($stmtCerrar, "i", $idPedido);
        $correcto = mysqli_stmt_execute($stmtCerrar);
        $actualizado = mysqli_stmt_affected_rows($stmtCerrar) > 0;
        mysqli_stmt_close($stmtCerrar);
        if (!$correcto || !$actualizado) {
            mysqli_rollback($conexion);
            responder(array("error" => "El pedido debe estar en estado Comiendo, con todos los productos de cocina listos y las bebidas pickeadas."), 409);
        }
        $sqlMesa = "UPDATE Mesa m
                    INNER JOIN Pedido p ON p.id_mesa = m.id_mesa
                    SET m.id_estado = (
                        SELECT id_estado
                        FROM Estado_Mesa
                        WHERE LOWER(nombre) = 'para limpiar'
                        LIMIT 1
                    )
                    WHERE p.id_pedido = ?";
        $stmtMesa = mysqli_prepare($conexion, $sqlMesa);
        if (!$stmtMesa) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo liberar la mesa para limpieza."), 500);
        }
        mysqli_stmt_bind_param($stmtMesa, "i", $idPedido);
        $correcto = mysqli_stmt_execute($stmtMesa);
        mysqli_stmt_close($stmtMesa);
        if (!$correcto) {
            mysqli_rollback($conexion);
            responder(array("error" => "No se pudo liberar la mesa para limpieza."), 500);
        }
        mysqli_commit($conexion);
        registrar_auditoria($conexion, "cerrar_pedido", "pedido", $idPedido);
        responder(array("ok" => true, "estado" => "Listo"));
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
$idCliente = empty($entrada["id_cliente"])
    ? null
    : filter_var($entrada["id_cliente"], FILTER_VALIDATE_INT);
$productos = $entrada["productos"] ?? array();
$forzarStock = ($entrada["forzar_stock"] ?? false) === true;
$idEmpleado = (int) $_SESSION["usuario_id"];

if (
    $idMesa === false || $idMesa <= 0
    || !is_array($productos) || count($productos) === 0
) {
    responder(array("error" => "Faltan datos obligatorios del pedido."), 400);
}
if ($idCliente === false) {
    responder(array("error" => "El cliente indicado no es válido."), 400);
}
if ($idCliente !== null) {
    $stmtCliente = mysqli_prepare(
        $conexion,
        "SELECT id_cliente FROM Cliente WHERE id_cliente = ? AND activo = TRUE LIMIT 1"
    );
    if (!$stmtCliente) {
        responder(array("error" => "No se pudo validar el cliente."), 500);
    }
    mysqli_stmt_bind_param($stmtCliente, "i", $idCliente);
    mysqli_stmt_execute($stmtCliente);
    $clienteActivo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCliente));
    mysqli_stmt_close($stmtCliente);
    if (!$clienteActivo) {
        responder(array("error" => "El cliente seleccionado no existe o está inactivo. Vuelve a seleccionarlo."), 400);
    }
}

$sqlTurno = "SELECT id_turno
             FROM Turno_caja
             WHERE fecha_hora_cierre IS NULL
             ORDER BY fecha_hora_apertura DESC
             LIMIT 1";
$resultadoTurno = mysqli_query($conexion, $sqlTurno);
$turnoAbierto = mysqli_fetch_assoc($resultadoTurno);
if (!$turnoAbierto) {
    responder(array("error" => "No hay una sesión de caja abierta."), 409);
}
$idTurno = (int) $turnoAbierto["id_turno"];

$sqlEstado = "SELECT id_estado
              FROM Estado_Pedido
              WHERE LOWER(nombre) = 'en preparación'
              LIMIT 1";
$stmtEstado = mysqli_prepare($conexion, $sqlEstado);
mysqli_stmt_execute($stmtEstado);
$resultadoEstado = mysqli_stmt_get_result($stmtEstado);
$estadoPreparacion = mysqli_fetch_assoc($resultadoEstado);
mysqli_stmt_close($stmtEstado);
if (!$estadoPreparacion) {
    responder(array("error" => "No existe el estado En preparación."), 500);
}
$idEstado = (int) $estadoPreparacion["id_estado"];

$lineas = array();
$idsProductos = array();
$montoTotal = 0;
$hayProductoCocina = false;
foreach ($productos as $producto) {
    $idProducto = filter_var($producto["id_producto"] ?? null, FILTER_VALIDATE_INT);
    $cantidad = filter_var($producto["cantidad"] ?? null, FILTER_VALIDATE_INT);
    if (
        $idProducto === false || $idProducto <= 0
        || $cantidad === false || $cantidad <= 0
        || isset($idsProductos[$idProducto])
    ) {
        responder(array("error" => "La lista de productos no es válida."), 400);
    }

    $stmtPrecio = mysqli_prepare(
        $conexion,
        "SELECT p.precio, c.titulo AS categoria
         FROM Producto p
         LEFT JOIN Categoria c ON c.id_categoria = p.id_categoria
         WHERE p.id_producto = ? AND p.activo = TRUE"
    );
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

    $precio = (float) $filaPrecio["precio"];
    $esBebida = strtolower(trim($filaPrecio["categoria"] ?? "")) === "bebidas";
    if (!$esBebida) {
        $hayProductoCocina = true;
    }
    $total = $precio * $cantidad;
    $montoTotal += $total;
    $idsProductos[$idProducto] = true;
    $lineas[] = array(
        "id_producto" => $idProducto,
        "cantidad" => $cantidad,
        "precio" => $precio,
        "total" => $total,
        "es_bebida" => $esBebida
    );
}
if (!$hayProductoCocina) {
    $stmtEstadoBebidas = mysqli_prepare(
        $conexion,
        "SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'comiendo' LIMIT 1"
    );
    if (!$stmtEstadoBebidas) {
        responder(array("error" => "No se pudo consultar el estado del pedido."), 500);
    }
    mysqli_stmt_execute($stmtEstadoBebidas);
    $estadoBebidas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtEstadoBebidas));
    mysqli_stmt_close($stmtEstadoBebidas);
    if (!$estadoBebidas) {
        responder(array("error" => "No existe el estado Comiendo."), 500);
    }
    $idEstado = (int) $estadoBebidas["id_estado"];
}

mysqli_begin_transaction($conexion);
$faltantes = array();
try {
    $faltantes = obtenerFaltantesStock($conexion, $lineas);
} catch (Throwable $error) {
    error_log("No se pudo comprobar stock al crear un pedido: " . $error->getMessage());
    mysqli_rollback($conexion);
    responder(array("error" => "No se pudo comprobar el inventario. Intenta nuevamente."), 500);
}
if ($faltantes && !$forzarStock) {
    mysqli_rollback($conexion);
    responder(array(
        "error" => "No hay stock suficiente para este pedido. Activa “Forzar pedido aunque falte stock” para continuar.",
        "faltantes" => $faltantes
    ), 409);
}
$correcto = true;
$sqlMesaLibre = "UPDATE Mesa
                 SET id_estado = (
                     SELECT id_estado
                     FROM Estado_Mesa
                     WHERE LOWER(nombre) = 'ocupada'
                     LIMIT 1
                 )
                 WHERE id_mesa = ?
                   AND id_estado = (
                       SELECT id_estado
                       FROM Estado_Mesa
                       WHERE LOWER(nombre) = 'libre'
                       LIMIT 1
                   )";
$stmtMesaLibre = mysqli_prepare($conexion, $sqlMesaLibre);
if ($stmtMesaLibre) {
    mysqli_stmt_bind_param($stmtMesaLibre, "i", $idMesa);
    $correcto = mysqli_stmt_execute($stmtMesaLibre) && mysqli_stmt_affected_rows($stmtMesaLibre) === 1;
    mysqli_stmt_close($stmtMesaLibre);
}
if (!$correcto) {
    mysqli_rollback($conexion);
    responder(array("error" => "La mesa seleccionada no está libre o ya fue tomada por otro usuario."), 409);
}
$sqlPedido = "INSERT INTO Pedido (
                  descripcion, fecha_hora_entrega, monto_total, id_empleado,
                  id_turno, id_mesa, id_estado, id_cliente
              )
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmtPedido = mysqli_prepare($conexion, $sqlPedido);
if ($stmtPedido) {
    mysqli_stmt_bind_param(
        $stmtPedido,
        "ssdiiiii",
        $descripcion,
        $fechaEntrega,
        $montoTotal,
        $idEmpleado,
        $idTurno,
        $idMesa,
        $idEstado,
        $idCliente
    );
    $correcto = mysqli_stmt_execute($stmtPedido);
    $idPedido = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmtPedido);
} else {
    $correcto = false;
}

if ($correcto) {
    $sqlContiene = "INSERT INTO Contiene (id_pedido, id_producto, cantidad, precio, total)
                    VALUES (?, ?, ?, ?, ?)";
    $stmtContiene = mysqli_prepare($conexion, $sqlContiene);
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
registrar_auditoria($conexion, "crear_pedido", "pedido", $idPedido, array(
    "productos" => count($lineas),
    "monto_total" => $montoTotal,
    "id_mesa" => $idMesa
));
if ($faltantes && $forzarStock) {
    registrar_auditoria($conexion, "forzar_stock_pedido", "pedido", $idPedido, array("faltantes" => $faltantes));
}
responder(array("ok" => true, "id_pedido" => $idPedido, "monto_total" => $montoTotal), 201);