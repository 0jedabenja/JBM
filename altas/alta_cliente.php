<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion();
verificar_permiso("clientes");

$mensaje = "";
$rutaBase = "../";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $apellido = trim($_POST["apellido"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");

    if ($nombre === "" || $apellido === "") {
        $mensaje = "El nombre y el apellido son obligatorios.";
    } elseif (strlen($telefono) > 20 || strlen($direccion) > 255) {
        $mensaje = "El teléfono o la dirección superan la longitud permitida.";
    } else {
        $sql = "INSERT INTO Cliente (nombre, apellido, telefono, direccion)
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $nombre, $apellido, $telefono, $direccion);
            if (mysqli_stmt_execute($stmt)) {
                $idCliente = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt);
                registrar_auditoria($conexion, "crear_cliente", "cliente", $idCliente);
                header("Location: ../clientes.php");
                exit();
            }
            $mensaje = "No se pudo registrar el cliente.";
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = "Error al preparar la consulta.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente</title>
    <script src="../assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="../assets/css/alta_cliente.css">
</head>
<body>
    <?php include_once "../includes/sidebar.php"; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle">
                <img src="../assets/img/sidebar/dark-menu.svg" alt="Abrir menú">
            </div>

            <?php include_once "../includes/profile.php"; ?>
        </div>

        <main class="alta-content">
            <section class="alta-panel">
                <h1>Registrar nuevo cliente</h1>
                <p>Completa los datos del cliente para incorporarlo al sistema.</p>

                <?php if ($mensaje !== ""): ?>
                    <p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p>
                <?php endif; ?>

                <form class="alta-form" action="alta_cliente.php" method="POST">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required
                           value="<?php echo htmlspecialchars($_POST["nombre"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

                    <label for="apellido">Apellido *</label>
                    <input type="text" id="apellido" name="apellido" maxlength="100" required
                           value="<?php echo htmlspecialchars($_POST["apellido"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" maxlength="20"
                           value="<?php echo htmlspecialchars($_POST["telefono"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion" maxlength="255"
                           value="<?php echo htmlspecialchars($_POST["direccion"] ?? "", ENT_QUOTES, "UTF-8"); ?>">

                    <div class="alta-actions">
                        <button type="submit">Guardar cliente</button>
                        <a href="../clientes.php">Volver al listado</a>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>