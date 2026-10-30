<?php
include_once __DIR__ . "/includes/funciones.php";
verificar_permiso("insumos");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar salida de stock</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/ajustes-stock.css">
</head>
<body>
<?php include_once __DIR__ . "/includes/sidebar.php"; ?>
<div class="main">
    <div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once __DIR__ . "/includes/profile.php"; ?></div>
    <main class="ajuste-stock">
        <header class="page-heading">
            <h1>Registrar salida de stock</h1>
            <p>Registra pérdidas, insumos dañados o consumo del personal. Las salidas se descuentan del inventario y quedan en el historial de movimientos.</p>
        </header>
        <form id="formAjusteStock">
            <label for="tipoMovimiento">Motivo</label>
            <select id="tipoMovimiento" required>
                <option value="perdida">Pérdida</option>
                <option value="averia">Insumo dañado o en mal estado</option>
                <option value="consumo_personal">Consumo del personal</option>
            </select>
            <label for="tipoElemento">Qué se retira</label>
            <select id="tipoElemento" required>
                <option value="insumo">Insumo</option>
                <option value="producto">Producto del menú (descuenta su receta)</option>
            </select>
            <label for="elementoStock" id="etiquetaElemento">Insumo</label>
            <select id="elementoStock" required></select>
            <label for="cantidadStock">Cantidad</label>
            <input id="cantidadStock" type="number" min="0.01" step="0.01" required inputmode="decimal">
            <label for="observacionStock">Detalle (opcional)</label>
            <textarea id="observacionStock" maxlength="180" rows="3" placeholder="Ej.: producto vencido o consumo del turno"></textarea>
            <p id="disponibleStock" class="disponible"></p>
            <p id="mensajeAjuste" role="status" aria-live="polite"></p>
            <div class="acciones">
                <a href="insumos.php">Volver a insumos</a>
                <button id="registrarAjuste" type="submit">Registrar salida</button>
            </div>
        </form>
    </main>
</div>
<script>
const tokenCsrf = <?php echo json_encode(obtener_token_csrf()); ?>;
const tipoElemento = document.getElementById("tipoElemento");
const selectorElemento = document.getElementById("elementoStock");
const campoCantidad = document.getElementById("cantidadStock");
const mensajeAjuste = document.getElementById("mensajeAjuste");
let elementos = [];

async function cargarElementos() {
    selectorElemento.innerHTML = '<option value="">Cargando...</option>';
    const respuesta = await fetch(`apis/api_ajustes_stock.php?tipo=${encodeURIComponent(tipoElemento.value)}`, {cache: "no-store"});
    const datos = await respuesta.json();
    if (!respuesta.ok) throw new Error(datos.error || "No se pudieron cargar los elementos.");
    elementos = datos.elementos || [];
    selectorElemento.innerHTML = elementos.map(elemento =>
        `<option value="${Number(elemento.id)}" data-disponible="${Number(elemento.cantidad_actual ?? 0)}" data-unidad="${escapar(elemento.unidad || "")}" data-receta="${Number(elemento.componentes || 0)}">${escapar(elemento.nombre)}${tipoElemento.value === "insumo" ? ` · disponible: ${escapar(elemento.cantidad_actual)} ${escapar(elemento.unidad || "")}` : ""}</option>`
    ).join("");
    if (!elementos.length) selectorElemento.innerHTML = '<option value="">No hay elementos disponibles</option>';
    actualizarCantidad();
}

function actualizarCantidad() {
    const producto = tipoElemento.value === "producto";
    campoCantidad.step = producto ? "1" : "0.01";
    campoCantidad.min = producto ? "1" : "0.01";
    campoCantidad.value = "";
    document.getElementById("etiquetaElemento").textContent = producto ? "Producto" : "Insumo";
    const seleccionado = selectorElemento.selectedOptions[0];
    document.getElementById("disponibleStock").textContent = seleccionado
        ? producto
            ? (Number(seleccionado.dataset.receta) > 0
                ? `${seleccionado.dataset.receta} insumos asociados a la receta.`
                : "Este producto no tiene insumos configurados; la salida quedará registrada sin descontar stock.")
            : `Disponible: ${seleccionado.dataset.disponible} ${seleccionado.dataset.unidad}.`
        : "";
}

document.getElementById("formAjusteStock").addEventListener("submit", async evento => {
    evento.preventDefault();
    const boton = document.getElementById("registrarAjuste");
    boton.disabled = true;
    mensajeAjuste.textContent = "";
    mensajeAjuste.className = "";
    try {
        const respuesta = await fetch("apis/api_ajustes_stock.php", {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-Token": tokenCsrf},
            body: JSON.stringify({
                tipo_movimiento: document.getElementById("tipoMovimiento").value,
                tipo_elemento: tipoElemento.value,
                id_elemento: Number(selectorElemento.value),
                cantidad: Number(campoCantidad.value),
                observacion: document.getElementById("observacionStock").value.trim()
            })
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo registrar la salida.");
        document.getElementById("formAjusteStock").reset();
        mensajeAjuste.textContent = "Salida registrada y stock actualizado.";
        await cargarElementos();
    } catch (error) {
        mensajeAjuste.textContent = error.message;
        mensajeAjuste.className = "error";
    } finally {
        boton.disabled = false;
    }
});

tipoElemento.addEventListener("change", () => cargarElementos().catch(mostrarError));
selectorElemento.addEventListener("change", actualizarCantidad);
function mostrarError(error) {
    mensajeAjuste.textContent = error.message;
    mensajeAjuste.className = "error";
}
function escapar(valor) {
    return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
cargarElementos().catch(mostrarError);
</script>
</body>
</html>
