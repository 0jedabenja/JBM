<?php
define("JBM_JSON_API", true);
ini_set("display_errors", "0");

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
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
