<?php
require_once __DIR__ . "/validacion.php";
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

class JBMApiInputException extends RuntimeException
{
}

function jbm_api_input_error($message)
{
    throw new JBMApiInputException($message);
}

function jbm_api_normalize_key($key)
{
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', (string)$key));
}

function jbm_api_validate_scalar($key, $value)
{
    if ($value === null) {
        return;
    }

    $nombre = jbm_api_normalize_key($key);
    if (is_bool($value)) {
        if (in_array($nombre, array("forzar_stock", "activo"), true)) {
            return;
        }
        jbm_api_input_error("El campo '{$key}' tiene un tipo de dato no válido.");
    }

    if (is_int($value) || is_float($value)) {
        if (!is_finite((float)$value)) {
            jbm_api_input_error("El campo '{$key}' debe ser un número finito.");
        }
        $texto = (string)$value;
    } elseif (is_string($value)) {
        $texto = trim($value);
    } else {
        jbm_api_input_error("El campo '{$key}' tiene un tipo de dato no válido.");
    }

    if ($nombre === "forzar_stock") {
        jbm_api_input_error("El campo '{$key}' debe ser verdadero o falso.");
    }

    if ($nombre === "categoria") {
        if (is_string($value) && in_array($texto, array("todas", "sin_categoria"), true)) {
            return;
        }
        if (is_float($value) || !preg_match('/^[0-9]+$/', $texto)) {
            jbm_api_input_error("El campo '{$key}' debe ser un tipo válido o un ID de categoría positivo.");
        }
        $idCategoria = filter_var($texto, FILTER_VALIDATE_INT);
        if ($idCategoria === false || $idCategoria <= 0 || $idCategoria > 2147483647) {
            jbm_api_input_error("El ID de categoría está fuera del rango permitido.");
        }
        return;
    }

    if (preg_match('/^id(?:_|$)/', $nombre) || in_array($nombre, array("pagina", "numero", "pos_x", "pos_y", "ancho"), true)) {
        if (is_float($value)) {
            jbm_api_input_error("El campo '{$key}' debe recibirse como entero.");
        }
        $idOpcional = in_array($nombre, array("id_cliente", "id_categoria", "id_medida", "id_mesa", "id_descuento", "id_empleado"), true);
        if ($texto === "" && $idOpcional) {
            return;
        }
        if (!preg_match('/^[0-9]+$/', $texto)) {
            jbm_api_input_error("El campo '{$key}' debe ser un entero no negativo.");
        }
        $entero = filter_var($texto, FILTER_VALIDATE_INT);
        if ($entero === false || $entero < 0 || $entero > 2147483647
            || ($nombre === "pagina" && $entero > 1000)
        ) {
            jbm_api_input_error("El campo '{$key}' está fuera del rango permitido.");
        }
        if ((preg_match('/^id(?:_|$)/', $nombre) && $entero === 0)
            || (in_array($nombre, array("pagina", "numero"), true) && $entero === 0)
        ) {
            jbm_api_input_error("El campo '{$key}' debe ser mayor que cero.");
        }
        return;
    }

    if ($nombre === "limite") {
        if (strtolower($texto) === "sin") {
            return;
        }
        if (!preg_match('/^[0-9]+$/', $texto) || (int)$texto < 1 || (int)$texto > 1000) {
            jbm_api_input_error("El campo '{$key}' debe ser un entero entre 1 y 1000.");
        }
        return;
    }

    if ($nombre === "activo") {
        if (!in_array($texto, array("0", "1"), true)) {
            jbm_api_input_error("El campo '{$key}' debe ser 0 o 1.");
        }
        return;
    }

    if (preg_match('/^(monto|precio|costo|cantidad|stock|porcentaje|valor|diferencia|total|subtotal|descuento|efectivo|importe)(_|$)/', $nombre)
        || in_array($nombre, array("monto", "precio", "costo", "cantidad", "porcentaje", "valor", "total", "subtotal", "efectivo", "importe"), true)
    ) {
        $numero = validar_decimal_no_negativo($texto);
        if ($numero === false) {
            jbm_api_input_error("El campo '{$key}' debe ser un número no negativo con hasta dos decimales.");
        }
        if (strpos($nombre, "porcentaje") === 0 && $numero > 100) {
            jbm_api_input_error("El campo '{$key}' no puede superar 100.");
        }
        return;
    }

    $limitesTexto = array(
        "nombre" => 100,
        "apellido" => 100,
        "usuario" => 50,
        "correo" => 255,
        "email" => 255,
        "telefono" => 20,
        "direccion" => 255,
        "accion" => 60,
        "tipo" => 60,
        "tipo_movimiento" => 60,
        "campo" => 60,
        "filtro" => 255,
        "entidad" => 60,
        "estado" => 50,
        "descripcion" => 5000,
        "observacion" => 5000,
        "password" => 1024,
        "password_cierre" => 1024,
        "nueva_password" => 1024,
        "admin_password" => 1024,
        "contrasena_administrador" => 1024,
        "contrasena" => 1024,
        "nueva" => 1024,
        "repetir" => 1024
    );
    if (ctype_digit((string)$key) && (is_int($value) || is_float($value) || is_string($value))) {
        if (!preg_match('/^[+]?(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/', $texto)
            || !is_finite((float)$texto)
            || (float)$texto > 99999999.99
        ) {
            jbm_api_input_error("La cantidad ingresada no es un número no negativo válido.");
        }
        return;
    }

    if (!is_string($value)) {
        jbm_api_input_error("El campo '{$key}' debe ser texto.");
    }

    $maximo = $limitesTexto[$nombre] ?? 5000;
    if (!validar_texto($value, $maximo, false)) {
        jbm_api_input_error("El campo '{$key}' contiene texto inválido o supera el máximo de {$maximo} caracteres.");
    }

    if (in_array($nombre, array("desde", "hasta", "fecha", "fecha_hora", "fecha_entrega", "fecha_hora_entrega", "fecha_inicio", "fecha_fin"), true)
        && $value !== ""
    ) {
        $formatos = array("!Y-m-d", "!Y-m-d H:i", "!Y-m-d H:i:s", "!Y-m-d\\TH:i", "!Y-m-d\\TH:i:s");
        $fechaValida = false;
        foreach ($formatos as $formato) {
            $fecha = DateTimeImmutable::createFromFormat($formato, $value);
            $errores = DateTimeImmutable::getLastErrors();
            if ($fecha && ($errores === false || ($errores["warning_count"] === 0 && $errores["error_count"] === 0))) {
                $fechaValida = true;
                break;
            }
        }
        if (!$fechaValida) {
            jbm_api_input_error("El campo '{$key}' debe contener una fecha válida.");
        }
    }

}

