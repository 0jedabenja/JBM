<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("empleados", true);
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
    "id_empleado" => "e.id_empleado",
    "nombre" => "e.nombre",
    "apellido" => "e.apellido",
    "usuario" => "e.usuario",
    "rol" => "r.nombre"
);
$campo = $_GET["campo"] ?? "todos";

if (isset($_GET["filtro"]) && $_GET["filtro"] !== "undefined" && trim($_GET["filtro"]) !== "") {
    $filtro = "%" . trim($_GET["filtro"]) . "%";
    if (isset($camposPermitidos[$campo])) {
        $sql = "SELECT e.id_empleado, e.nombre, e.apellido, e.usuario, e.id_rol,
                       r.nombre AS rol
                FROM Empleado e
                INNER JOIN Rol r ON r.id_rol = e.id_rol
                WHERE e.activo = TRUE AND {$camposPermitidos[$campo]} LIKE ?
                ORDER BY e.apellido, e.nombre LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $filtro, $limite, $desdequeelemento);
    } else {
        $sql = "SELECT e.id_empleado, e.nombre, e.apellido, e.usuario, e.id_rol,
                       r.nombre AS rol
                FROM Empleado e
                INNER JOIN Rol r ON r.id_rol = e.id_rol
                WHERE e.activo = TRUE
                  AND (e.nombre LIKE ? OR e.apellido LIKE ? OR e.usuario LIKE ?
                   OR r.nombre LIKE ? OR e.id_empleado LIKE ?)
                ORDER BY e.apellido, e.nombre LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssssii", $filtro, $filtro, $filtro, $filtro, $filtro, $limite, $desdequeelemento);
    }
} else {
    $sql = "SELECT e.id_empleado, e.nombre, e.apellido, e.usuario, e.id_rol,
                   r.nombre AS rol
            FROM Empleado e
            INNER JOIN Rol r ON r.id_rol = e.id_rol
            WHERE e.activo = TRUE
            ORDER BY e.apellido, e.nombre
            LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $limite, $desdequeelemento);
}

mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$empleados = array();
while ($row = mysqli_fetch_assoc($resultado)) {
    $empleados[] = $row;
}

mysqli_stmt_close($stmt);
echo json_encode($empleados, JSON_UNESCAPED_UNICODE);