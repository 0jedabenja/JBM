<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("insumos", true);
header('Content-Type: application/json; charset=utf-8');

$limiteParametro = obtener_parametro_get("limite", "20");
$limite = $limiteParametro === "sin" ? 9999999 : max(1, (int)$limiteParametro);
$pagina = max(1, (int)obtener_parametro_get("pagina", "1"));

$desdequeelemento = ($pagina - 1) * $limite;
$camposPermitidos = array(
    "id_insumo" => "i.id_insumo",
    "nombre" => "i.nombre",
    "unidad" => "m.unidad",
    "cantidad_actual" => "i.cantidad_actual",
    "stock_minimo" => "i.stock_minimo",
    "costo" => "i.costo"
);
$campo = obtener_parametro_get("campo", "todos");
$filtro = obtener_parametro_get("filtro");

if ($filtro !== "" && $filtro !== "undefined") {
    $filtro = "%" . $filtro . "%";
    if (isset($camposPermitidos[$campo])) {
        $sql = "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.stock_minimo, i.costo, i.id_medida, m.unidad
                FROM Insumo i
                LEFT JOIN Medida m ON m.id_medida = i.id_medida
                WHERE i.activo = TRUE AND {$camposPermitidos[$campo]} LIKE ?
                ORDER BY i.nombre LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $filtro, $limite, $desdequeelemento);
    } else {
        $sql = "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.stock_minimo, i.costo, i.id_medida, m.unidad
                FROM Insumo i
                LEFT JOIN Medida m ON m.id_medida = i.id_medida
                WHERE i.activo = TRUE
                  AND (i.nombre LIKE ? OR i.id_insumo LIKE ? OR m.unidad LIKE ?
                   OR i.cantidad_actual LIKE ? OR i.stock_minimo LIKE ? OR i.costo LIKE ?)
                ORDER BY i.nombre LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssii", $filtro, $filtro, $filtro, $filtro, $filtro, $filtro, $limite, $desdequeelemento);
    }
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