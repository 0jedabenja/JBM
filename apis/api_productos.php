<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
if (!usuario_tiene_permiso("productos") && !usuario_tiene_permiso("pedidos")) {
    verificar_permiso("productos", true);
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
    "id_producto" => "p.id_producto",
    "nombre" => "p.nombre",
    "precio" => "p.precio",
    "categoria" => "c.titulo"
);
$campo = $_GET["campo"] ?? "todos";

if (isset($_GET["filtro"]) && $_GET["filtro"] !== "undefined" && trim($_GET["filtro"]) !== "") {
    $filtro = "%" . $_GET["filtro"] . "%";
    if (isset($camposPermitidos[$campo])) {
        $sql = "SELECT p.id_producto, p.nombre, p.precio, p.id_categoria,
                       c.titulo AS categoria
                FROM Producto p
                LEFT JOIN Categoria c ON c.id_categoria = p.id_categoria
                WHERE p.activo = TRUE AND {$camposPermitidos[$campo]} LIKE ?
                ORDER BY p.nombre LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $filtro, $limite, $desdequeelemento);
    } else {
        $sql = "SELECT p.id_producto, p.nombre, p.precio, p.id_categoria,
                       c.titulo AS categoria
                FROM Producto p
                LEFT JOIN Categoria c ON c.id_categoria = p.id_categoria
                WHERE p.activo = TRUE
                  AND (p.nombre LIKE ? OR p.id_producto LIKE ? OR p.precio LIKE ? OR c.titulo LIKE ?)
                ORDER BY p.nombre LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssssii", $filtro, $filtro, $filtro, $filtro, $limite, $desdequeelemento);
    }
} else {
    $sql = "SELECT p.id_producto, p.nombre, p.precio, p.id_categoria,
                   c.titulo AS categoria
            FROM Producto p
            LEFT JOIN Categoria c ON c.id_categoria = p.id_categoria
            WHERE p.activo = TRUE
            ORDER BY p.nombre
            LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $limite, $desdequeelemento);
}

mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$productos = array();
while ($row = mysqli_fetch_assoc($resultado)) {
    $productos[] = $row;
}

mysqli_stmt_close($stmt);
echo json_encode($productos, JSON_UNESCAPED_UNICODE);