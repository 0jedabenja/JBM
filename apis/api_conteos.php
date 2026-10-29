<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_permiso("conteos", true);
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function responder_conteo($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

function es_medida_unidad($unidad) {
    $unidad = strtolower(trim((string)$unidad));
    return $unidad === "unidad" || $unidad === "unidades" || $unidad === "u";
}

function leer_conteo() {
    $datos = json_decode(file_get_contents("php://input"), true);
    return is_array($datos) ? $datos : array();
}

function obtener_detalle_conteo($conexion, $idConteo) {
    $sql = "SELECT id_conteo, fecha_inicio, estado
            FROM Conteo_Insumo
            WHERE id_conteo = ? AND estado = 'En curso'";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    mysqli_stmt_execute($stmt);
    $conteo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$conteo) return null;

    $sql = "SELECT d.id_insumo, i.nombre, i.costo,
                   COALESCE(m.simbolo, m.unidad, '-') AS unidad,
                   m.unidad AS unidad_medida,
                   d.cantidad_sistema, d.cantidad_contada
            FROM Detalle_Conteo_Insumo d
            INNER JOIN Insumo i ON i.id_insumo = d.id_insumo
            LEFT JOIN Medida m ON m.id_medida = i.id_medida
            WHERE d.id_conteo = ?
            ORDER BY i.nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $items = array();
    while ($item = mysqli_fetch_assoc($resultado)) $items[] = $item;
    mysqli_stmt_close($stmt);

    return array("conteo" => $conteo, "items" => $items);
}

function obtener_insumos_conteo($conexion, $tipo, $idCategoria = null) {
    $sql = "SELECT id_insumo, nombre, cantidad_actual FROM Insumo WHERE activo = TRUE";
    if ($tipo === "categoria") $sql .= " AND id_categoria = ?";
    if ($tipo === "sin_categoria") $sql .= " AND id_categoria IS NULL";
    $sql .= " ORDER BY nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    if ($tipo === "categoria") mysqli_stmt_bind_param($stmt, "i", $idCategoria);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $items = array();
    while ($item = mysqli_fetch_assoc($resultado)) $items[] = $item;
    mysqli_stmt_close($stmt);
    return $items;
}

function responder_listados($conexion) {
    $categorias = array();
    $resultado = mysqli_query($conexion, "SELECT id_categoria, titulo FROM Categoria ORDER BY titulo");
    while ($categoria = mysqli_fetch_assoc($resultado)) $categorias[] = $categoria;

    $pendientes = array();
    $sqlPendientes = "SELECT c.id_conteo, c.fecha_inicio, c.tipo_categoria,
                             cat.titulo AS categoria, e.nombre, e.apellido,
                             COUNT(d.id_insumo) AS cantidad
                      FROM Conteo_Insumo c
                      LEFT JOIN Categoria cat ON cat.id_categoria = c.id_categoria
                      INNER JOIN Empleado e ON e.id_empleado = c.id_empleado
                      LEFT JOIN Detalle_Conteo_Insumo d ON d.id_conteo = c.id_conteo
                      WHERE c.estado = 'En curso'
                      GROUP BY c.id_conteo, c.fecha_inicio, c.tipo_categoria,
                               cat.titulo, e.nombre, e.apellido
                      ORDER BY c.fecha_inicio DESC";
    $resultado = mysqli_query($conexion, $sqlPendientes);
    while ($conteo = mysqli_fetch_assoc($resultado)) $pendientes[] = $conteo;

    $historial = array();
    $sqlHistorial = "SELECT c.id_conteo, c.fecha_inicio, c.fecha_fin, c.estado,
                            c.tipo_categoria, cat.titulo AS categoria,
                            e.nombre, e.apellido, COUNT(d.id_insumo) AS cantidad,
                            SUM(CASE WHEN d.diferencia <> 0 THEN 1 ELSE 0 END) AS items_con_diferencia
                     FROM Conteo_Insumo c
                     LEFT JOIN Categoria cat ON cat.id_categoria = c.id_categoria
                     INNER JOIN Empleado e ON e.id_empleado = c.id_empleado
                     LEFT JOIN Detalle_Conteo_Insumo d ON d.id_conteo = c.id_conteo
                     WHERE c.estado <> 'En curso'
                     GROUP BY c.id_conteo, c.fecha_inicio, c.fecha_fin, c.estado,
                              c.tipo_categoria, cat.titulo, e.nombre, e.apellido
                     ORDER BY c.fecha_inicio DESC
                     LIMIT 20";
    $resultado = mysqli_query($conexion, $sqlHistorial);
    while ($conteo = mysqli_fetch_assoc($resultado)) $historial[] = $conteo;

    responder_conteo(array("categorias" => $categorias, "pendientes" => $pendientes, "historial" => $historial));
}

