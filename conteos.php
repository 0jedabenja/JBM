<?php
include_once("includes/funciones.php");
verificar_permiso("conteos");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteo de insumos</title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .conteos-content {
            padding: 24px 30px 40px;
        }

        .conteos-content h1,
        .conteos-content h2 {
            color: #001f47;
        }

        .conteo-toolbar {
            align-items: end;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0;
        }

        .conteo-field {
            display: grid;
            gap: 6px;
            min-width: 220px;
        }

        .conteo-field label {
            font-weight: 700;
        }

        .conteo-field select,
        .conteo-table input {
            border: 1px solid #b9c5c8;
            border-radius: 5px;
            font: inherit;
            padding: 9px;
        }

        .conteo-button {
            background: #001f47;
            border: 0;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            padding: 10px 15px;
        }

        .conteo-button.secondary {
            background: #fff;
            border: 1px solid #c9d3d5;
            color: #001f47;
        }

        .conteo-button.danger {
            background: #9c3030;
        }

        .conteo-message {
            min-height: 24px;
        }

        .conteo-message.error {
            color: #a32929;
        }

        .conteo-message.success {
            color: #246b3a;
        }

        .conteo-section {
            border-top: 1px solid #d8e0e1;
            margin-top: 28px;
            padding-top: 18px;
        }

        .conteo-list {
            display: grid;
            gap: 8px;
            max-width: 900px;
        }

        .conteo-row {
            align-items: center;
            border-bottom: 1px solid #e0e6e7;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            padding: 10px 0;
        }

        .conteo-row p {
            color: #5d6d72;
            margin: 4px 0 0;
        }

        .conteo-table {
            border-collapse: collapse;
            margin: 12px 0;
            width: 100%;
        }

        .conteo-table th,
        .conteo-table td {
            border-bottom: 1px solid #dfe5e6;
            padding: 10px;
            text-align: left;
        }

        .conteo-table th {
            color: #001f47;
        }

        .conteo-table input {
            max-width: 160px;
            width: 100%;
        }

        .conteo-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .conteo-empty {
            color: #617176;
            padding: 12px 0;
        }

        @media (max-width: 700px) {
            .conteos-content {
                padding: 18px 16px 32px;
            }

            .conteo-toolbar {
                align-items: stretch;
            }

            .conteo-field,
            .conteo-toolbar .conteo-button {
                width: 100%;
            }

            .conteo-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <?php include_once "includes/sidebar.php"; ?>
    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once "includes/profile.php"; ?>
        </div>
        <main class="conteos-content">
            <h1>Conteo de insumos</h1>
            <p id="mensajeConteo" class="conteo-message" role="status" aria-live="polite"></p>
            <section aria-labelledby="tituloNuevoConteo">
                <h2 id="tituloNuevoConteo">Nuevo conteo</h2>
                <div class="conteo-toolbar">
                    <div class="conteo-field">
                        <label for="categoriaConteo">Insumos a contar</label>
                        <select id="categoriaConteo">
                            <option value="todas">Todas las categorías</option>
                            <option value="sin_categoria">Sin categoría</option>
                        </select>
                    </div>
                    <button id="btnIniciarConteo" class="conteo-button" type="button">Iniciar conteo</button>
                </div>
            </section>
            <section id="panelConteo" class="conteo-section" hidden>
                <h2 id="tituloConteoActivo">Conteo en curso</h2>
                <form id="formConteo">
                    <table class="conteo-table">
                        <thead><tr><th>Insumo</th><th>En sistema</th><th>Contado</th></tr></thead>
                        <tbody id="itemsConteo"></tbody>
                    </table>
                    <div class="conteo-actions">
                        <button class="conteo-button" type="submit">Aplicar conteo y actualizar stock</button>
                        <button id="btnCancelarConteo" class="conteo-button danger" type="button">Cancelar conteo</button>
                    </div>
                </form>
            </section>
            <section class="conteo-section" aria-labelledby="tituloPendientes">
                <h2 id="tituloPendientes">Conteos en curso</h2>
                <div id="listaPendientes" class="conteo-list"></div>
            </section>
            <section class="conteo-section" aria-labelledby="tituloHistorial">
                <h2 id="tituloHistorial">Historial reciente</h2>
                <div id="listaHistorial"></div>
            </section>
        </main>
    </div>
    <script>
    let conteoActivo = null;
    const categoriaConteo = document.getElementById("categoriaConteo");
    const panelConteo = document.getElementById("panelConteo");
    let guardadoBorrador = Promise.resolve();

    document.getElementById("btnIniciarConteo").addEventListener("click", iniciarConteo);
    document.getElementById("btnCancelarConteo").addEventListener("click", cancelarConteo);
    document.getElementById("formConteo").addEventListener("submit", finalizarConteo);
    document.getElementById("itemsConteo").addEventListener("change", (evento) => {
        guardadoBorrador = guardarBorrador(evento);
    });
    document.getElementById("listaPendientes").addEventListener("click", gestionarPendientes);

    async function solicitar(url, opciones = {}) {
        const respuesta = await fetch(url, opciones);
        let datos;
        try {
            datos = await respuesta.json();
        } catch (error) {
            throw new Error("No se pudo leer la respuesta del servidor. Verificá que el esquema de conteos esté creado.");
        }
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo completar la operación.");
        return datos;
    }

    async function cargarResumen() {
        try {
            const datos = await solicitar("apis/api_conteos.php", { cache: "no-store" });
            const seleccion = categoriaConteo.value;
            categoriaConteo.innerHTML = '<option value="todas">Todas las categorías</option><option value="sin_categoria">Sin categoría</option>';
            datos.categorias.forEach((categoria) => {
                const opcion = document.createElement("option");
                opcion.value = categoria.id_categoria;
                opcion.textContent = categoria.titulo;
                categoriaConteo.appendChild(opcion);
            });
            if (Array.from(categoriaConteo.options).some((opcion) => opcion.value === seleccion)) categoriaConteo.value = seleccion;
            renderPendientes(datos.pendientes);
            renderHistorial(datos.historial);
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    async function iniciarConteo() {
        if (!window.confirm("¿Querés iniciar el conteo? Se guardará el stock actual como referencia.")) return;
        try {
            const datos = await solicitar("apis/api_conteos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ accion: "iniciar", categoria: categoriaConteo.value })
            });
            mostrarConteo(datos);
            await cargarResumen();
            mostrarMensaje("Conteo iniciado. Ingresá lo que hay físicamente.", false);
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    function mostrarConteo(datos) {
        conteoActivo = Number(datos.conteo.id_conteo);
        document.getElementById("tituloConteoActivo").textContent = `Conteo #${conteoActivo}`;
        document.getElementById("itemsConteo").innerHTML = datos.items.map((item) => `
            <tr>
                <td>${escapar(item.nombre)} <small>${escapar(item.unidad)}</small></td>
                <td>${escapar(item.cantidad_sistema)}</td>
                <td><input type="number" min="0" max="99999999.99" step="0.01" required data-insumo="${Number(item.id_insumo)}" value="${item.cantidad_contada === null ? "" : escapar(item.cantidad_contada)}" aria-label="Cantidad contada de ${escapar(item.nombre)}"></td>
            </tr>
        `).join("");
        panelConteo.hidden = false;
        panelConteo.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    async function guardarBorrador(evento) {
        const input = evento.target.closest("input[data-insumo]");
        if (!input || !conteoActivo) return;

        try {
            await solicitar("apis/api_conteos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    accion: "guardar_borrador",
                    id_conteo: conteoActivo,
                    cantidades: [{
                        id_insumo: Number(input.dataset.insumo),
                        cantidad: input.value === "" ? null : Number(input.value)
                    }]
                })
            });
            mostrarMensaje("Borrador guardado. El stock no cambia hasta finalizar.", false);
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    async function gestionarPendientes(evento) {
        const continuar = evento.target.closest("[data-continuar]");
        const cancelar = evento.target.closest("[data-cancelar]");
        try {
            if (continuar) {
                const datos = await solicitar(`apis/api_conteos.php?id_conteo=${encodeURIComponent(continuar.dataset.continuar)}`, { cache: "no-store" });
                mostrarConteo(datos);
            }
            if (cancelar && window.confirm("¿Cancelar este conteo? No se actualizarán las existencias.")) {
                await solicitar("apis/api_conteos.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ accion: "cancelar", id_conteo: Number(cancelar.dataset.cancelar) })
                });
                await cargarResumen();
                mostrarMensaje("Conteo cancelado; las existencias no cambiaron.", false);
            }
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    async function finalizarConteo(evento) {
        evento.preventDefault();
        await guardadoBorrador;
        const inputs = Array.from(document.querySelectorAll("#itemsConteo input[data-insumo]"));
        if (inputs.some((input) => input.value.trim() === "")) {
            mostrarMensaje("Completá la cantidad de cada insumo.", true);
            return;
        }
        if (!window.confirm("¿Finalizar el conteo y reemplazar las existencias del sistema?")) return;

        const cantidades = inputs.map((input) => ({ id_insumo: Number(input.dataset.insumo), cantidad: Number(input.value) }));
        try {
            await solicitar("apis/api_conteos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ accion: "finalizar", id_conteo: conteoActivo, cantidades })
            });
            panelConteo.hidden = true;
            conteoActivo = null;
            await cargarResumen();
            mostrarMensaje("Conteo guardado y existencias actualizadas.", false);
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    async function cancelarConteo() {
        if (!conteoActivo || !window.confirm("¿Cancelar este conteo? No se actualizarán las existencias.")) return;
        try {
            await solicitar("apis/api_conteos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ accion: "cancelar", id_conteo: conteoActivo })
            });
            panelConteo.hidden = true;
            conteoActivo = null;
            await cargarResumen();
            mostrarMensaje("Conteo cancelado; las existencias no cambiaron.", false);
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    function renderPendientes(conteos) {
        const lista = document.getElementById("listaPendientes");
        if (!conteos.length) {
            lista.innerHTML = '<p class="conteo-empty">No hay conteos en curso.</p>';
            return;
        }
        lista.innerHTML = conteos.map((conteo) => `
            <article class="conteo-row">
                <div><strong>Conteo #${Number(conteo.id_conteo)}</strong><p>${escapar(etiquetaCategoria(conteo))} · ${Number(conteo.cantidad)} insumos · ${escapar(conteo.nombre)} ${escapar(conteo.apellido)}</p></div>
                <div class="conteo-actions"><button class="conteo-button secondary" type="button" data-continuar="${Number(conteo.id_conteo)}">Continuar</button><button class="conteo-button danger" type="button" data-cancelar="${Number(conteo.id_conteo)}">Cancelar</button></div>
            </article>
        `).join("");
    }

    function renderHistorial(conteos) {
        const lista = document.getElementById("listaHistorial");
        if (!conteos.length) {
            lista.innerHTML = '<p class="conteo-empty">Todavía no hay conteos registrados.</p>';
            return;
        }
        const filas = conteos.map((conteo) => `
            <tr>
                <td>${escapar(conteo.fecha_fin || conteo.fecha_inicio)}</td>
                <td>${escapar(etiquetaCategoria(conteo))}</td>
                <td>${escapar(conteo.estado)}</td>
                <td>${Number(conteo.cantidad)}</td>
                <td>${Number(conteo.items_con_diferencia || 0)}</td>
                <td>${escapar(conteo.nombre)} ${escapar(conteo.apellido)}</td>
            </tr>
        `).join("");
        lista.innerHTML = `
            <table class="conteo-table">
                <thead>
                    <tr><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Insumos</th><th>Con diferencia</th><th>Administrador</th></tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        `;
    }

    function etiquetaCategoria(conteo) {
        if (conteo.tipo_categoria === "sin_categoria") return "Sin categoría";
        if (conteo.tipo_categoria === "categoria") return conteo.categoria || "Categoría eliminada";
        return "Todas las categorías";
    }

    function mostrarMensaje(texto, error) {
        const mensaje = document.getElementById("mensajeConteo");
        mensaje.textContent = texto;
        mensaje.className = `conteo-message ${error ? "error" : "success"}`;
    }

    function escapar(valor) {
        return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    cargarResumen();
    </script>
</body>
</html>