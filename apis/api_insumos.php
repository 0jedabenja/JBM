<?php
require_once __DIR__ . "/../includes/api_json.php";
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("insumos", true);
header('Content-Type: application/json; charset=utf-8');

$limiteParametro = obtener_parametro_get("limite", "20");
$limite = $limiteParametro === "sin" ? 9999999 : max(1, (int)$limiteParametro);
$pagina = max(1, (int)obtener_parametro_get("pagina", "1"));
$desdequeelemento = ($pagina - 1) * $limite;
$campo = obtener_parametro_get("campo", "todos");
$filtro = obtener_parametro_get("filtro");
$insumos = obtener_listado_api($conexion, "insumos", $filtro, $campo, $limite, $desdequeelemento);
echo json_encode($insumos, JSON_UNESCAPED_UNICODE);