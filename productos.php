<?php
include_once("includes/funciones.php");
verificar_sesion();
verificar_permiso("productos");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(7) { background-color: #fff; }
        .navigation ul li:nth-child(7) a { color: #001f47; }
        .navigation ul li:nth-child(7) a .icon img { content: url('assets/img/sidebar/theme-shopping.svg'); }
        .modulo-content { padding: 20px 30px; }
        .modulo-content h2 { color: #001f47; margin-bottom: 20px; }
        .modulo-actions { display: flex; justify-content: flex-end; margin-bottom: 20px; }
        .modulo-actions a { background: #001f47; border-radius: 50%; color: #fff; font-size: 1.5rem; line-height: 35px; text-align: center; text-decoration: none; width: 35px; height: 35px; }
        .modulo-table { border-collapse: collapse; width: 100%; }
        .modulo-table th, .modulo-table td { border-bottom: 1px solid #ddd; padding: 12px; text-align: left; }
        .modulo-table th { color: #001f47; }
        .accion { background: none; border: 0; color: #001f47; cursor: pointer; font-weight: bold; padding: 0; }
        .paginacion { align-items: center; display: flex; gap: 15px; justify-content: center; margin-top: 20px; }
        .paginacion button { background: #001f47; border: 0; border-radius: 4px; color: #fff; cursor: pointer; padding: 8px 14px; }
        .paginacion button:disabled { cursor: not-allowed; opacity: .5; }
    </style>
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
                    <option value="id_producto">ID</option>
                    <option value="nombre">Nombre</option>
                    <option value="precio">Precio</option>
                    <option value="categoria">Categoría</option>
                </select></label></div>
            </div>
            <?php include_once 'includes/profile.php'; ?>
        </div>
        <main class="modulo-content">
            <h2>Listado de Productos</h2>
            <div class="modulo-actions"><a href="altas/alta_producto.php" title="Cargar producto">+</a></div>
            <table class="modulo-table"><thead><tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Categoría</th><th>Acciones</th></tr></thead>
                <tbody id="tablaProductos"><tr><td colspan="5" style="text-align:center;">No se encontraron productos.</td></tr></tbody>
            </table>
            <div class="paginacion"><button type="button" id="btnAnterior">Anterior</button><span id="infoPagina">Página 1</span><button type="button" id="btnSiguiente">Siguiente</button></div>
        </main>
    </div>
    <script>
        let paginaActual = 1;
        const limitePorPagina = 15;
        let timeoutBusqueda = null;
        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("inputFiltro").addEventListener("input", buscarProductos);
            document.getElementById("atributoFiltro").addEventListener("change", buscarProductos);
            document.getElementById("btnAnterior").addEventListener("click", () => cambiarPagina(-1));
            document.getElementById("btnSiguiente").addEventListener("click", () => cambiarPagina(1));
            cargarProductos();
        });
        function cargarProductos() {
            const filtro = document.getElementById("inputFiltro").value.trim();
            const campo = document.getElementById("atributoFiltro").value;
            const tbody = document.getElementById("tablaProductos");
            fetch(`apis/api_productos.php?pagina=${paginaActual}&limite=${limitePorPagina}&campo=${encodeURIComponent(campo)}&filtro=${encodeURIComponent(filtro)}`)
                .then(response => { if (!response.ok) throw new Error("No se pudieron cargar los productos."); return response.json(); })
                .then(productos => {
                    tbody.innerHTML = "";
                    if (productos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No se encontraron productos.</td></tr>`;
                    } else {
                        productos.forEach(producto => {
                            const fila = document.createElement("tr");
                            fila.innerHTML = `<td>${escapeHTML(producto.id_producto)}</td><td>${escapeHTML(producto.nombre)}</td><td>$ ${escapeHTML(producto.precio)}</td><td>${escapeHTML(producto.categoria)}</td><td><a href="modificar_producto.php?id=${encodeURIComponent(producto.id_producto)}">Editar</a> <button type="button" class="accion" onclick="eliminarProducto(${producto.id_producto})">Desactivar</button></td>`;
                            tbody.appendChild(fila);
                        });
                    }
                    document.getElementById("btnAnterior").disabled = paginaActual === 1;
                    document.getElementById("btnSiguiente").disabled = productos.length < limitePorPagina;
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                }).catch(error => { console.error(error); tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No se pudieron cargar los productos.</td></tr>`; });
        }
        function cambiarPagina(delta) { paginaActual = Math.max(1, paginaActual + delta); cargarProductos(); }
        function buscarProductos() { clearTimeout(timeoutBusqueda); timeoutBusqueda = setTimeout(() => { paginaActual = 1; cargarProductos(); }, 300); }
        function eliminarProducto(id) { if (!confirm("¿Está seguro de que desea desactivar este producto?")) return; fetch(`apis/eliminar.php?tipo=producto&id=${encodeURIComponent(id)}`).then(response => { if (!response.ok) throw new Error(); return response.json(); }).then(cargarProductos).catch(() => alert("No se pudo desactivar el producto.")); }
        function escapeHTML(valor) { if (valor === null || valor === undefined) return ""; return String(valor).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;").replace(/'/g, "&#039;"); }
    </script>
</body>
</html>
