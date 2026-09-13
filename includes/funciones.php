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
