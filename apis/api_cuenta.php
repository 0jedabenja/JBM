<?php
require_once __DIR__ . "/../includes/api_json.php";
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
header("Content-Type: application/json; charset=utf-8");
$id = (int)$_SESSION["usuario_id"];
if ($_SERVER["REQUEST_METHOD"] === "GET") {
  $stmt = mysqli_prepare($conexion, "SELECT e.id_empleado, e.nombre, e.apellido, e.usuario, e.correo, e.id_rol, r.nombre AS rol FROM Empleado e INNER JOIN Rol r ON r.id_rol = e.id_rol WHERE e.id_empleado = ? AND e.activo = TRUE");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  $usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
  mysqli_stmt_close($stmt);
  echo json_encode($usuario, JSON_UNESCAPED_UNICODE);
  exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") { http_response_code(405); echo json_encode(["error"=>"Método no permitido"]); exit; }
$e=json_decode(file_get_contents("php://input"),true); if(!is_array($e)) $e=$_POST;
$nombre=trim((string)($e["nombre"]??"")); $apellido=trim((string)($e["apellido"]??"")); $usuario=trim((string)($e["usuario"]??"")); $correo=strtolower(trim((string)($e["correo"]??"")));
if(!$nombre||!$apellido||!$usuario||!filter_var($correo,FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(["error"=>"Datos no válidos"]); exit; }
$stmt = mysqli_prepare($conexion, "UPDATE Empleado SET nombre = ?, apellido = ?, usuario = ?, correo = ? WHERE id_empleado = ? AND activo = TRUE");
mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $apellido, $usuario, $correo, $id);
try {
  if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    http_response_code(409);
    echo json_encode(array("error" => "El usuario o correo ya están en uso"));
    exit;
  }
} catch (mysqli_sql_exception $error) {
  mysqli_stmt_close($stmt);
  if ($error->getCode() !== 1062) throw $error;
  http_response_code(409);
  echo json_encode(array("error" => "El usuario o correo ya están en uso"));
  exit;
}
mysqli_stmt_close($stmt);
$_SESSION["usuario"]=$usuario; $_SESSION["nombre_usuario"]=$nombre." ".$apellido; registrar_auditoria($conexion,"modificar_perfil","empleado",$id);
echo json_encode(["ok"=>true]);
