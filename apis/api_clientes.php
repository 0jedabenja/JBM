<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
if (!usuario_tiene_permiso("clientes") && !usuario_tiene_permiso("pedidos")) {
	verificar_permiso("clientes", true);
}
header('Content-Type: application/json; charset=utf-8');

$limiteParametro = obtener_parametro_get("limite", "20");
$limite = $limiteParametro === "sin" ? 9999999 : max(1, (int)$limiteParametro);
$pagina = max(1, (int)obtener_parametro_get("pagina", "1"));
$desdequeelemento = ($pagina - 1) * $limite;
$campo = obtener_parametro_get("campo", "todos");
$filtro = obtener_parametro_get("filtro");
$stmt = mysqli_prepare($conexion, "CALL sp_listar_clientes(?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssii", $filtro, $campo, $limite, $desdequeelemento);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$clientes = $resultado ? mysqli_fetch_all($resultado, MYSQLI_ASSOC) : array();
mysqli_stmt_close($stmt);
echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
