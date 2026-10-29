<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
header("Content-Type: application/json; charset=utf-8");
$id = (int)$_SESSION["usuario_id"];
if ($_SERVER["REQUEST_METHOD"] === "GET") {
  $stmt = mysqli_prepare($conexion, "CALL sp_datos_usuario(?)"); mysqli_stmt_bind_param($stmt,"i",$id); mysqli_stmt_execute($stmt); $r=mysqli_stmt_get_result($stmt); $u=$r?mysqli_fetch_assoc($r):null; mysqli_stmt_close($stmt); echo json_encode($u,JSON_UNESCAPED_UNICODE); exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") { http_response_code(405); echo json_encode(["error"=>"Método no permitido"]); exit; }
$e=json_decode(file_get_contents("php://input"),true); if(!is_array($e)) $e=$_POST;
$nombre=trim((string)($e["nombre"]??"")); $apellido=trim((string)($e["apellido"]??"")); $usuario=trim((string)($e["usuario"]??"")); $correo=strtolower(trim((string)($e["correo"]??"")));
if(!$nombre||!$apellido||!$usuario||!filter_var($correo,FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(["error"=>"Datos no válidos"]); exit; }
$stmt=mysqli_prepare($conexion,"CALL sp_actualizar_perfil(?, ?, ?, ?, ?)"); mysqli_stmt_bind_param($stmt,"issss",$id,$nombre,$apellido,$usuario,$correo);
if(!mysqli_stmt_execute($stmt)){http_response_code(409);echo json_encode(["error"=>"El usuario o correo ya están en uso"]);exit;} mysqli_stmt_close($stmt);
$_SESSION["usuario"]=$usuario; $_SESSION["nombre_usuario"]=$nombre." ".$apellido; registrar_auditoria($conexion,"modificar_perfil","empleado",$id);
echo json_encode(["ok"=>true]);
