<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET["limite"])) {
    $limite = 20;
} else {
    if ($_GET["limite"] === "sin") {
        $limite = 9999999;
    } else {
        $limite = max(1, (int)$_GET["limite"]);
    }
}

if (!isset($_GET["pagina"])) {
    $pagina = 1;
} else {
    $pagina = max(1, (int)$_GET["pagina"]);
}

$desdequeelemento = ($pagina - 1) * $limite;

if (isset($_GET["filtro"]) && $_GET["filtro"] !== "undefined" && trim($_GET["filtro"]) !== "") {
        $sql = "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.stock_minimo, i.costo, i.id_medida, m.unidad
            FROM Insumo i
            LEFT JOIN Medida m ON m.id_medida = i.id_medida
            WHERE i.activo = TRUE
              AND (nombre LIKE ? 
               OR id_insumo LIKE ? 
               OR costo LIKE ?)
            ORDER BY nombre 
            LIMIT ? OFFSET ?";     
    $stmt = mysqli_prepare($conexion, $sql);
    $filtro = "%" . $_GET["filtro"] . "%";
    mysqli_stmt_bind_param($stmt, "sssii", $filtro, $filtro, $filtro, $limite, $desdequeelemento);
} else {
        $sql = "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.stock_minimo, i.costo, i.id_medida, m.unidad
            FROM Insumo i
            LEFT JOIN Medida m ON m.id_medida = i.id_medida
            WHERE i.activo = TRUE
            ORDER BY nombre 
            LIMIT ? OFFSET ?";        
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $limite, $desdequeelemento);
}
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$insumos = array();
while ($row = mysqli_fetch_assoc($resultado)) {
    $insumos[] = $row;
}
mysqli_stmt_close($stmt);
echo json_encode($insumos, JSON_UNESCAPED_UNICODE);