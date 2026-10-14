<?php
include_once("includes/funciones.php");
verificar_sesion();
verificar_permiso("empleados");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empleados</title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(8) { background-color: #fff; }
        .navigation ul li:nth-child(8) a { color: #001f47; }
        .navigation ul li:nth-child(8) a .icon img { content: url('assets/img/sidebar/theme-team.svg'); }
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
                    <option value="id_empleado">ID</option>
                    <option value="nombre">Nombre</option>
                    <option value="apellido">Apellido</option>
                    <option value="usuario">Usuario</option>
                    <option value="rol">Rol</option>
                </select></label></div>
            </div>
            <?php include_once 'includes/profile.php'; ?>
        </div>
        <main class="modulo-content">
            <h2>Listado de Empleados</h2>
            <div class="modulo-actions"><a href="altas/alta_empleado.php" title="Cargar empleado">+</a></div>
            <table class="modulo-table"><thead><tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>Usuario</th><th>Rol</th><th>Acciones</th></tr></thead>
                <tbody id="tablaEmpleados"><tr><td colspan="6" style="text-align:center;">No se encontraron empleados.</td></tr></tbody>
            </table>
            <div class="paginacion"><button type="button" id="btnAnterior">Anterior</button><span id="infoPagina">Página 1</span><button type="button" id="btnSiguiente">Siguiente</button></div>
        </main>
    </div>
    <script>
        let paginaActual = 1;
        const limitePorPagina = 15;
        let timeoutBusqueda = null;
        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("inputFiltro").addEventListener("input", buscarEmpleados);
            document.getElementById("atributoFiltro").addEventListener("change", buscarEmpleados);
            document.getElementById("btnAnterior").addEventListener("click", () => cambiarPagina(-1));
            document.getElementById("btnSiguiente").addEventListener("click", () => cambiarPagina(1));
            cargarEmpleados();
        });
        function cargarEmpleados() {
            const filtro = document.getElementById("inputFiltro").value.trim();
            const campo = document.getElementById("atributoFiltro").value;
            const tbody = document.getElementById("tablaEmpleados");
            fetch(`apis/api_empleados.php?pagina=${paginaActual}&limite=${limitePorPagina}&campo=${encodeURIComponent(campo)}&filtro=${encodeURIComponent(filtro)}`)
                .then(response => { if (!response.ok) throw new Error("No se pudieron cargar los empleados."); return response.json(); })
                .then(empleados => {
                    tbody.innerHTML = "";
                    if (empleados.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No se encontraron empleados.</td></tr>`;
                    } else {
                        empleados.forEach(empleado => {
                            const fila = document.createElement("tr");
                            fila.innerHTML = `<td>${escapeHTML(empleado.id_empleado)}</td><td>${escapeHTML(empleado.nombre)}</td><td>${escapeHTML(empleado.apellido)}</td><td>${escapeHTML(empleado.usuario)}</td><td>${escapeHTML(empleado.rol)}</td><td><a href="modificar_empleado.php?id=${encodeURIComponent(empleado.id_empleado)}">Editar</a> <button type="button" class="accion" onclick="eliminarEmpleado(${empleado.id_empleado})">Desactivar</button></td>`;
                            tbody.appendChild(fila);
                        });
                    }
                    document.getElementById("btnAnterior").disabled = paginaActual === 1;
                    document.getElementById("btnSiguiente").disabled = empleados.length < limitePorPagina;
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                }).catch(error => { console.error(error); tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No se pudieron cargar los empleados.</td></tr>`; });
        }
        function cambiarPagina(delta) { paginaActual = Math.max(1, paginaActual + delta); cargarEmpleados(); }
        function buscarEmpleados() { clearTimeout(timeoutBusqueda); timeoutBusqueda = setTimeout(() => { paginaActual = 1; cargarEmpleados(); }, 300); }
        function eliminarEmpleado(id) { if (!confirm("¿Está seguro de que desea desactivar este empleado?")) return; fetch(`apis/eliminar.php?tipo=empleado&id=${encodeURIComponent(id)}`).then(response => { if (!response.ok) throw new Error(); return response.json(); }).then(cargarEmpleados).catch(() => alert("No se pudo desactivar el empleado.")); }
        function escapeHTML(valor) { if (valor === null || valor === undefined) return ""; return String(valor).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;").replace(/'/g, "&#039;"); }
    </script>
</body>
</html>
