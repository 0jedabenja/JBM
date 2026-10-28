<?php
include_once __DIR__ . "/includes/conexionBD.php";
include_once __DIR__ . "/includes/funciones.php";
verificar_sesion();

$tipo = obtener_parametro_get("tipo");
$id = filter_var(obtener_parametro_get("id"), FILTER_VALIDATE_INT);
if (!in_array($tipo, array("insumo", "producto"), true) || $id === false || $id <= 0) {
    mostrar_pagina_error(400);
}
verificar_permiso($tipo === "insumo" ? "insumos" : "productos");

$tabla = $tipo === "insumo" ? "Insumo" : "Producto";
$columnaId = $tipo === "insumo" ? "id_insumo" : "id_producto";
$stmt = mysqli_prepare($conexion, "SELECT nombre FROM {$tabla} WHERE {$columnaId} = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$fila) {
    mostrar_pagina_error(404);
}
$nombre = $fila["nombre"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de <?php echo htmlspecialchars($nombre, ENT_QUOTES, "UTF-8"); ?></title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/movimientos.css">
</head>
<body class="<?php echo htmlspecialchars($tipo, ENT_QUOTES, "UTF-8"); ?>">
    <?php include_once 'includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once 'includes/profile.php'; ?>
        </div>
        <main class="modulo-content">
            <h2>Movimientos: <?php echo htmlspecialchars($nombre, ENT_QUOTES, "UTF-8"); ?></h2>
            <p class="descripcion"><?php echo $tipo === "insumo" ? "Los ajustes se muestran con signo: negativo resta y positivo suma." : "Se muestran las unidades vendidas en pedidos."; ?></p>
            <form id="filtrosMovimientos" class="filtros-movimientos">
                <label for="filtroTipo">Tipo de movimiento
                    <select id="filtroTipo">
                        <option value="todos">Todos</option>
                        <?php if ($tipo === "insumo"): ?>
                            <option value="saldo_inicial">Saldo inicial</option>
                            <option value="inventario">Ajuste de inventario</option>
                            <option value="compra">Compra</option>
                            <option value="perdida">Pérdida</option>
                            <option value="averia">Insumo dañado</option>
                            <option value="consumo_personal">Consumo del personal</option>
                        <?php else: ?>
                            <option value="venta">Venta / pedido</option>
                        <?php endif; ?>
                    </select>
                </label>
                <label for="fechaDesde">Desde<input type="date" id="fechaDesde"></label>
                <label for="fechaHasta">Hasta<input type="date" id="fechaHasta"></label>
                <button type="submit">Filtrar</button>
                <a class="boton" href="<?php echo $tipo === "insumo" ? "insumos.php" : "productos.php"; ?>">Volver</a>
            </form>
            <p id="mensajeMovimientos" role="status"></p>
            <div class="tabla-contenedor">
                <table class="modulo-table">
                    <thead><tr><th>Fecha y hora</th><th>Tipo</th><th><?php echo $tipo === "insumo" ? "Variación" : "Unidades vendidas"; ?></th><th>Valor unitario</th><th>Total</th><th>Referencia</th><th>Responsable</th></tr></thead>
                    <tbody id="filasMovimientos"><tr><td colspan="7" class="texto-centrado">Cargando movimientos...</td></tr></tbody>
                </table>
            </div>
            <div class="paginacion">
                <button class="boton" id="paginaAnterior" type="button">Anterior</button>
                <span id="paginaActual">Página 1</span>
                <button class="boton" id="paginaSiguiente" type="button">Siguiente</button>
            </div>
        </main>
    </div>
<script>
const tipoEntidad = <?php echo json_encode($tipo); ?>;
const idEntidad = <?php echo (int)$id; ?>;
let pagina = 1;
let cargando = false;

document.getElementById("filtrosMovimientos").addEventListener("submit", (evento) => {
    evento.preventDefault();
    pagina = 1;
    cargarMovimientos();
});
document.getElementById("paginaAnterior").addEventListener("click", () => {
    if (pagina > 1 && !cargando) { pagina--; cargarMovimientos(); }
});
document.getElementById("paginaSiguiente").addEventListener("click", () => {
    if (!cargando && document.getElementById("paginaSiguiente").dataset.hayMas === "true") { pagina++; cargarMovimientos(); }
});

async function cargarMovimientos() {
    if (cargando) return;
    cargando = true;
    const params = new URLSearchParams({
        tipo: tipoEntidad,
        id: String(idEntidad),
        movimiento: document.getElementById("filtroTipo").value,
        desde: document.getElementById("fechaDesde").value,
        hasta: document.getElementById("fechaHasta").value,
        pagina: String(pagina)
    });
    try {
        const respuesta = await fetch(`apis/api_movimientos.php?${params.toString()}`, { cache: "no-store" });
        const contenido = await respuesta.text();
        let datos;
        try {
            datos = JSON.parse(contenido);
        } catch {
            throw new Error("El servidor devolvió una respuesta inválida. Verificá la conexión y la estructura de la base de datos.");
        }
        if (!respuesta.ok) throw new Error(datos.error || "No se pudieron cargar los movimientos.");
        const cuerpo = document.getElementById("filasMovimientos");
        cuerpo.innerHTML = datos.movimientos.length ? datos.movimientos.map((movimiento) => `
            <tr>
                <td>${escapar(new Date(movimiento.fecha_hora.replace(" ", "T")).toLocaleString("es-AR"))}</td>
                <td>${escapar(etiquetaTipo(movimiento.tipo))}</td>
                <td>${formatearCantidad(Number(movimiento.cantidad), tipoEntidad === "insumo")}</td>
                <td>$ ${formatearImporte(movimiento.costo_unitario)}</td>
                <td>$ ${formatearImporte(movimiento.total)}</td>
                <td>${referenciaMovimiento(movimiento)}</td>
                <td>${escapar(movimiento.responsable)}</td>
            </tr>`).join("") : '<tr><td colspan="7">No hay movimientos para los filtros seleccionados.</td></tr>';
        document.getElementById("paginaActual").textContent = `Página ${pagina}`;
        document.getElementById("paginaAnterior").disabled = pagina <= 1;
        document.getElementById("paginaSiguiente").dataset.hayMas = datos.hay_mas ? "true" : "false";
        document.getElementById("paginaSiguiente").disabled = !datos.hay_mas;
        document.getElementById("mensajeMovimientos").textContent = "";
    } catch (error) {
        document.getElementById("mensajeMovimientos").textContent = error.message;
    } finally {
        cargando = false;
    }
}

function etiquetaTipo(tipo) {
    return ({ saldo_inicial: "Saldo inicial", inventario: "Ajuste de inventario", compra: "Compra", perdida: "Pérdida", averia: "Producto dañado", consumo_personal: "Consumo del personal", venta: "Venta / pedido" })[tipo] || tipo;
}
function formatearCantidad(valor, conSigno) {
    const formato = Number(valor).toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return conSigno && valor > 0 ? `+${formato}` : formato;
}
function formatearImporte(valor) {
    return Number(valor).toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function referenciaMovimiento(movimiento) {
    const referencia = escapar(movimiento.referencia);
    if (tipoEntidad === "insumo" && movimiento.tipo === "compra" && movimiento.referencia_id) {
        return `${referencia} <a class="boton boton-comprobante" href="apis/comprobante_compra.php?id=${encodeURIComponent(movimiento.referencia_id)}" target="_blank">Ver comprobante</a>`;
    }
    return referencia;
}
function escapar(valor) {
    return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
cargarMovimientos();
</script>
</body>
</html>
