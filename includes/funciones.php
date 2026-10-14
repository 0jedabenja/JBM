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

    if (!empty($_SESSION["rol"])) {
        return strtolower(trim($_SESSION["rol"]));
    }

    if (empty($_SESSION["usuario_id"])) {
        return "";
    }

    global $conexion;
    if (!isset($conexion)) {
        include_once __DIR__ . "/conexionBD.php";
    }

    $stmt = mysqli_prepare($conexion, "SELECT r.nombre FROM Empleado e INNER JOIN Rol r ON r.id_rol = e.id_rol WHERE e.id_empleado = ? AND e.activo = TRUE LIMIT 1");
    if (!$stmt) {
        return "";
    }

    $usuario_id = (int) $_SESSION["usuario_id"];
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    $_SESSION["rol"] = $fila["nombre"] ?? "";
    return strtolower(trim($_SESSION["rol"]));
}

function usuario_tiene_permiso($permiso) {
    $rol = obtener_rol_sesion();
    $permisos = array(
        "administrador" => array("inicio", "pedidos", "crear_pedido", "cocina", "mapa", "caja", "clientes", "insumos", "productos", "empleados"),
        "cajero" => array("inicio", "pedidos", "crear_pedido", "mapa", "caja"),
        "mozo" => array("inicio", "pedidos", "crear_pedido"),
        "cocina" => array("inicio", "pedidos", "cocina")
    );

    return isset($permisos[$rol]) && in_array($permiso, $permisos[$rol], true);
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
        http_response_code(403);
        exit("No tiene permisos para acceder a esta sección.");
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

function cerrar_sesion() {
    iniciar_sesion_segura();
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $parametros["path"], $parametros["domain"] ?? "", $parametros["secure"], $parametros["httponly"]);
    }
    session_destroy();
}
