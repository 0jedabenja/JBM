<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
if (!usuario_tiene_permiso("clientes") && !usuario_tiene_permiso("pedidos")) {
	verificar_permiso("clientes", true);
}
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
$camposPermitidos = array(
	"id_cliente" => "id_cliente",
	"nombre" => "nombre",
	"apellido" => "apellido",
	"telefono" => "telefono",
	"direccion" => "direccion"
);
$campo = $_GET["campo"] ?? "todos";

if (isset($_GET["filtro"]) && $_GET["filtro"] !== "undefined" && trim($_GET["filtro"]) !== "") {
	$filtro = "%" . trim($_GET["filtro"]) . "%";
	if (isset($camposPermitidos[$campo])) {
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
