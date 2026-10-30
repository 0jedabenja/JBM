<?php
include_once "includes/funciones.php";
verificar_permiso("auditoria");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/auditoria.css">
</head>
<body>
    <?php include_once "includes/sidebar.php"; ?>
    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once "includes/profile.php"; ?>
        </div>
        <main class="auditoria-content">
            <h1>Auditoría del sistema</h1>
            <p class="auditoria-description">Registro de operaciones importantes. Se muestran 100 eventos por página.</p>
            <form id="filtrosAuditoria" class="auditoria-filtros">
                <label>Usuario
                    <input type="search" id="filtroUsuario" placeholder="Nombre o usuario" maxlength="100">
                </label>
                <label>Acción
                    <select id="filtroAccion"><option value="">Todas</option></select>
                </label>
                <label>Entidad
                    <select id="filtroEntidad"><option value="">Todas</option></select>
                </label>
                <label>Desde
                    <input type="datetime-local" id="filtroDesde">
                </label>
                <label>Hasta
                    <input type="datetime-local" id="filtroHasta">
                </label>
                <button type="submit" class="button">Filtrar</button>
                <button type="button" id="limpiarFiltros" class="button button--secondary">Limpiar</button>
            </form>
            <table class="auditoria-table">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Entidad</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="filasAuditoria">
                    <tr><td colspan="6">Cargando registros...</td></tr>
                </tbody>
            </table>
            <p id="mensajeAuditoria" class="auditoria-status" role="status" aria-live="polite"></p>
            <nav class="pagination" aria-label="Paginación de auditoría">
                <button id="auditoriaAnterior" class="button button--secondary" type="button" disabled>Anterior</button>
                <span id="paginaAuditoria">Página 1</span>
                <button id="auditoriaSiguiente" class="button button--secondary" type="button" disabled>Siguiente</button>
            </nav>
        </main>
    </div>
    <script>
    let paginaAuditoriaActual = 1;
    const tablaAuditoria = document.getElementById("filasAuditoria");
    const mensajeAuditoria = document.getElementById("mensajeAuditoria");
    const botonAuditoriaAnterior = document.getElementById("auditoriaAnterior");
    const botonAuditoriaSiguiente = document.getElementById("auditoriaSiguiente");

    botonAuditoriaAnterior.addEventListener("click", () => {
        if (paginaAuditoriaActual > 1) {
            paginaAuditoriaActual--;
            cargarAuditoria();
        }
    });
    botonAuditoriaSiguiente.addEventListener("click", () => {
        if (botonAuditoriaSiguiente.dataset.hayMas === "true") {
            paginaAuditoriaActual++;
            cargarAuditoria();
        }
    });

    const formularioFiltros = document.getElementById("filtrosAuditoria");
    const filtroAccion = document.getElementById("filtroAccion");
    const filtroEntidad = document.getElementById("filtroEntidad");

    formularioFiltros.addEventListener("submit", (evento) => {
        evento.preventDefault();
        paginaAuditoriaActual = 1;
        cargarAuditoria();
    });
    document.getElementById("limpiarFiltros").addEventListener("click", () => {
        formularioFiltros.reset();
        paginaAuditoriaActual = 1;
        cargarAuditoria();
    });

    function llenarOpciones(select, valores) {
        const seleccionado = select.value;
        select.innerHTML = '<option value="">Todas</option>' + valores.map((valor) =>
            `<option value="${escaparAuditoria(valor)}">${escaparAuditoria(valor.replace(/_/g, " "))}</option>`).join("");
        select.value = seleccionado;
    }

    async function cargarAuditoria() {
        mensajeAuditoria.textContent = "";
        const parametros = new URLSearchParams({ pagina: paginaAuditoriaActual });
        [["usuario", "filtroUsuario"], ["accion", "filtroAccion"], ["entidad", "filtroEntidad"], ["desde", "filtroDesde"], ["hasta", "filtroHasta"]]
            .forEach(([nombre, id]) => { const valor = document.getElementById(id).value.trim(); if (valor) parametros.set(nombre, valor); });
        try {
            const respuesta = await fetch(`apis/api_auditoria.php?${parametros}`, { cache: "no-store" });
            const datos = await respuesta.json();
            if (!respuesta.ok) throw new Error(datos.error || "No se pudo cargar la auditoría.");
            llenarOpciones(filtroAccion, datos.opciones.acciones);
            llenarOpciones(filtroEntidad, datos.opciones.entidades);
            tablaAuditoria.innerHTML = datos.registros.length
                ? datos.registros.map((fila) => `<tr>
                    <td>${escaparAuditoria(fila.fecha_hora)}</td><td>${escaparAuditoria(fila.responsable)} (${escaparAuditoria(fila.usuario)})</td>
                    <td>${escaparAuditoria(fila.accion)}</td><td>${escaparAuditoria(fila.entidad)} #${escaparAuditoria(fila.id_entidad)}</td>
                    <td>${escaparAuditoria(JSON.stringify(fila.detalle || {}))}</td><td>${escaparAuditoria(fila.ip)}</td></tr>`).join("")
                : '<tr><td colspan="6">No hay eventos que coincidan con los filtros.</td></tr>';
            mensajeAuditoria.textContent = `Total: ${datos.total} eventos`;
            document.getElementById("paginaAuditoria").textContent = `Página ${datos.pagina}`;
            botonAuditoriaAnterior.disabled = !datos.hay_anterior;
            botonAuditoriaSiguiente.disabled = !datos.hay_siguiente;
            botonAuditoriaSiguiente.dataset.hayMas = datos.hay_siguiente ? "true" : "false";
        } catch (error) {
            mensajeAuditoria.textContent = error instanceof Error ? error.message : "No se pudo cargar la auditoría.";
            tablaAuditoria.innerHTML = '<tr><td colspan="6">No se pudieron cargar los registros.</td></tr>';
            botonAuditoriaAnterior.disabled = paginaAuditoriaActual <= 1;
            botonAuditoriaSiguiente.disabled = true;
            botonAuditoriaSiguiente.dataset.hayMas = "false";
        }
    }

    function escaparAuditoria(valor) {
        return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    cargarAuditoria();
    </script>
</body>
</html>
