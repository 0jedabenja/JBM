<?php
require_once __DIR__ . "/../includes/api_json.php";
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
$empleados = obtener_listado_api($conexion, "empleados", $filtro, $campo, $limite, $desdequeelemento);
echo json_encode($empleados, JSON_UNESCAPED_UNICODE);