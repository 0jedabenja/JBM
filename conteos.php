<?php
include_once("includes/funciones.php");
verificar_permiso("conteos");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteo de insumos</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/conteos.css">
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
                    <div class="conteo-actions">
                        <button id="btnGenerarBase" class="conteo-button secondary" type="button">Generar base</button>
                        <button id="btnGuardarAvance" class="conteo-button secondary" type="button">Guardar avance</button>
                        <button id="btnGenerarDiferencias" class="conteo-button secondary" type="button">Imprimir diferencias</button>
                    </div>
                    <p>Los cambios se guardan al salir de cada campo. El stock no se actualiza hasta aplicar el conteo.</p>
                    <div class="conteo-listado-toolbar">
                        <label for="filtroInsumosConteo">Buscar insumo
                            <input id="filtroInsumosConteo" type="search" placeholder="Nombre del insumo">
                        </label>
                        <label for="tamanoPaginaConteo">Por página
                            <select id="tamanoPaginaConteo">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                            </select>
                        </label>
                    </div>
                    <table class="conteo-table">
                        <thead><tr><th>Insumo</th><th>En sistema</th><th>Contado</th></tr></thead>
                        <tbody id="itemsConteo"></tbody>
                    </table>
                    <nav id="paginacionConteo" class="pagination" aria-label="Paginación del conteo"></nav>
                    <div class="conteo-actions">
                        <button class="conteo-button" type="submit">Aplicar conteo y actualizar stock</button>
                        <button id="btnCancelarConteo" class="conteo-button danger" type="button">Cancelar conteo</button>
                    </div>
                </form>
            </section>
            <dialog id="confirmarConteoDialog" class="conteo-dialog" aria-labelledby="tituloConfirmarConteo">
                <form id="confirmarConteoForm">
                    <h2 id="tituloConfirmarConteo">Confirmar y aplicar conteo</h2>
                    <p>Revisá el resumen antes de actualizar las existencias:</p>
                    <ul>
                        <li id="resumenFaltantes"></li>
                        <li id="resumenSobrantes"></li>
                        <li id="resumenDiferenciaValorizada"></li>
                    </ul>
                    <label for="contrasenaAdministrador">Contraseña del administrador conectado
                        <input id="contrasenaAdministrador" name="contrasena_administrador" type="password" autocomplete="current-password" required>
                    </label>
                    <p id="errorConfirmarConteo" class="conteo-dialog-error" role="alert"></p>
                    <div class="conteo-actions">
                        <button id="btnConfirmarAplicacion" class="conteo-button" type="submit">Confirmar y actualizar</button>
                        <button id="btnCancelarConfirmacion" class="conteo-button secondary" type="button">Volver</button>
                    </div>
                </form>
            </dialog>
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
    let itemsConteo = [];
    let paginaConteo = 1;
    const categoriaConteo = document.getElementById("categoriaConteo");
    const panelConteo = document.getElementById("panelConteo");
    const confirmarConteoDialog = document.getElementById("confirmarConteoDialog");
    let guardadoBorrador = Promise.resolve();

    document.getElementById("btnIniciarConteo").addEventListener("click", iniciarConteo);
    document.getElementById("btnGenerarBase").addEventListener("click", () => abrirPDFConteo("base"));
    document.getElementById("btnGenerarDiferencias").addEventListener("click", () => abrirPDFConteo("diferencias"));
    document.getElementById("btnGuardarAvance").addEventListener("click", () => guardarAvance().catch(() => {}));
    document.getElementById("btnCancelarConteo").addEventListener("click", cancelarConteo);
    document.getElementById("formConteo").addEventListener("submit", abrirConfirmacionConteo);
    document.getElementById("confirmarConteoForm").addEventListener("submit", confirmarAplicacionConteo);
    document.getElementById("btnCancelarConfirmacion").addEventListener("click", () => confirmarConteoDialog.close());
    document.getElementById("itemsConteo").addEventListener("change", guardarBorrador);
    document.getElementById("itemsConteo").addEventListener("input", validarCantidadUnidad);
    document.getElementById("itemsConteo").addEventListener("keydown", bloquearDecimalUnidad);
    document.getElementById("itemsConteo").addEventListener("paste", filtrarPegadoUnidad);
    document.getElementById("listaPendientes").addEventListener("click", gestionarPendientes);
    document.getElementById("listaHistorial").addEventListener("click", gestionarHistorial);
    document.getElementById("filtroInsumosConteo").addEventListener("input", () => { paginaConteo = 1; renderizarItemsConteo(); });
    document.getElementById("tamanoPaginaConteo").addEventListener("change", () => { paginaConteo = 1; renderizarItemsConteo(); });
    document.getElementById("paginacionConteo").addEventListener("click", cambiarPaginaConteo);

    function esUnidad(unidad) {
        return String(unidad || "").normalize("NFD").replace(/\u0300-\u036f/g, "").trim().toLowerCase() === "unidad";
    }

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
            mostrarMensaje("Conteo iniciado. Usá “Generar base” cuando quieras imprimir la hoja de conteo.", false);
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    function mostrarConteo(datos) {
        guardadoBorrador = guardadoBorrador.catch(() => {});
        conteoActivo = Number(datos.conteo.id_conteo);
        itemsConteo = datos.items.map((item) => ({ ...item }));
        paginaConteo = 1;
        document.getElementById("filtroInsumosConteo").value = "";
        document.getElementById("tituloConteoActivo").textContent = `Conteo #${conteoActivo}`;
        renderizarItemsConteo();
        panelConteo.hidden = false;
        panelConteo.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function obtenerItemsFiltradosConteo() {
        const filtro = document.getElementById("filtroInsumosConteo").value.trim().toLocaleLowerCase("es");
        return itemsConteo.filter((item) => `${item.nombre} ${item.unidad_medida || ""}`.toLocaleLowerCase("es").includes(filtro));
    }

    function renderizarItemsConteo() {
        const filtrados = obtenerItemsFiltradosConteo();
        const porPagina = Number(document.getElementById("tamanoPaginaConteo").value);
        const totalPaginas = Math.max(1, Math.ceil(filtrados.length / porPagina));
        paginaConteo = Math.min(paginaConteo, totalPaginas);
        const inicio = (paginaConteo - 1) * porPagina;
        document.getElementById("itemsConteo").innerHTML = filtrados.slice(inicio, inicio + porPagina).map((item) => `
            <tr>
                <td>${escapar(item.nombre)} <small>${escapar(item.unidad)}</small></td>
                <td>${escapar(item.cantidad_sistema)}</td>
                <td><input type="number" min="0" max="99999999.99" step="${esUnidad(item.unidad_medida) ? "1" : "0.01"}" ${esUnidad(item.unidad_medida) ? 'pattern="[0-9]*" inputmode="numeric"' : 'inputmode="decimal"'} required data-insumo="${Number(item.id_insumo)}" data-costo="${Number(item.costo)}" data-unidad="${escapar(item.unidad_medida || "")}" value="${item.cantidad_contada === null ? "" : escapar(item.cantidad_contada)}" aria-label="Cantidad contada de ${escapar(item.nombre)}"></td>
            </tr>
        `).join("") || '<tr><td colspan="3" class="conteo-empty">No hay insumos que coincidan.</td></tr>';

        const paginacion = document.getElementById("paginacionConteo");
        if (filtrados.length === 0) {
            paginacion.innerHTML = "";
        } else {
            paginacion.innerHTML = `<button class="button button--secondary" type="button" data-pagina="${paginaConteo - 1}" ${paginaConteo === 1 ? "disabled" : ""}>Anterior</button><span>Página ${paginaConteo} de ${totalPaginas} · ${filtrados.length} insumos</span><button class="button button--secondary" type="button" data-pagina="${paginaConteo + 1}" ${paginaConteo === totalPaginas ? "disabled" : ""}>Siguiente</button>`;
        }
    }

    function cambiarPaginaConteo(evento) {
        const boton = evento.target.closest("[data-pagina]");
        if (!boton || boton.disabled) return;
        paginaConteo = Number(boton.dataset.pagina);
        renderizarItemsConteo();
    }

    function bloquearDecimalUnidad(evento) {
        const input = evento.target.closest("input[data-insumo]");
        if (!input || !esUnidad(input.dataset.unidad)) return;
        if ([",", ".", "e", "E", "+", "-"].includes(evento.key)) {
            evento.preventDefault();
            mostrarMensaje("Los insumos medidos en unidades solo admiten números enteros.", true);
        }
    }

    function filtrarPegadoUnidad(evento) {
        const input = evento.target.closest("input[data-insumo]");
        if (!input || !esUnidad(input.dataset.unidad)) return;
        const pegado = evento.clipboardData.getData("text");
        if (!/^\d+$/.test(pegado)) {
            evento.preventDefault();
            mostrarMensaje("Los insumos medidos en unidades solo admiten números enteros.", true);
        }
    }

    function validarCantidadUnidad(evento) {
        const input = evento.target.closest("input[data-insumo]");
        if (!input) return;
        if (esUnidad(input.dataset.unidad) && input.value !== "") {
            const entero = input.value.replace(/[^0-9]/g, "");
            if (input.value !== entero) {
                input.value = entero;
                mostrarMensaje("Los insumos medidos en unidades solo admiten números enteros.", true);
            }
        }
        const item = itemsConteo.find((registro) => Number(registro.id_insumo) === Number(input.dataset.insumo));
        if (item) item.cantidad_contada = input.value.trim() === "" ? null : Number(input.value);
    }

    async function guardarAvance() {
        if (!conteoActivo) return;
        const idConteoGuardado = conteoActivo;
        const cantidades = itemsConteo.map((item) => ({
            id_insumo: Number(item.id_insumo),
            cantidad: item.cantidad_contada === null || item.cantidad_contada === undefined ? null : Number(item.cantidad_contada)
        }));
        await encolarGuardado(idConteoGuardado, cantidades, "Avance guardado. Podés salir y continuar el conteo más tarde.");
    }

    async function abrirPDFConteo(tipo) {
        if (!conteoActivo) return;
        const ventanaPdf = window.open("about:blank", "_blank");
        if (!ventanaPdf) {
            mostrarMensaje("El navegador bloqueó la ventana del PDF. Permití ventanas emergentes e intentá nuevamente.", true);
            return;
        }
        try {
            if (tipo === "diferencias") await guardarAvance();
            ventanaPdf.location.href = `apis/pdf_conteo.php?id_conteo=${encodeURIComponent(conteoActivo)}&tipo=${encodeURIComponent(tipo)}`;
        } catch (error) {
            ventanaPdf.close();
            mostrarMensaje(error.message, true);
        }
    }

    function guardarBorrador(evento) {
        const input = evento.target.closest("input[data-insumo]");
        if (!input || !conteoActivo) return Promise.resolve();

        const idConteoGuardado = conteoActivo;
        const cantidad = input.value.trim() === "" ? null : Number(input.value);
        const cantidades = [{
            id_insumo: Number(input.dataset.insumo),
            cantidad
        }];
        return encolarGuardado(idConteoGuardado, cantidades, "Borrador guardado. El stock no cambia hasta aplicar el conteo.");
    }

    function encolarGuardado(idConteoGuardado, cantidades, mensajeExito) {
        const guardadoAnterior = guardadoBorrador.catch(() => {});
        guardadoBorrador = guardadoAnterior.then(() => solicitar("apis/api_conteos.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                accion: "guardar_borrador",
                id_conteo: idConteoGuardado,
                cantidades
            })
        }))
            .then(() => {
                if (conteoActivo === idConteoGuardado) mostrarMensaje(mensajeExito, false);
            })
            .catch((error) => {
                if (conteoActivo === idConteoGuardado) mostrarMensaje(error.message, true);
                throw error;
            });
        guardadoBorrador.catch(() => {});
        return guardadoBorrador;
    }

    async function gestionarPendientes(evento) {
        const continuar = evento.target.closest("[data-continuar]");
        const cancelar = evento.target.closest("[data-cancelar]");
        const pdf = evento.target.closest("[data-pdf]");
        if (pdf) {
            const ventanaPdf = window.open(`apis/pdf_conteo.php?id_conteo=${encodeURIComponent(pdf.dataset.pdf)}`, "_blank");
            if (!ventanaPdf) mostrarMensaje("El navegador bloqueó la ventana del PDF. Permití ventanas emergentes e intentá nuevamente.", true);
            return;
        }
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

    function abrirConfirmacionConteo(evento) {
        evento.preventDefault();
        if (itemsConteo.some((item) => item.cantidad_contada === null || item.cantidad_contada === undefined || item.cantidad_contada === "")) {
            mostrarMensaje("Completá la cantidad de cada insumo.", true);
            return;
        }

        let cantidadFaltantes = 0;
        let cantidadSobrantes = 0;
        let diferenciaValorizada = 0;
        itemsConteo.forEach((item) => {
            const diferencia = Math.round((Number(item.cantidad_contada) - Number(item.cantidad_sistema)) * 100) / 100;
            if (diferencia < 0) {
                cantidadFaltantes++;
            } else if (diferencia > 0) {
                cantidadSobrantes++;
            }
            diferenciaValorizada += Math.round(diferencia * Number(item.costo || 0) * 100) / 100;
        });
        document.getElementById("resumenFaltantes").textContent = `Insumos con faltantes: ${cantidadFaltantes}`;
        document.getElementById("resumenSobrantes").textContent = `Insumos con sobrantes: ${cantidadSobrantes}`;
        diferenciaValorizada = Math.round(diferenciaValorizada * 100) / 100;
        const signoDiferencia = diferenciaValorizada > 0 ? "+" : "";
        document.getElementById("resumenDiferenciaValorizada").textContent = `Diferencia valorizada neta: $ ${signoDiferencia}${Number(diferenciaValorizada).toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById("errorConfirmarConteo").textContent = "";
        document.getElementById("contrasenaAdministrador").value = "";
        confirmarConteoDialog.showModal();
        document.getElementById("contrasenaAdministrador").focus();
    }

    async function confirmarAplicacionConteo(evento) {
        evento.preventDefault();
        const contrasena = document.getElementById("contrasenaAdministrador").value;
        const boton = document.getElementById("btnConfirmarAplicacion");
        boton.disabled = true;
        try {
            await guardadoBorrador.catch(() => {});
            await solicitar("apis/api_conteos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    accion: "finalizar",
                    id_conteo: conteoActivo,
                    contrasena_administrador: contrasena,
                    cantidades: itemsConteo.map((item) => ({
                        id_insumo: Number(item.id_insumo),
                        cantidad: Number(item.cantidad_contada)
                    }))
                })
            });
            confirmarConteoDialog.close();
            document.getElementById("contrasenaAdministrador").value = "";
            panelConteo.hidden = true;
            conteoActivo = null;
            await cargarResumen();
            mostrarMensaje("Conteo guardado y existencias actualizadas.", false);
        } catch (error) {
            document.getElementById("errorConfirmarConteo").textContent = error.message;
            document.getElementById("contrasenaAdministrador").select();
        } finally {
            boton.disabled = false;
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
                <div class="conteo-actions"><button class="conteo-button secondary" type="button" data-pdf="${Number(conteo.id_conteo)}">Generar base</button><button class="conteo-button secondary" type="button" data-continuar="${Number(conteo.id_conteo)}">Continuar</button><button class="conteo-button danger" type="button" data-cancelar="${Number(conteo.id_conteo)}">Cancelar</button></div>
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
                <td>${conteo.estado === "Finalizado" && Number(conteo.items_con_diferencia) > 0
                    ? `<button class="conteo-button secondary" type="button" data-diferencia="${Number(conteo.id_conteo)}">Generar diferencia</button>`
                    : "-"}</td>
            </tr>
        `).join("");
        lista.innerHTML = `
            <table class="conteo-table">
                <thead>
                    <tr><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Insumos</th><th>Con diferencia</th><th>Administrador</th><th>Acción</th></tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        `;
    }

    function gestionarHistorial(evento) {
        const boton = evento.target.closest("[data-diferencia]");
        if (!boton) return;
        const ventanaPdf = window.open(
            `apis/pdf_conteo.php?id_conteo=${encodeURIComponent(boton.dataset.diferencia)}&tipo=diferencias`,
            "_blank"
        );
        if (!ventanaPdf) mostrarMensaje("El navegador bloqueó la ventana del PDF. Permití ventanas emergentes e intentá nuevamente.", true);
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