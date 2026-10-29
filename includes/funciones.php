<?php
function iniciar_sesion_segura() {
    if (session_status() === PHP_SESSION_NONE) {
        $https = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
        session_set_cookie_params(array(
            "lifetime" => 0,
            "path" => "/",
            "secure" => $https,
            "httponly" => true,
            "samesite" => "Lax"
        ));
        session_start();
    }
}

function verificar_sesion() {
    iniciar_sesion_segura();
    if (!isset($_SESSION["usuario_id"])) {
        $directorio = trim(dirname($_SERVER["SCRIPT_NAME"] ?? ""), "/");
        $niveles = $directorio === "" ? 0 : substr_count($directorio, "/");
        header("Location: " . str_repeat("../", $niveles) . "login.php");
        exit();
    }
}

function obtener_rol_sesion() {
    iniciar_sesion_segura();

    if (empty($_SESSION["usuario_id"])) {
        return "";
    }

    global $conexion;
    if (!isset($conexion)) {
        include_once __DIR__ . "/conexionBD.php";
    }

    $usuario = trim((string)($_SESSION["usuario"] ?? ""));
    if ($usuario !== "") {
        $stmt = mysqli_prepare($conexion, "SELECT e.id_empleado, e.usuario, e.nombre, e.apellido, e.id_rol, r.nombre AS rol FROM Empleado e INNER JOIN Rol r ON r.id_rol = e.id_rol WHERE e.usuario = ? AND e.activo = TRUE LIMIT 1");
        if (!$stmt) {
            return "";
        }
        mysqli_stmt_bind_param($stmt, "s", $usuario);
    } else {
        $stmt = mysqli_prepare($conexion, "SELECT e.id_empleado, e.usuario, e.nombre, e.apellido, e.id_rol, r.nombre AS rol FROM Empleado e INNER JOIN Rol r ON r.id_rol = e.id_rol WHERE e.id_empleado = ? AND e.activo = TRUE LIMIT 1");
        if (!$stmt) {
            return "";
        }
        $usuarioId = (int) $_SESSION["usuario_id"];
        mysqli_stmt_bind_param($stmt, "i", $usuarioId);
    }
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if (!$fila) {
        unset($_SESSION["usuario_id"], $_SESSION["usuario"], $_SESSION["nombre_usuario"], $_SESSION["id_rol"], $_SESSION["rol"]);
        return "";
    }

    $_SESSION["usuario_id"] = (int) $fila["id_empleado"];
    $_SESSION["usuario"] = $fila["usuario"];
    $_SESSION["nombre_usuario"] = $fila["nombre"] . " " . $fila["apellido"];
    $_SESSION["id_rol"] = (int) $fila["id_rol"];
    $_SESSION["rol"] = $fila["rol"];
    return strtolower(trim($_SESSION["rol"]));
}

function usuario_tiene_permiso($permiso) {
    $rol = obtener_rol_sesion();
    $permisos = array(
        "administrador" => array("inicio", "pedidos", "crear_pedido", "cocina", "mapa", "caja", "clientes", "insumos", "productos", "empleados", "conteos", "auditoria", "compras"),
        "cajero" => array("inicio", "pedidos", "crear_pedido", "mapa", "caja", "compras"),
        "mozo" => array("inicio", "pedidos", "crear_pedido", "mapa", "clientes"),
        "cocina" => array("inicio", "pedidos", "cocina")
    );

    return isset($permisos[$rol]) && in_array($permiso, $permisos[$rol], true);
}

function mostrar_pagina_error($codigo) {
    $codigoError = (int)$codigo;
    http_response_code($codigoError);
    include __DIR__ . "/../errores.php";
    exit();
}

function verificar_permiso($permiso, $es_api = false) {
    verificar_sesion();

    if (usuario_tiene_permiso($permiso)) {
        return;
    }

    if ($es_api) {
        http_response_code(403);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(array("error" => "No tiene permisos para realizar esta operación."), JSON_UNESCAPED_UNICODE);
    } else {
        mostrar_pagina_error(403);
    }
    exit();
}

function verificar_sesion_api() {
    iniciar_sesion_segura();
    if (!isset($_SESSION["usuario_id"])) {
        http_response_code(401);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(array("error" => "Sesión requerida."), JSON_UNESCAPED_UNICODE);
        exit();
    }
}

