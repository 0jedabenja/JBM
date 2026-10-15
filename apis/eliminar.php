<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(array("error" => "Método no permitido."), JSON_UNESCAPED_UNICODE);
    exit();
}

$entrada = json_decode(file_get_contents("php://input"), true);
if (!is_array($entrada)) $entrada = $_POST;

// Solo se interpolan identificadores de esta lista; el ID se enlaza como parámetro.
    $entidades = array(
        "insumo" => array("permiso" => "insumos", "tabla" => "Insumo", "columna" => "id_insumo"),
        "cliente" => array("permiso" => "clientes", "tabla" => "Cliente", "columna" => "id_cliente"),
        "producto" => array("permiso" => "productos", "tabla" => "Producto", "columna" => "id_producto"),
        "empleado" => array("permiso" => "empleados", "tabla" => "Empleado", "columna" => "id_empleado")
    );
    $tipo = is_string($entrada["tipo"] ?? null) ? trim($entrada["tipo"]) : "";
    $id = filter_var($entrada["id"] ?? null, FILTER_VALIDATE_INT);

    if (!isset($entidades[$tipo]) || $id === false || $id <= 0) {
        http_response_code(400);
        echo json_encode(array("error" => "Datos no válidos."), JSON_UNESCAPED_UNICODE);
        exit();
    }

    verificar_permiso($entidades[$tipo]["permiso"], true);
    $entidad = $entidades[$tipo];
    $sql = "UPDATE {$entidad["tabla"]} SET activo = FALSE WHERE {$entidad["columna"]} = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(array("error" => "No se pudo preparar la operación."), JSON_UNESCAPED_UNICODE);
        exit();
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode(array("error" => "No se pudo completar la operación."), JSON_UNESCAPED_UNICODE);
        exit();
    }
    mysqli_stmt_close($stmt);
    registrar_auditoria($conexion, "desactivar_" . $tipo, $tipo, $id);
    echo json_encode(ucfirst($tipo) . " " . $id . " desactivado", JSON_UNESCAPED_UNICODE);
