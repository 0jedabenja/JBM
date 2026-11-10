<?php
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
require_once __DIR__ . "/../includes/validacion.php";
verificar_sesion();
verificar_permiso("insumos");
$mensaje = "";
$tipoMensaje = "";
$rutaBase = "../";

$medidas = array();
$consultaMedidas = mysqli_query($conexion, "SELECT id_medida, unidad FROM Medida ORDER BY unidad ASC");
if ($consultaMedidas) {
    while ($row = mysqli_fetch_assoc($consultaMedidas)) {
        $medidas[] = $row;
    }
}
$categorias = array();
$consultaCategorias = mysqli_query($conexion, "SELECT id_categoria, titulo FROM Categoria ORDER BY titulo ASC");
if ($consultaCategorias) {
    while ($row = mysqli_fetch_assoc($consultaCategorias)) {
        $categorias[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $nombre = is_string($_POST["nombre"] ?? null) ? trim($_POST["nombre"]) : "";
    $cantidad_actual = validar_decimal_no_negativo($_POST["cantidad_actual"] ?? null);
    $stock_minimo = validar_decimal_no_negativo($_POST["stock_minimo"] ?? null);
    $costo = validar_decimal_no_negativo($_POST["costo"] ?? null);
    $id_medida = filter_var($_POST["id_medida"] ?? 0, FILTER_VALIDATE_INT);
    $id_categoria = filter_var($_POST["id_categoria"] ?? null, FILTER_VALIDATE_INT);

    if ($nombre === "" || !mb_check_encoding($nombre, "UTF-8") || mb_strlen($nombre, "UTF-8") > 100) {
        $mensaje = "El nombre del insumo es obligatorio y no puede superar los 100 caracteres.";
        $tipoMensaje = "error";
    } elseif ($cantidad_actual === false || $stock_minimo === false || $costo === false || $id_medida === false || $id_medida === null || $id_medida <= 0 || $id_categoria === false || $id_categoria === null || $id_categoria <= 0) {
        $mensaje = "Ingrese cantidades y costos no negativos, con hasta dos decimales, y seleccione una unidad y categoría válidas.";
        $tipoMensaje = "error";
    } elseif (!in_array($id_medida, array_map("intval", array_column($medidas, "id_medida")), true)
        || !in_array($id_categoria, array_map("intval", array_column($categorias, "id_categoria")), true)
    ) {
        $mensaje = "La unidad de medida o la categoría seleccionada no existe.";
        $tipoMensaje = "error";
    } else {
        mysqli_begin_transaction($conexion);
        $sql = "INSERT INTO Insumo (nombre, cantidad_actual, stock_minimo, costo, id_medida, id_categoria)
            VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sdddii", $nombre, $cantidad_actual, $stock_minimo, $costo, $id_medida, $id_categoria);
            if (mysqli_stmt_execute($stmt)) {
                $idInsumo = mysqli_insert_id($conexion);
                $movimientoRegistrado = registrar_movimiento_insumo($conexion, $idInsumo, "saldo_inicial", $cantidad_actual, $costo);
                if ($movimientoRegistrado) {
                    mysqli_commit($conexion);
                    registrar_auditoria($conexion, "crear_insumo", "insumo", $idInsumo, array("nombre" => $nombre, "saldo_inicial" => $cantidad_actual));
                    $mensaje = "¡Insumo registrado con éxito!";
                    $tipoMensaje = "exito";
                } else {
                    mysqli_rollback($conexion);
                    $mensaje = "No se pudo guardar el movimiento de saldo inicial.";
                    $tipoMensaje = "error";
                }
            } else {
                mysqli_rollback($conexion);
                $mensaje = "Error al guardar el insumo en la base de datos.";
                $tipoMensaje = "error";
                error_log("Error MySQL INSERT: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = "Error al preparar la consulta.";
            $tipoMensaje = "error";
            mysqli_rollback($conexion);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Insumo</title>
    <link rel="stylesheet" href="../assets/css/alta.css">
    <script src="../assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="../assets/css/alta_insumo.css">
</head>
<body>
    <?php include_once "../includes/sidebar.php"; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="../assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once "../includes/profile.php"; ?>
        </div>

        <main class="alta-content">
            <section class="alta-panel">
                <h1>Registrar nuevo insumo</h1>
                <p>Completa los datos del insumo y su unidad de medida.</p>

                <?php if ($mensaje !== ""): ?>
                    <p class="<?php echo $tipoMensaje === "exito" ? "alta-success" : "alta-error"; ?>" role="alert">
                        <?php echo htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"); ?>
                    </p>
                <?php endif; ?>

        <form class="alta-form" action="alta_insumo.php" method="POST">
            
            <div class="campo">
                <label for="nombre">Nombre del Insumo *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="100">
            </div>

            <div class="campo">
                <label for="id_medida">Unidad de Medida *</label>
                <select id="id_medida" name="id_medida" required>
                    <option value="">Seleccione una opción...</option>
                    <?php foreach ($medidas as $medida): ?>
                        <option value="<?php echo $medida['id_medida']; ?>">
                            <?php echo htmlspecialchars($medida['unidad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="id_categoria">Categoría *</label>
                <select id="id_categoria" name="id_categoria" required>
                    <option value="">Seleccione una categoría...</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo (int)$categoria["id_categoria"]; ?>"><?php echo htmlspecialchars($categoria["titulo"], ENT_QUOTES, "UTF-8"); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label for="cantidad_actual">Cantidad Inicial</label>
                <input type="number" id="cantidad_actual" name="cantidad_actual" step="0.01" min="0" max="99999999.99" value="0" required>
            </div>

            <div class="campo">
                <label for="stock_minimo">Stock Mínimo (Aviso)</label>
                <input type="number" id="stock_minimo" name="stock_minimo" step="0.01" min="0" max="99999999.99" value="0" required>
            </div>

            <div class="campo">
                <label for="costo">Costo ($)</label>
                <input type="number" id="costo" name="costo" step="0.01" min="0" max="99999999.99" value="0" required>
            </div>

            <div class="alta-actions">
                <button type="submit" class="btn">Guardar Insumo</button>
                <a href="../insumos.php" class="btn btn-volver">Volver al Listado</a>
            </div>

        </form>
            </section>
        </main>
    </div>

</body>
</html>