<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion_api();
verificar_permiso("empleados", true);
header("Content-Type: application/json; charset=utf-8");
if ($_SERVER["REQUEST_METHOD"] !== "POST") { http_response_code(405); echo json_encode(["error"=>"Método no permitido"]); exit; }
$entrada = json_decode(file_get_contents("php://input"), true);
if (!is_array($entrada)) $entrada = $_POST;
$id = filter_var($entrada["id_empleado"] ?? null, FILTER_VALIDATE_INT);
$nueva = trim((string)($entrada["nueva_password"] ?? ""));
$admin = (string)($entrada["admin_password"] ?? "");
if (!$id || strlen($nueva) < 8 || $admin === "") { http_response_code(400); echo json_encode(["error"=>"Datos incompletos o contraseña inválida"]); exit; }
$stmt = mysqli_prepare($conexion, "CALL sp_password_hash(?)");
mysqli_stmt_bind_param($stmt, "i", $_SESSION["usuario_id"]);
mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt); $fila = $resultado ? mysqli_fetch_assoc($resultado) : null; mysqli_stmt_close($stmt);
if (!$fila || !password_verify($admin, $fila["contraseña"])) { http_response_code(403); echo json_encode(["error"=>"La contraseña del administrador no es válida"]); exit; }
$stmt = mysqli_prepare($conexion, "CALL sp_reestablecer_password_admin(?, ?)");
$hash = password_hash($nueva, PASSWORD_DEFAULT);
mysqli_stmt_bind_param($stmt, "is", $id, $hash);
mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt); $respuesta = $resultado ? mysqli_fetch_assoc($resultado) : ["filas_afectadas"=>0]; mysqli_stmt_close($stmt);
if ((int)($respuesta["filas_afectadas"] ?? 0) !== 1) { http_response_code(404); echo json_encode(["error"=>"Usuario no encontrado"]); exit; }
registrar_auditoria($conexion, "reestablecer_password_admin", "empleado", $id);
echo json_encode(["ok"=>true]);
