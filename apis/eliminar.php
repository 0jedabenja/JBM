<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");

header('Content-Type: application/json', true, 200);

if (isset($_GET["tipo"]) && isset($_GET["id"])) {

    $id = (int)$_GET["id"];

    switch ($_GET['tipo']) {
        case "insumo":
            mysqli_query($conexion, 'UPDATE Insumo SET activo = FALSE WHERE id_insumo = ' . $id);
            echo json_encode("Insumo " . $id . " Desactivado");
            break;
    }

} else {
    header('Content-Type: application/json', true, 400);
    echo json_encode(["error" => ["Datos incompletos"]]);
}