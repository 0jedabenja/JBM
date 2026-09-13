<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();

header('Content-Type: application/json', true, 200);

if (isset($_GET["tipo"]) && isset($_GET["id"])) {

    $id = (int)$_GET["id"];

    switch ($_GET['tipo']) {
        case "insumo":
            mysqli_query($conexion, 'UPDATE Insumo SET activo = FALSE WHERE id_insumo = ' . $id);
            echo json_encode("Insumo " . $id . " Desactivado");
            break;
        case "cliente":
            mysqli_query($conexion, 'UPDATE Cliente SET activo = FALSE WHERE id_cliente = ' . $id);
            echo json_encode("Cliente " . $id . " Desactivado");
            break;
        case "producto":
            mysqli_query($conexion, 'UPDATE Producto SET activo = FALSE WHERE id_producto = ' . $id);
            echo json_encode("Producto " . $id . " Desactivado");
            break;
        case "empleado":
            mysqli_query($conexion, 'UPDATE Empleado SET activo = FALSE WHERE id_empleado = ' . $id);
            echo json_encode("Empleado " . $id . " Desactivado");
            break;
    }

} else {
    header('Content-Type: application/json', true, 400);
    echo json_encode(["error" => ["Datos incompletos"]]);
}