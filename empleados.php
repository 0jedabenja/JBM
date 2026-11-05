<?php
include_once("includes/funciones.php");
verificar_sesion();
verificar_permiso("empleados");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empleados</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/empleados.css">
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
            <header class="page-heading page-heading--actions">
                <div>
                    <h1>Listado de Empleados</h1>
                    <p>Gestioná los usuarios del sistema y sus roles.</p>
                </div>
                <div class="modulo-actions"><a href="altas/alta_empleado.php" title="Cargar empleado">+</a></div>
            </header>            <table class="modulo-table"><thead><tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>Usuario</th><th>Rol</th><th>Acciones</th></tr></thead>
                <tbody id="tablaEmpleados"><tr><td colspan="6" class="texto-centrado">No se encontraron empleados.</td></tr></tbody>
            </table>
            <nav class="pagination" aria-label="Paginación de empleados"><button class="button button--secondary" type="button" id="btnAnterior">Anterior</button><span id="infoPagina">Página 1</span><button class="button button--secondary" type="button" id="btnSiguiente">Siguiente</button></nav>
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
                        tbody.innerHTML = `<tr><td colspan="6" class="texto-centrado">No se encontraron empleados.</td></tr>`;
                    } else {
                        empleados.forEach(empleado => {
                            const fila = document.createElement("tr");
                            const reset = <?php echo $_SESSION["rol"] === "administrador" ? "true" : "false"; ?>;
                            fila.innerHTML = `<td>${escapeHTML(empleado.id_empleado)}</td><td>${escapeHTML(empleado.nombre)}</td><td>${escapeHTML(empleado.apellido)}</td><td>${escapeHTML(empleado.usuario)}</td><td>${escapeHTML(empleado.rol)}</td><td class="acciones-producto"><a class="accion accion-editar" href="modificar_empleado.php?id=${encodeURIComponent(empleado.id_empleado)}">Editar</a>${reset ? `<button type="button" class="accion" onclick="restablecerEmpleado(${empleado.id_empleado})">Restablecer contraseña</button>` : ""}<button type="button" class="accion accion-desactivar" onclick="eliminarEmpleado(${empleado.id_empleado})">Desactivar</button></td>`;
                            tbody.appendChild(fila);
                        });
                    }
                    document.getElementById("btnAnterior").disabled = paginaActual === 1;
                    document.getElementById("btnSiguiente").disabled = empleados.length < limitePorPagina;
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                }).catch(error => { console.error(error); tbody.innerHTML = `<tr><td colspan="6" class="texto-centrado">No se pudieron cargar los empleados.</td></tr>`; });
        }
        function cambiarPagina(delta) { paginaActual = Math.max(1, paginaActual + delta); cargarEmpleados(); }
        function buscarEmpleados() { clearTimeout(timeoutBusqueda); timeoutBusqueda = setTimeout(() => { paginaActual = 1; cargarEmpleados(); }, 300); }
        async function eliminarEmpleado(id) { if (!await JBM.confirmar("¿Está seguro de que desea desactivar este empleado?", { aceptar: "Desactivar", peligro: true })) return; fetch("apis/eliminar.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ tipo: "empleado", id: Number(id) }) }).then(response => { if (!response.ok) throw new Error(); return response.json(); }).then(cargarEmpleados).catch(() => JBM.avisar("No se pudo desactivar el empleado.", "Error")); }
        async function restablecerEmpleado(id) {
            const v = await JBM.pedir({
                titulo: "Restablecer contraseña",
                aceptar: "Restablecer",
                campos: [
                    { name: "nueva", label: "Nueva contraseña (mínimo 8 caracteres)", type: "password" },
                    { name: "repetir", label: "Repetir nueva contraseña", type: "password" },
                    { name: "admin", label: "Contraseña del administrador para confirmar", type: "password" }
                ],
                validar: d => d.nueva.length < 8 ? "La contraseña debe tener al menos 8 caracteres." : d.nueva !== d.repetir ? "Las contraseñas no coinciden." : ""
            });
            if (!v) return;
            fetch("apis/api_reestablecer_usuario.php", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({id_empleado:id,nueva_password:v.nueva,admin_password:v.admin})})
                .then(async r => {const d=await r.json(); if(!r.ok) throw new Error(d.error||"No se pudo restablecer."); return d;})
                .then(()=>JBM.avisar("Contraseña restablecida.")).catch(e=>JBM.avisar(e.message, "Error"));
        }
        function escapeHTML(valor) { if (valor === null || valor === undefined) return ""; return String(valor).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;").replace(/'/g, "&#039;"); }
    </script>
</body>
</html>
