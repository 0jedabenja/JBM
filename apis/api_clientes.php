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
$camposPermitidos = array(
	"id_cliente" => "id_cliente",
	"nombre" => "nombre",
	"apellido" => "apellido",
	"telefono" => "telefono",
	"direccion" => "direccion"
);
$campo = obtener_parametro_get("campo", "todos");
$filtro = obtener_parametro_get("filtro");

if ($filtro !== "" && $filtro !== "undefined") {
	$filtro = "%" . $filtro . "%";
	if ($campo === "nombre_apellido") {
		$sql = "SELECT id_cliente, nombre, apellido, telefono, direccion
				FROM Cliente
				WHERE activo = TRUE
				  AND (CONCAT(nombre, ' ', apellido) LIKE ?
				   OR CONCAT(apellido, ' ', nombre) LIKE ?
				   OR nombre LIKE ?
				   OR apellido LIKE ?)
				ORDER BY apellido, nombre
				LIMIT ? OFFSET ?";
		$stmt = mysqli_prepare($conexion, $sql);
		mysqli_stmt_bind_param($stmt, "ssssii", $filtro, $filtro, $filtro, $filtro, $limite, $desdequeelemento);
	} elseif (isset($camposPermitidos[$campo])) {
		$sql = "SELECT id_cliente, nombre, apellido, telefono, direccion
				FROM Cliente
				WHERE activo = TRUE AND {$camposPermitidos[$campo]} LIKE ?
				ORDER BY apellido, nombre
				LIMIT ? OFFSET ?";
		$stmt = mysqli_prepare($conexion, $sql);
		mysqli_stmt_bind_param($stmt, "sii", $filtro, $limite, $desdequeelemento);
	} else {
		$sql = "SELECT id_cliente, nombre, apellido, telefono, direccion
				FROM Cliente
				WHERE activo = TRUE
				  AND (nombre LIKE ?
				   OR apellido LIKE ?
				   OR id_cliente LIKE ?
				   OR telefono LIKE ?
				   OR direccion LIKE ?)
				ORDER BY apellido, nombre
				LIMIT ? OFFSET ?";
		$stmt = mysqli_prepare($conexion, $sql);
		mysqli_stmt_bind_param($stmt, "sssssii", $filtro, $filtro, $filtro, $filtro, $filtro, $limite, $desdequeelemento);
	}
} else {
	$sql = "SELECT id_cliente, nombre, apellido, telefono, direccion
			FROM Cliente
			WHERE activo = TRUE
			ORDER BY apellido, nombre
			LIMIT ? OFFSET ?";
	$stmt = mysqli_prepare($conexion, $sql);
	mysqli_stmt_bind_param($stmt, "ii", $limite, $desdequeelemento);
}

mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$clientes = array();
while ($row = mysqli_fetch_assoc($resultado)) {
	$clientes[] = $row;
}

mysqli_stmt_close($stmt);
echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
