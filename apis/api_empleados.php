<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("empleados", true);
header('Content-Type: application/json; charset=utf-8');

$limiteParametro = obtener_parametro_get("limite", "20");
$limite = $limiteParametro === "sin" ? 9999999 : max(1, (int)$limiteParametro);
$pagina = max(1, (int)obtener_parametro_get("pagina", "1"));
$desdequeelemento = ($pagina - 1) * $limite;
$filtro = obtener_parametro_get("filtro");
$campo = obtener_parametro_get("campo", "todos");
$stmt = mysqli_prepare($conexion, "CALL sp_listar_empleados(?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssii", $filtro, $campo, $limite, $desdequeelemento);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$empleados = $resultado ? mysqli_fetch_all($resultado, MYSQLI_ASSOC) : array();
mysqli_stmt_close($stmt);
echo json_encode($empleados, JSON_UNESCAPED_UNICODE);