function obtener_parametro_get($nombre, $predeterminado = "") {
    if (!isset($_GET[$nombre]) || !is_scalar($_GET[$nombre])) {
        return $predeterminado;
    }

    return trim((string)$_GET[$nombre]);
}

function obtener_listado_api($conexion, $entidad, $filtro, $campo, $limite, $offset) {
    $definiciones = array(
        "clientes" => array(
            "select" => "SELECT c.id_cliente, c.nombre, c.apellido, c.telefono, c.direccion FROM Cliente c",
            "where" => "c.activo = TRUE",
            "order" => "c.apellido, c.nombre",
            "campos" => array(
                "id_cliente" => "CAST(c.id_cliente AS CHAR)",
                "nombre" => "c.nombre",
                "apellido" => "c.apellido",
                "telefono" => "c.telefono",
                "direccion" => "c.direccion",
                "nombre_apellido" => "CONCAT(c.nombre, ' ', c.apellido)"
            ),
            "todos" => array("CAST(c.id_cliente AS CHAR)", "c.nombre", "c.apellido", "c.telefono", "c.direccion")
        ),
        "productos" => array(
            "select" => "SELECT p.id_producto, p.nombre, p.precio, p.id_categoria, cat.titulo AS categoria,
                                CASE WHEN EXISTS (
                                    SELECT 1 FROM Compone co
                                    LEFT JOIN Insumo i ON i.id_insumo = co.id_insumo
                                    WHERE co.id_producto = p.id_producto
                                      AND (i.id_insumo IS NULL OR i.activo <> TRUE OR co.cantidad <= 0 OR i.cantidad_actual < co.cantidad)
                                ) THEN 0 ELSE 1 END AS disponible
                         FROM Producto p LEFT JOIN Categoria cat ON cat.id_categoria = p.id_categoria",
            "where" => "p.activo = TRUE",
            "order" => "p.nombre",
            "campos" => array(
                "id_producto" => "CAST(p.id_producto AS CHAR)",
                "nombre" => "p.nombre",
                "precio" => "CAST(p.precio AS CHAR)",
                "categoria" => "cat.titulo"
            ),
            "todos" => array("CAST(p.id_producto AS CHAR)", "p.nombre", "CAST(p.precio AS CHAR)", "cat.titulo")
        ),
        "insumos" => array(
            "select" => "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.stock_minimo, i.costo, i.id_medida, m.unidad
                         FROM Insumo i LEFT JOIN Medida m ON m.id_medida = i.id_medida",
            "where" => "i.activo = TRUE",
            "order" => "i.nombre",
            "campos" => array(
                "id_insumo" => "CAST(i.id_insumo AS CHAR)",
                "nombre" => "i.nombre",
                "unidad" => "m.unidad",
                "cantidad_actual" => "CAST(i.cantidad_actual AS CHAR)",
                "stock_minimo" => "CAST(i.stock_minimo AS CHAR)",
                "costo" => "CAST(i.costo AS CHAR)"
            ),
            "todos" => array("CAST(i.id_insumo AS CHAR)", "i.nombre", "m.unidad", "CAST(i.cantidad_actual AS CHAR)", "CAST(i.stock_minimo AS CHAR)", "CAST(i.costo AS CHAR)")
        ),
        "empleados" => array(
            "select" => "SELECT e.id_empleado, e.nombre, e.apellido, e.usuario, e.correo, e.id_rol, r.nombre AS rol
                         FROM Empleado e INNER JOIN Rol r ON r.id_rol = e.id_rol",
            "where" => "e.activo = TRUE",
            "order" => "e.apellido, e.nombre",
            "campos" => array(
                "id_empleado" => "CAST(e.id_empleado AS CHAR)",
                "nombre" => "e.nombre",
                "apellido" => "e.apellido",
                "usuario" => "e.usuario",
                "correo" => "e.correo",
                "rol" => "r.nombre"
            ),
            "todos" => array("CAST(e.id_empleado AS CHAR)", "e.nombre", "e.apellido", "e.usuario", "e.correo", "r.nombre")
        )
    );

    if (!isset($definiciones[$entidad])) {
        throw new InvalidArgumentException("Tipo de listado no válido.");
    }

    $definicion = $definiciones[$entidad];
    $sql = $definicion["select"] . " WHERE " . $definicion["where"];
    $valores = array();
    if ($filtro !== "") {
        $expresiones = isset($definicion["campos"][$campo])
            ? array($definicion["campos"][$campo])
            : $definicion["todos"];
        $condiciones = array();
        foreach ($expresiones as $expresion) {
            $condiciones[] = $expresion . " LIKE ?";
            $valores[] = "%" . $filtro . "%";
        }
        $sql .= " AND (" . implode(" OR ", $condiciones) . ")";
    }

    $sql .= " ORDER BY " . $definicion["order"] . " LIMIT ? OFFSET ?";
    $valores[] = (int)$limite;
    $valores[] = (int)$offset;
    $tipos = str_repeat("s", count($valores) - 2) . "ii";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new RuntimeException("No se pudo preparar la consulta del listado: " . mysqli_error($conexion));
    }

    $argumentos = array($stmt, $tipos);
    foreach ($valores as &$valor) {
        $argumentos[] = &$valor;
    }
    unset($valor);
    call_user_func_array("mysqli_stmt_bind_param", $argumentos);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("No se pudo consultar el listado: " . $error);
    }

    $resultado = mysqli_stmt_get_result($stmt);
    if (!$resultado) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("No se pudo leer el listado: " . $error);
    }
    $filas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $filas;
}