function jbm_api_validate_tree($value, $key = "", $depth = 0)
{
    if ($depth > 8) {
        jbm_api_input_error("La solicitud contiene datos anidados en exceso.");
    }
    if (!is_array($value)) {
        jbm_api_validate_scalar($key, $value);
        return;
    }
    $nombre = jbm_api_normalize_key($key);
    if ($key !== "" && !ctype_digit($key) && !in_array($nombre, array("items", "productos", "pagos", "cantidades"), true)) {
        jbm_api_input_error("El campo '{$key}' debe ser un valor individual, no una lista.");
    }
    if (count($value) > 10000) {
        jbm_api_input_error("La solicitud contiene demasiados elementos.");
    }
    foreach ($value as $clave => $dato) {
        if (is_string($clave) && strlen($clave) > 100) {
            jbm_api_input_error("La solicitud contiene un nombre de campo demasiado largo.");
        }
        jbm_api_validate_tree($dato, (string)$clave, $depth + 1);
    }
}

function jbm_api_validate_request()
{
    $contentLength = $_SERVER["CONTENT_LENGTH"] ?? "";
    if ($contentLength !== "" && (!ctype_digit((string)$contentLength) || (int)$contentLength > 1048576)) {
        jbm_api_input_error("La solicitud supera el tamaño máximo permitido de 1 MB.");
    }

    jbm_api_validate_tree($_GET);
    jbm_api_validate_tree($_POST);

    $contentType = strtolower((string)($_SERVER["CONTENT_TYPE"] ?? ""));
    $stream = fopen("php://input", "rb");
    if ($stream === false) {
        jbm_api_input_error("No se pudo leer el contenido de la solicitud.");
    }
    $contenido = stream_get_contents($stream, 1048577);
    fclose($stream);
    if ($contenido === false) {
        jbm_api_input_error("No se pudo leer el contenido de la solicitud.");
    }
    if ($contenido === "") {
        return;
    }
    if (strlen($contenido) > 1048576) {
        jbm_api_input_error("La solicitud supera el tamaño máximo permitido de 1 MB.");
    }
    $primerCaracter = substr(ltrim($contenido), 0, 1);
    if (strpos($contentType, "application/json") === false
        && $primerCaracter !== "{" && $primerCaracter !== "[") {
        return;
    }
    $objeto = json_decode($contenido);
    if (json_last_error() !== JSON_ERROR_NONE || !($objeto instanceof stdClass)) {
        jbm_api_input_error("El contenido JSON de la solicitud no es válido.");
    }
    $entrada = json_decode($contenido, true, 9);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jbm_api_input_error("La solicitud contiene datos anidados en exceso.");
    }
    jbm_api_validate_tree($entrada);
}

try {
    jbm_api_validate_request();
} catch (JBMApiInputException $error) {
    http_response_code(400);
    echo json_encode(array("error" => $error->getMessage()), JSON_UNESCAPED_UNICODE);
    exit();
}
