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
                mysqli_stmt_close($stmt);
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
    <style>
        .navigation ul li:nth-child(5) {
            background-color: #fff;
        }

        .navigation ul li:nth-child(5) a {
            color: #001f47;
        }

        .navigation ul li:nth-child(5) a .icon img {
            content: url('../assets/img/sidebar/theme-person.svg');
        }

        .alta-content {
            padding: 30px;
        }

        .alta-panel {
            background: #fff;
            border: 1px solid #e4e7eb;
            border-radius: 12px;
            margin: 20px auto;
            max-width: 760px;
            padding: 30px;
        }

        .alta-panel h1 {
            color: #001f47;
            margin-bottom: 8px;
        }

        .alta-panel p {
            color: #666;
            margin-bottom: 25px;
        }

        .alta-form {
            display: grid;
            gap: 8px;
        }

        .alta-form label {
            color: #001f47;
            font-weight: bold;
            margin-top: 10px;
        }

        .alta-form input {
            border: 1px solid #bbb;
            border-radius: 6px;
            font-size: 1rem;
            padding: 12px;
        }

        .alta-form input:focus {
            border-color: #001f47;
            outline: 2px solid rgba(0, 31, 71, 0.15);
        }

        .alta-error {
            background: #fff1f1;
            border: 1px solid #d9534f;
            border-radius: 6px;
            color: #a52b27;
            padding: 12px;
        }

        .alta-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .alta-actions button,
        .alta-actions a {
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            padding: 12px 18px;
            text-decoration: none;
        }

        .alta-actions button {
            background: #001f47;
            border: 1px solid #001f47;
            color: #fff;
        }

        .alta-actions a {
            border: 1px solid #001f47;
            color: #001f47;
        }

        @media (max-width: 700px) {
            .alta-content {
                padding: 15px;
            }

            .alta-panel {
                padding: 20px;
            }

            .alta-actions {
                flex-direction: column;
            }
        }
    </style>
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