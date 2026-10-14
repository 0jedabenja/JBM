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
$camposPermitidos = array(
    "id_empleado" => "e.id_empleado",
    "nombre" => "e.nombre",
    "apellido" => "e.apellido",
    "usuario" => "e.usuario",
    "rol" => "r.nombre"
);
$campo = obtener_parametro_get("campo", "todos");
$filtro = obtener_parametro_get("filtro");

if ($filtro !== "" && $filtro !== "undefined") {
    $filtro = "%" . $filtro . "%";
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