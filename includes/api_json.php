<?php
define("JBM_JSON_API", true);
ini_set("display_errors", "0");
ini_set("log_errors", "1");
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_DEPRECATED);

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatales = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error["type"], $fatales, true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    error_log("Error fatal en API JSON: " . $error["message"] . " en " . $error["file"] . " línea " . $error["line"]);
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(array("error" => "Error interno del servidor."), JSON_UNESCAPED_UNICODE);
});

set_exception_handler(function (Throwable $error) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    error_log("Error en API JSON: " . $error->getMessage());
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(array("error" => "Error interno al procesar la solicitud."), JSON_UNESCAPED_UNICODE);
});

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
ob_start();
