<?php
$codigosError = array(
    400 => array("Solicitud no válida", "Revisá la dirección o los datos enviados e intentá nuevamente."),
    401 => array("Necesitás iniciar sesión", "Iniciá sesión para continuar."),
    403 => array("Acceso denegado", "No tenés permiso para acceder a esta página."),
    404 => array("Página no encontrada", "La página que buscás no existe o fue movida."),
    405 => array("Operación no permitida", "El método usado para esta solicitud no está disponible."),
    408 => array("La solicitud tardó demasiado", "La conexión demoró más de lo esperado. Intentá nuevamente."),
    410 => array("Contenido no disponible", "Este contenido ya no está disponible."),
    413 => array("Solicitud demasiado grande", "Reducí el tamaño de los datos enviados e intentá nuevamente."),
    414 => array("Dirección demasiado larga", "La dirección solicitada es demasiado larga."),
    415 => array("Formato no compatible", "El formato enviado no es compatible con esta operación."),
    422 => array("No se pudo procesar la solicitud", "Revisá la información ingresada e intentá nuevamente."),
    429 => array("Demasiadas solicitudes", "Esperá un momento antes de volver a intentarlo."),
    500 => array("Error interno", "Ocurrió un problema inesperado. Intentá nuevamente más tarde."),
    501 => array("Operación no implementada", "Esta operación todavía no está disponible."),
    502 => array("Servicio no disponible", "No se pudo obtener una respuesta válida del servicio."),
    503 => array("Servicio temporalmente no disponible", "Intentá nuevamente en unos minutos."),
    504 => array("Tiempo de espera agotado", "El servicio tardó demasiado en responder. Intentá nuevamente.")
);
$codigoSolicitado = isset($codigoError) ? (int)$codigoError : (int)($_GET["codigo"] ?? 500);
$codigoError = isset($codigosError[$codigoSolicitado]) ? $codigoSolicitado : 500;
$tituloError = $codigosError[$codigoError][0];
$mensajeError = $codigosError[$codigoError][1];
http_response_code($codigoError);
$rutaBase = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
if ($rutaBase === "." || $rutaBase === "/") $rutaBase = "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $codigoError; ?> · <?php echo htmlspecialchars($tituloError, ENT_QUOTES, "UTF-8"); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($rutaBase, ENT_QUOTES, "UTF-8"); ?>/assets/css/errores.css">
</head>
<body>
    <main class="error-card">
        <p class="error-code"><?php echo $codigoError; ?></p>
        <h1><?php echo htmlspecialchars($tituloError, ENT_QUOTES, "UTF-8"); ?></h1>
        <p><?php echo htmlspecialchars($mensajeError, ENT_QUOTES, "UTF-8"); ?></p>
        <nav class="error-actions" aria-label="Opciones">
            <a class="error-button" href="<?php echo htmlspecialchars($rutaBase, ENT_QUOTES, "UTF-8"); ?>/login.php">Ir al inicio</a>
            <button class="error-button secondary" type="button" onclick="history.back()">Volver</button>
        </nav>
    </main>
</body>
</html>