$metodo = $_SERVER["REQUEST_METHOD"];
if ($metodo === "GET") {
    $idParametro = obtener_parametro_get("id_conteo");
    if ($idParametro !== "") {
        $idConteo = filter_var($idParametro, FILTER_VALIDATE_INT);
        $detalle = $idConteo ? obtener_detalle_conteo($conexion, $idConteo) : null;
        if (!$detalle) responder_conteo(array("error" => "El conteo no existe o ya fue cerrado."), 404);
        responder_conteo($detalle);
    }

    responder_listados($conexion);
}

if ($metodo !== "POST") responder_conteo(array("error" => "Método no permitido."), 405);

$entrada = leer_conteo();
$accion = $entrada["accion"] ?? "";

if ($accion === "iniciar") {
    $filtro = $entrada["categoria"] ?? "todas";
    if (!is_string($filtro) && !is_int($filtro)) {
        responder_conteo(array("error" => "La categoría seleccionada no es válida."), 400);
    }
    $filtro = (string)$filtro;
    $tipo = "todas";
    $idCategoria = null;
    if ($filtro === "sin_categoria") {
        $tipo = "sin_categoria";
    } elseif ($filtro !== "todas") {
        $idCategoria = filter_var($filtro, FILTER_VALIDATE_INT);
        if ($idCategoria === false || $idCategoria <= 0) responder_conteo(array("error" => "La categoría seleccionada no es válida."), 400);
        $tipo = "categoria";
    }

    mysqli_begin_transaction($conexion);
    $resultadoAbiertos = mysqli_query(
        $conexion,
        "SELECT tipo_categoria, id_categoria
         FROM Conteo_Insumo
         WHERE estado = 'En curso'
         FOR UPDATE"
    );
    if (!$resultadoAbiertos) {
        mysqli_rollback($conexion);
        responder_conteo(array("error" => "No se pudieron verificar los conteos abiertos."), 500);
    }
    while ($conteoAbierto = mysqli_fetch_assoc($resultadoAbiertos)) {
        $seSuperponen =
            $tipo === "todas" ||
            $conteoAbierto["tipo_categoria"] === "todas" ||
            ($tipo === "sin_categoria" && $conteoAbierto["tipo_categoria"] === "sin_categoria") ||
            ($tipo === "categoria" &&
                $conteoAbierto["tipo_categoria"] === "categoria" &&
                (int)$conteoAbierto["id_categoria"] === (int)$idCategoria);
        if ($seSuperponen) {
            mysqli_free_result($resultadoAbiertos);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "Ya hay un conteo abierto que incluye esta categoría. Finalizalo o cancelalo antes de iniciar otro."), 409);
        }
    }
    mysqli_free_result($resultadoAbiertos);

    $idEmpleado = (int)$_SESSION["usuario_id"];
    $stmt = mysqli_prepare($conexion, "INSERT INTO Conteo_Insumo (tipo_categoria, id_categoria, id_empleado) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sii", $tipo, $idCategoria, $idEmpleado);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conexion);
        responder_conteo(array("error" => "No se pudo iniciar el conteo."), 500);
    }
    $idConteo = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    $items = obtener_insumos_conteo($conexion, $tipo, $idCategoria);
    if (!$items) {
        mysqli_rollback($conexion);
        responder_conteo(array("error" => "No hay insumos activos para contar con este filtro."), 422);
    }

    $stmt = mysqli_prepare($conexion, "INSERT INTO Detalle_Conteo_Insumo (id_conteo, id_insumo, cantidad_sistema) VALUES (?, ?, ?)");
    foreach ($items as $item) {
        $idInsumo = (int)$item["id_insumo"];
        $cantidadSistema = (float)$item["cantidad_actual"];
        mysqli_stmt_bind_param($stmt, "iid", $idConteo, $idInsumo, $cantidadSistema);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "No se pudieron guardar los insumos del conteo."), 500);
        }
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($conexion);
    registrar_auditoria($conexion, "iniciar_conteo", "conteo_insumos", $idConteo, array("tipo_categoria" => $tipo, "id_categoria" => $idCategoria));
    responder_conteo(obtener_detalle_conteo($conexion, $idConteo), 201);
}

