<?php
include_once("../includes/conexionBD.php");
include_once("../includes/funciones.php");
verificar_sesion();
$mensaje = "";
$tipoMensaje = "";

$medidas = array();
$consultaMedidas = mysqli_query($conexion, "SELECT id_medida, unidad FROM medida ORDER BY unidad ASC");
if ($consultaMedidas) {
    while ($row = mysqli_fetch_assoc($consultaMedidas)) {
        $medidas[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $nombre = trim($_POST["nombre"] ?? '');
    $cantidad_actual = filter_var($_POST["cantidad_actual"] ?? 0, FILTER_VALIDATE_FLOAT);
    $stock_minimo = filter_var($_POST["stock_minimo"] ?? 0, FILTER_VALIDATE_FLOAT);
    $costo = filter_var($_POST["costo"] ?? 0, FILTER_VALIDATE_FLOAT);
    $id_medida = filter_var($_POST["id_medida"] ?? 0, FILTER_VALIDATE_INT);

    if (empty($nombre)) {
        $mensaje = "El nombre del insumo es obligatorio.";
        $tipoMensaje = "error";
    } elseif ($cantidad_actual === false || $stock_minimo === false || $costo === false || $id_medida === false) {
        $mensaje = "Los valores numéricos ingresados no son válidos.";
        $tipoMensaje = "error";
    } else {
        
        $sql = "INSERT INTO Insumo (nombre, cantidad_actual, stock_minimo, costo, id_medida) 
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sdddi", $nombre, $cantidad_actual, $stock_minimo, $costo, $id_medida);
            if (mysqli_stmt_execute($stmt)) {
                $mensaje = "¡Insumo registrado con éxito!";
                $tipoMensaje = "exito";
            } else {
                $mensaje = "Error al guardar el insumo en la base de datos.";
                $tipoMensaje = "error";
                error_log("Error MySQL INSERT: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = "Error al preparar la consulta.";
            $tipoMensaje = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Insumo</title>
</head>
<body>

    <div class="form-container">
        <h2>Registrar Nuevo Insumo</h2>

        <form action="alta_insumo.php" method="POST">
            
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
                <label for="cantidad_actual">Cantidad Inicial</label>
                <input type="number" id="cantidad_actual" name="cantidad_actual" step="0.01" min="0" value="0">
            </div>

            <div class="campo">
                <label for="stock_minimo">Stock Mínimo (Aviso)</label>
                <input type="number" id="stock_minimo" name="stock_minimo" step="0.01" min="0" value="0">
            </div>

            <div class="campo">
                <label for="costo">Costo ($)</label>
                <input type="number" id="costo" name="costo" step="0.01" min="0" value="0">
            </div>

            <button type="submit" class="btn">Guardar Insumo</button>
            <a href="../insumos.php" class="btn btn-volver">Volver al Listado</a>

        </form>
    </div>

</body>
</html>