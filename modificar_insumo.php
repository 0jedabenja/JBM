<?php
include_once("includes/conexionBD.php");
include_once("includes/funciones.php");
verificar_permiso("insumos");
$id_insumo = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if ($id_insumo === false || $id_insumo === null || $id_insumo <= 0) {
    http_response_code(400);
    exit("ID de insumo no válido.");
}

$mensaje = "";
$tipoMensaje = "";

$medidas = array();
$consultaMedidas = mysqli_query($conexion, "SELECT id_medida, unidad FROM Medida ORDER BY unidad ASC");
if ($consultaMedidas) {
    while ($row = mysqli_fetch_assoc($consultaMedidas)) {
        $medidas[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $stock_minimo = filter_var($_POST["stock_minimo"] ?? null, FILTER_VALIDATE_FLOAT);
    $costo = filter_var($_POST["costo"] ?? null, FILTER_VALIDATE_FLOAT);
    $id_medida = filter_var($_POST["id_medida"] ?? null, FILTER_VALIDATE_INT);

    if ($nombre === "") {
        $mensaje = "El nombre del insumo es obligatorio.";
        $tipoMensaje = "error";
    } elseif ($stock_minimo === false || $costo === false || $id_medida === false || $id_medida <= 0) {
        $mensaje = "Los valores ingresados no son válidos.";
        $tipoMensaje = "error";
    } else {
        $sql = "UPDATE Insumo
            SET nombre = ?, stock_minimo = ?, costo = ?, id_medida = ?
                WHERE id_insumo = ? AND activo = TRUE";
        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "sddii",
                $nombre,
                $stock_minimo,
                $costo,
                $id_medida,
                $id_insumo
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: insumos.php");
                exit();
            }

            $mensaje = "Error al actualizar el insumo.";
            $tipoMensaje = "error";
            error_log("Error MySQL UPDATE: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = "Error al preparar la consulta.";
            $tipoMensaje = "error";
        }
    }
}

$sql = "SELECT id_insumo, nombre, cantidad_actual, stock_minimo, costo, id_medida
        FROM Insumo
        WHERE id_insumo = ? AND activo = TRUE";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    http_response_code(500);
    exit("Error al preparar la consulta.");
}

mysqli_stmt_bind_param($stmt, "i", $id_insumo);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$insumo = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$insumo) {
    http_response_code(404);
    exit("Insumo no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Insumo</title>
    <link rel="stylesheet" href="assets/css/alta.css">
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(6) { background-color: #fff; }
        .navigation ul li:nth-child(6) a { color: #001f47; }
        .navigation ul li:nth-child(6) a .icon img { content: url('assets/img/sidebar/theme-box.svg'); }
    </style>
</head>
<body>
    <?php include_once "includes/sidebar.php"; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once "includes/profile.php"; ?>
        </div>

        <main class="alta-content">
            <section class="alta-panel">
                <h1>Modificar insumo</h1>
                <p>Actualiza los datos permitidos del insumo seleccionado.</p>

                <?php if ($mensaje !== ""): ?>
                    <p class="alta-error" role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?></p>
                <?php endif; ?>

    <form class="alta-form" action="modificar_insumo.php?id=<?php echo (int) $insumo["id_insumo"]; ?>" method="POST">
        <label for="nombre">Nombre del Insumo *</label>
        <input type="text" id="nombre" name="nombre" maxlength="100" required
               value="<?php echo htmlspecialchars($insumo["nombre"], ENT_QUOTES, "UTF-8"); ?>">

        <label for="id_medida">Unidad de Medida *</label>
        <select id="id_medida" name="id_medida" required>
            <option value="">Seleccione una opción...</option>
            <?php foreach ($medidas as $medida): ?>
                <option value="<?php echo $medida["id_medida"]; ?>"
                    <?php echo ((int)$medida["id_medida"] === (int)$insumo["id_medida"]) ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($medida["unidad"], ENT_QUOTES, "UTF-8"); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="cantidad_actual">Cantidad Actual</label>
        <input type="number" id="cantidad_actual" name="cantidad_actual" step="0.01" min="0" readonly
               value="<?php echo htmlspecialchars($insumo["cantidad_actual"], ENT_QUOTES, "UTF-8"); ?>">

        <label for="stock_minimo">Stock Mínimo</label>
        <input type="number" id="stock_minimo" name="stock_minimo" step="0.01" min="0" required
               value="<?php echo htmlspecialchars($insumo["stock_minimo"], ENT_QUOTES, "UTF-8"); ?>">

        <label for="costo">Costo</label>
        <input type="number" id="costo" name="costo" step="0.01" min="0" required
               value="<?php echo htmlspecialchars($insumo["costo"], ENT_QUOTES, "UTF-8"); ?>">

        <div class="alta-actions">
            <button type="submit">Guardar cambios</button>
            <a href="insumos.php">Volver al listado</a>
        </div>
    </form>
            </section>
        </main>
    </div>
</body>
</html>