if ($accion === "guardar_borrador") {
    $idConteo = filter_var($entrada["id_conteo"] ?? null, FILTER_VALIDATE_INT);
    $cantidades = $entrada["cantidades"] ?? null;
    if ($idConteo === false || $idConteo <= 0 || !is_array($cantidades) || !$cantidades) {
        responder_conteo(array("error" => "Los datos del borrador no son válidos."), 400);
    }

    mysqli_begin_transaction($conexion);
    $stmt = mysqli_prepare($conexion, "SELECT estado FROM Conteo_Insumo WHERE id_conteo = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    mysqli_stmt_execute($stmt);
    $conteo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$conteo || $conteo["estado"] !== "En curso") {
        mysqli_rollback($conexion);
        responder_conteo(array("error" => "El conteo ya no está disponible."), 409);
    }

    $stmt = mysqli_prepare($conexion, "SELECT d.id_insumo, m.unidad
                                     FROM Detalle_Conteo_Insumo d
                                     INNER JOIN Insumo i ON i.id_insumo = d.id_insumo
                                     LEFT JOIN Medida m ON m.id_medida = i.id_medida
                                     WHERE d.id_conteo = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $esperados = array();
    while ($fila = mysqli_fetch_assoc($resultado)) $esperados[(int)$fila["id_insumo"]] = $fila["unidad"] ?? "";
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conexion, "UPDATE Detalle_Conteo_Insumo SET cantidad_contada = ? WHERE id_conteo = ? AND id_insumo = ?");
    $guardados = array();
    foreach ($cantidades as $item) {
        if (!is_array($item)) {
            mysqli_stmt_close($stmt);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "Revisá las cantidades ingresadas."), 400);
        }

        $idInsumo = filter_var($item["id_insumo"] ?? null, FILTER_VALIDATE_INT);
        $valor = $item["cantidad"] ?? null;
        $cantidad = $valor === null ? null : (is_numeric($valor) && is_finite((float)$valor) ? (float)$valor : false);
        if ($idInsumo === false || !array_key_exists($idInsumo, $esperados) || array_key_exists($idInsumo, $guardados) || $cantidad === false || ($cantidad !== null && ($cantidad < 0 || $cantidad > 99999999.99)) || ($cantidad !== null && es_medida_unidad($esperados[$idInsumo]) && floor($cantidad) !== $cantidad)) {
            mysqli_stmt_close($stmt);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "Revisá las cantidades ingresadas."), 400);
        }

        if ($cantidad !== null) $cantidad = round($cantidad, 2);
        mysqli_stmt_bind_param($stmt, "dii", $cantidad, $idConteo, $idInsumo);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "No se pudo guardar el borrador."), 500);
        }
        $guardados[$idInsumo] = true;
    }

    mysqli_stmt_close($stmt);
    mysqli_commit($conexion);
    responder_conteo(array("ok" => true));
}

