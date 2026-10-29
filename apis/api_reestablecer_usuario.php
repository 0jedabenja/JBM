<?php
require_once __DIR__ . "/../includes/api_json.php";
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
$stmt = mysqli_prepare($conexion, "SELECT contraseña FROM Empleado WHERE id_empleado = ? AND activo = TRUE");
mysqli_stmt_bind_param($stmt, "i", $_SESSION["usuario_id"]);
mysqli_stmt_execute($stmt); $resultado = mysqli_stmt_get_result($stmt); $fila = $resultado ? mysqli_fetch_assoc($resultado) : null; mysqli_stmt_close($stmt);
if (!$fila || !password_verify($admin, $fila["contraseña"])) { http_response_code(403); echo json_encode(["error"=>"La contraseña del administrador no es válida"]); exit; }
$stmt = mysqli_prepare($conexion, "UPDATE Empleado SET contraseña = ?, token_recuperacion = NULL, token_expiracion = NULL WHERE id_empleado = ? AND activo = TRUE");
$hash = password_hash($nueva, PASSWORD_DEFAULT);
mysqli_stmt_bind_param($stmt, "si", $hash, $id);
mysqli_stmt_execute($stmt);
$filasAfectadas = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);
if ($filasAfectadas !== 1) { http_response_code(404); echo json_encode(array("error"=>"Usuario no encontrado")); exit; }
registrar_auditoria($conexion, "reestablecer_password_admin", "empleado", $id);
echo json_encode(["ok"=>true]);
