<?php
include_once("includes/funciones.php");
verificar_sesion();
verificar_permiso("insumos");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Insumos</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/insumos.css">
    <link rel="stylesheet" href="assets/css/modulos-listado.css">
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <div class="search-group">
                <div class="search"><label><input type="text" id="inputFiltro" placeholder="Buscar aquí"><img src="assets/img/sidebar/dark-search.svg" alt=""></label></div>
                <div class="filter"><label><img src="assets/img/sidebar/dark-filter.svg" alt=""><select id="atributoFiltro">
                    <option value="todos">Todos los atributos</option>
                    <option value="id_insumo">ID</option>
                    <option value="nombre">Nombre</option>
                    <option value="unidad">Unidad de medida</option>
                    <option value="cantidad_actual">Cantidad actual</option>
                    <option value="stock_minimo">Stock mínimo</option>
                    <option value="costo">Costo</option>
                </select></label></div>
            </div>
            <?php include_once 'includes/profile.php'; ?>
        </div>
        <main class="modulo-content">
            <header class="page-heading page-heading--actions">
                <div>
                    <h1>Listado de Insumos</h1>
                    <p>Controlá el stock, el costo y las unidades de cada insumo.</p>
                </div>
                <div class="modulo-actions"><a class="accion-stock" href="ajustes_stock.php" title="Registrar pérdida, avería o consumo del personal">Registrar salida de stock</a><a href="altas/alta_insumo.php" title="Cargar insumo">+</a></div>
            </header>            <table class="modulo-table"><thead><tr><th>ID</th><th>Nombre</th><th>Unidad de Medida</th><th>Cant. Actual</th><th>Stock Mínimo</th><th>Costo</th><th>Acciones</th></tr></thead>
                <tbody id="tablaInsumos"><tr><td colspan="7" class="texto-centrado">No se encontraron insumos.</td></tr></tbody>
            </table>
            <nav class="pagination" aria-label="Paginación de insumos"><button class="button button--secondary" type="button" id="btnAnterior">Anterior</button><span id="infoPagina">Página 1</span><button class="button button--secondary" type="button" id="btnSiguiente">Siguiente</button></nav>
        </main>
    </div>
    <script>
        let paginaActual = 1;
        const limitePorPagina = 15;
        let timeoutBusqueda = null;
        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("inputFiltro").addEventListener("input", buscarInsumos);
            document.getElementById("atributoFiltro").addEventListener("change", buscarInsumos);
            document.getElementById("btnAnterior").addEventListener("click", () => cambiarPagina(-1));
            document.getElementById("btnSiguiente").addEventListener("click", () => cambiarPagina(1));
            cargarInsumos();
        });
        function cargarInsumos() {
            const filtro = document.getElementById("inputFiltro").value.trim();
            const campo = document.getElementById("atributoFiltro").value;
            const tbody = document.getElementById("tablaInsumos");
            fetch(`apis/api_insumos.php?pagina=${paginaActual}&limite=${limitePorPagina}&campo=${encodeURIComponent(campo)}&filtro=${encodeURIComponent(filtro)}`)
                .then(response => { if (!response.ok) throw new Error("No se pudieron cargar los insumos."); return response.json(); })
                .then(insumos => {
                    tbody.innerHTML = "";
                    if (insumos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="7" class="texto-centrado">No se encontraron insumos.</td></tr>`;
                    } else {
                        insumos.forEach(insumo => {
                            const fila = document.createElement("tr");
                            const cantidadActual = Number(insumo.cantidad_actual);
                            const tieneStockNegativo = Number.isFinite(cantidadActual) && cantidadActual < 0;
                            const distintivoStockNegativo = tieneStockNegativo
                                ? '<strong>Stock negativo</strong>'
                                : "";
                            if (tieneStockNegativo) fila.classList.add("producto-sin-stock");
                            fila.innerHTML = `<td>${escapeHTML(insumo.id_insumo)}</td><td>${escapeHTML(insumo.nombre)}</td><td>${escapeHTML(insumo.unidad)}</td><td>${escapeHTML(insumo.cantidad_actual)} ${distintivoStockNegativo}</td><td>${escapeHTML(insumo.stock_minimo)}</td><td>$ ${escapeHTML(insumo.costo)}</td><td class="acciones-producto"><a class="accion accion-movimientos" href="movimientos.php?tipo=insumo&id=${encodeURIComponent(insumo.id_insumo)}">Movimientos</a><a class="accion accion-editar" href="modificar_insumo.php?id=${encodeURIComponent(insumo.id_insumo)}">Editar</a><button type="button" class="accion accion-desactivar" onclick="eliminarInsumo(${insumo.id_insumo})">Desactivar</button></td>`;
                            tbody.appendChild(fila);
                        });
                    }
                    document.getElementById("btnAnterior").disabled = paginaActual === 1;
                    document.getElementById("btnSiguiente").disabled = insumos.length < limitePorPagina;
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                }).catch(error => { console.error(error); tbody.innerHTML = `<tr><td colspan="7" class="texto-centrado">No se pudieron cargar los insumos.</td></tr>`; });
        }
        function cambiarPagina(delta) { paginaActual = Math.max(1, paginaActual + delta); cargarInsumos(); }
        function buscarInsumos() { clearTimeout(timeoutBusqueda); timeoutBusqueda = setTimeout(() => { paginaActual = 1; cargarInsumos(); }, 300); }
        function eliminarInsumo(id) { if (!confirm("¿Está seguro de que desea desactivar este insumo?")) return; fetch("apis/eliminar.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ tipo: "insumo", id: Number(id) }) }).then(response => { if (!response.ok) throw new Error(); return response.json(); }).then(cargarInsumos).catch(() => alert("No se pudo desactivar el insumo.")); }
        function escapeHTML(valor) { if (valor === null || valor === undefined) return ""; return String(valor).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;").replace(/'/g, "&#039;"); }
    </script>
</body>
</html>