if ($accion === "finalizar") {
    $idConteo = filter_var($entrada["id_conteo"] ?? null, FILTER_VALIDATE_INT);
    $cantidades = $entrada["cantidades"] ?? null;
    $contrasenaAdministrador = $entrada["contrasena_administrador"] ?? null;
    if ($idConteo === false || $idConteo <= 0 || !is_array($cantidades) || !is_string($contrasenaAdministrador) || $contrasenaAdministrador === "") {
        responder_conteo(array("error" => "Los datos del conteo no son válidos."), 400);
    }

    $idEmpleado = (int)$_SESSION["usuario_id"];
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT e.contraseña, e.activo, r.nombre AS rol
         FROM Empleado e
         INNER JOIN Rol r ON r.id_rol = e.id_rol
         WHERE e.id_empleado = ?
         LIMIT 1"
    );
    if (!$stmt) {
        responder_conteo(array("error" => "No se pudo validar la contraseña del administrador."), 500);
    }
    mysqli_stmt_bind_param($stmt, "i", $idEmpleado);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        responder_conteo(array("error" => "No se pudo validar la contraseña del administrador."), 500);
    }
    $administrador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (
        !$administrador ||
        !(bool)$administrador["activo"] ||
        strtolower(trim($administrador["rol"])) !== "administrador" ||
        !password_verify($contrasenaAdministrador, $administrador["contraseña"])
    ) {
        responder_conteo(array("error" => "La contraseña del administrador no es correcta."), 403);
    }

    mysqli_begin_transaction($conexion);
    $stmt = mysqli_prepare($conexion, "SELECT estado FROM Conteo_Insumo WHERE id_conteo = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    mysqli_stmt_execute($stmt);
    $conteo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$conteo || $conteo["estado"] !== "En curso") {
        mysqli_rollback($conexion);
        responder_conteo(array("error" => "El conteo ya no está disponible."), 409);
    }

    $stmt = mysqli_prepare(
        $conexion,
        "        SELECT d.id_insumo, d.cantidad_sistema, i.costo, m.unidad
         FROM Detalle_Conteo_Insumo d
         INNER JOIN Insumo i ON i.id_insumo = d.id_insumo
        LEFT JOIN Medida m ON m.id_medida = i.id_medida
        WHERE d.id_conteo = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $esperados = array();
    while ($item = mysqli_fetch_assoc($resultado)) {
        $esperados[(int)$item["id_insumo"]] = array(
            "cantidad" => (float)$item["cantidad_sistema"],
            "costo" => (float)$item["costo"],
            "unidad" => $item["unidad"] ?? ""
        );
    }
    mysqli_stmt_close($stmt);

    $ingresados = array();
    $cantidadFaltantes = 0;
    $cantidadSobrantes = 0;
    foreach ($cantidades as $item) {
        if (!is_array($item)) {
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "Revisá las cantidades ingresadas."), 400);
        }
        $idInsumo = filter_var($item["id_insumo"] ?? null, FILTER_VALIDATE_INT);
        $cantidad = $item["cantidad"] ?? null;
        if ($idInsumo === false || !array_key_exists($idInsumo, $esperados) || !is_numeric($cantidad) || !is_finite((float)$cantidad) || (float)$cantidad < 0 || (float)$cantidad > 99999999.99 || isset($ingresados[$idInsumo]) || (es_medida_unidad($esperados[$idInsumo]["unidad"] ?? "") && floor((float)$cantidad) !== (float)$cantidad)) {
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "Revisá las cantidades ingresadas."), 400);
        }
        $ingresados[$idInsumo] = round((float)$cantidad, 2);
        if ($ingresados[$idInsumo] < $esperados[$idInsumo]["cantidad"]) {
            $cantidadFaltantes++;
        } elseif ($ingresados[$idInsumo] > $esperados[$idInsumo]["cantidad"]) {
            $cantidadSobrantes++;
        }
    }

    if (count($ingresados) !== count($esperados)) {
        mysqli_rollback($conexion);
        responder_conteo(array("error" => "Debés contar todos los insumos incluidos."), 400);
    }

    $stmtInsumo = mysqli_prepare($conexion, "UPDATE Insumo SET cantidad_actual = ? WHERE id_insumo = ?");
    $stmtDetalle = mysqli_prepare($conexion, "UPDATE Detalle_Conteo_Insumo SET cantidad_contada = ?, diferencia = ? WHERE id_conteo = ? AND id_insumo = ?");
    foreach ($ingresados as $idInsumo => $cantidad) {
        $cantidadSistema = $esperados[$idInsumo]["cantidad"];
        $diferencia = round($cantidad - $cantidadSistema, 2);
        mysqli_stmt_bind_param($stmtInsumo, "di", $cantidad, $idInsumo);
        if (!mysqli_stmt_execute($stmtInsumo)) {
            mysqli_stmt_close($stmtInsumo);
            mysqli_stmt_close($stmtDetalle);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "No se pudieron actualizar las existencias."), 500);
        }
        mysqli_stmt_bind_param($stmtDetalle, "ddii", $cantidad, $diferencia, $idConteo, $idInsumo);
        if (!mysqli_stmt_execute($stmtDetalle)) {
            mysqli_stmt_close($stmtInsumo);
            mysqli_stmt_close($stmtDetalle);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "No se pudo guardar el detalle del conteo."), 500);
        }
        if (!registrar_movimiento_insumo($conexion, $idInsumo, "inventario", $diferencia, $esperados[$idInsumo]["costo"], $idConteo)) {
            mysqli_stmt_close($stmtInsumo);
            mysqli_stmt_close($stmtDetalle);
            mysqli_rollback($conexion);
            responder_conteo(array("error" => "No se pudo registrar el movimiento del ajuste de inventario."), 500);
        }
    }
    mysqli_stmt_close($stmtInsumo);
    mysqli_stmt_close($stmtDetalle);

    $stmt = mysqli_prepare($conexion, "UPDATE Conteo_Insumo SET estado = 'Finalizado', fecha_fin = NOW() WHERE id_conteo = ?");
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conexion);
        responder_conteo(array("error" => "No se pudo finalizar el conteo."), 500);
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($conexion);
    registrar_auditoria($conexion, "finalizar_conteo", "conteo_insumos", $idConteo, array("items" => count($ingresados), "faltantes" => $cantidadFaltantes, "sobrantes" => $cantidadSobrantes));
    responder_conteo(array("ok" => true));
}

if ($accion === "cancelar") {
    $idConteo = filter_var($entrada["id_conteo"] ?? null, FILTER_VALIDATE_INT);
    if ($idConteo === false || $idConteo <= 0) responder_conteo(array("error" => "El conteo no es válido."), 400);
    $stmt = mysqli_prepare($conexion, "UPDATE Conteo_Insumo SET estado = 'Cancelado', fecha_fin = NOW() WHERE id_conteo = ? AND estado = 'En curso'");
    mysqli_stmt_bind_param($stmt, "i", $idConteo);
    mysqli_stmt_execute($stmt);
    $actualizado = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    if (!$actualizado) responder_conteo(array("error" => "El conteo ya no está disponible."), 409);
    registrar_auditoria($conexion, "cancelar_conteo", "conteo_insumos", $idConteo);
    responder_conteo(array("ok" => true));
}

responder_conteo(array("error" => "Acción no válida."), 400);