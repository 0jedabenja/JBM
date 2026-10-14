<?php
include_once __DIR__ . "/includes/conexionBD.php";
include_once __DIR__ . "/includes/funciones.php";
verificar_sesion();

$tipo = obtener_parametro_get("tipo");
$id = filter_var(obtener_parametro_get("id"), FILTER_VALIDATE_INT);
if (!in_array($tipo, array("insumo", "producto"), true) || $id === false || $id <= 0) {
    http_response_code(400);
    exit("El producto o insumo solicitado no es válido.");
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
    http_response_code(404);
    exit("No se encontró el producto o insumo.");
}
$nombre = $fila["nombre"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de <?php echo htmlspecialchars($nombre, ENT_QUOTES, "UTF-8"); ?></title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(<?php echo $tipo === "insumo" ? "6" : "7"; ?>) { background-color: #fff; }
        .navigation ul li:nth-child(<?php echo $tipo === "insumo" ? "6" : "7"; ?>) a { color: #001f47; }
        .navigation ul li:nth-child(<?php echo $tipo === "insumo" ? "6" : "7"; ?>) a .icon img {
            content: url('assets/img/sidebar/theme-<?php echo $tipo === "insumo" ? "box" : "shopping"; ?>.svg');
        }
        .modulo-content { padding: 20px 30px; }
        .modulo-content h2 { color: #001f47; margin-bottom: 8px; }
        .descripcion { color: #555; margin-bottom: 20px; }
        .filtros-movimientos {
            align-items: end;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0;
        }
        .filtros-movimientos label { display: grid; gap: 5px; }
        .filtros-movimientos input,
        .filtros-movimientos select {
            border: 1px solid #999;
            border-radius: 4px;
            font: inherit;
            min-height: 38px;
            padding: 7px 10px;
        }
        .boton, .filtros-movimientos button {
            background: #001f47;
            border: 0;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            display: inline-block;
            font: inherit;
            padding: 9px 14px;
            text-decoration: none;
        }
        .tabla-contenedor { overflow-x: auto; }
        .modulo-table { border-collapse: collapse; min-width: 850px; width: 100%; }
        .modulo-table th, .modulo-table td {
            border-bottom: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .modulo-table th { color: #001f47; white-space: nowrap; }
        .paginacion {
            align-items: center;
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        .paginacion button:disabled { cursor: not-allowed; opacity: .5; }
        #mensajeMovimientos { color: #a01818; }
    </style>
</head>
<body>
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
                    <tbody id="filasMovimientos"><tr><td colspan="7" style="text-align:center;">Cargando movimientos...</td></tr></tbody>
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
            throw new Error("El servidor devolvió una respuesta inválida. Verificá que la migración de auditoría y movimientos esté aplicada.");
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
                <td>${escapar(movimiento.referencia)}</td>
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
    return ({ saldo_inicial: "Saldo inicial", inventario: "Ajuste de inventario", compra: "Compra", venta: "Venta / pedido" })[tipo] || tipo;
}
function formatearCantidad(valor, conSigno) {
    const formato = Number(valor).toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return conSigno && valor > 0 ? `+${formato}` : formato;
}
function formatearImporte(valor) {
    return Number(valor).toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function escapar(valor) {
    return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
cargarMovimientos();
</script>
</body>
</html>