function obtener_token_csrf() {
    iniciar_sesion_segura();
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function validar_token_csrf($token) {
    iniciar_sesion_segura();
    return is_string($token) && !empty($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token); // si se setea el token y es correcto, devuelve true, sino false
}

function registrar_auditoria($conexion, $accion, $entidad, $idEntidad = null, $detalle = array()) {
    if (empty($_SESSION["usuario_id"])) {
        error_log("Auditoría no registrada: no hay usuario autenticado.");
        return false;
    }

    $detalleJson = json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($detalleJson === false) {
        error_log("Auditoría no registrada: no se pudo serializar el detalle.");
        return false;
    }

    $idEmpleado = (int)$_SESSION["usuario_id"];
    $idEntidad = $idEntidad === null ? null : (int)$idEntidad;
    $ip = is_string($_SERVER["REMOTE_ADDR"] ?? null) ? substr($_SERVER["REMOTE_ADDR"], 0, 45) : null;
    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO Auditoria (id_empleado, accion, entidad, id_entidad, detalle, ip)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        error_log("Auditoría no registrada: " . mysqli_error($conexion));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ississ", $idEmpleado, $accion, $entidad, $idEntidad, $detalleJson, $ip);
    $registrado = mysqli_stmt_execute($stmt);
    if (!$registrado) {
        error_log("Auditoría no registrada: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $registrado;
}

function registrar_movimiento_insumo($conexion, $idInsumo, $tipo, $cantidad, $costoUnitario, $referenciaId = null, $observacion = null) {
    $idEmpleado = empty($_SESSION["usuario_id"]) ? null : (int)$_SESSION["usuario_id"];
    $idInsumo = (int)$idInsumo;
    $referenciaId = $referenciaId === null ? null : (int)$referenciaId;
    $cantidad = round((float)$cantidad, 2);
    $costoUnitario = round((float)$costoUnitario, 2);
    $observacion = $observacion === null ? null : substr(trim((string)$observacion), 0, 255);
    if ($cantidad == 0) return true;

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO Movimiento_Insumo (id_insumo, id_empleado, tipo, cantidad, costo_unitario, referencia_id, observacion)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        error_log("Movimiento de insumo no registrado: " . mysqli_error($conexion));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "iisddis", $idInsumo, $idEmpleado, $tipo, $cantidad, $costoUnitario, $referenciaId, $observacion);
    $registrado = mysqli_stmt_execute($stmt);
    if (!$registrado) {
        error_log("Movimiento de insumo no registrado: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    return $registrado;
}

function cerrar_sesion() {
    iniciar_sesion_segura();
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $parametros["path"], $parametros["domain"] ?? "", $parametros["secure"], $parametros["httponly"]);
    }
    session_destroy();
}
