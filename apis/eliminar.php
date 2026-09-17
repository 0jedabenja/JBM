<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();

header('Content-Type: application/json', true, 200);

if (isset($_GET["tipo"]) && isset($_GET["id"])) {
    $entidades = array(
        "insumo" => array("permiso" => "insumos", "tabla" => "Insumo", "columna" => "id_insumo"),
        "cliente" => array("permiso" => "clientes", "tabla" => "Cliente", "columna" => "id_cliente"),
        "producto" => array("permiso" => "productos", "tabla" => "Producto", "columna" => "id_producto"),
        "empleado" => array("permiso" => "empleados", "tabla" => "Empleado", "columna" => "id_empleado")
    );
    $tipo = $_GET["tipo"];
    $id = filter_var($_GET["id"], FILTER_VALIDATE_INT);

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
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(ucfirst($tipo) . " " . $id . " desactivado", JSON_UNESCAPED_UNICODE);

} else {
    header('Content-Type: application/json', true, 400);
    echo json_encode(["error" => ["Datos incompletos"]]);
}