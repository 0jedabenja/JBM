<?php
include_once "includes/funciones.php";
verificar_permiso("compras");
$tokenCsrf = obtener_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/compras.css">
</head>
<body>
    <?php include_once "includes/sidebar.php"; ?>
    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once "includes/profile.php"; ?>
        </div>
        <main class="compras-content">
            <header class="compras-heading">
                <div>
                    <h1>Compras de insumos</h1>
                    <p>Registrá compras para actualizar el stock y el costo promedio de cada insumo.</p>
                </div>
            </header>
            <section class="compras-panel" aria-labelledby="tituloNuevaCompra">
                <h2 id="tituloNuevaCompra">Registrar compra</h2>
                <form id="formCompra">
                    <label class="compras-observacion" for="observacionCompra">Proveedor o nota (opcional)
                        <input id="observacionCompra" maxlength="5000" placeholder="Ej.: proveedor, número de factura o comentario">
                    </label>
                    <div class="compras-table-wrap">
                        <table class="compras-table">
                            <thead>
                                <tr><th>Insumo</th><th>Stock actual</th><th>Cantidad comprada</th><th>Costo por unidad</th><th>Subtotal</th><th></th></tr>
                            </thead>
                            <tbody id="itemsCompra"></tbody>
                        </table>
                    </div>
                    <div class="compras-actions">
                        <button id="agregarInsumo" class="compras-button secondary" type="button">Agregar insumo</button>
                        <strong>Total de compra: <span id="totalCompra">$0,00</span></strong>
                        <button id="guardarCompra" class="compras-button" type="submit">Guardar compra</button>
                    </div>
                </form>
                <p id="mensajeCompra" class="compras-message" role="status" aria-live="polite"></p>
            </section>
            <section class="compras-history" aria-labelledby="tituloHistorialCompras">
                <h2 id="tituloHistorialCompras">Historial de compras</h2>
                <p id="mensajeHistorial" class="compras-message" role="status" aria-live="polite"></p>
                <div id="historialCompras"></div>
                <nav class="pagination" aria-label="Paginación de compras">
                    <button id="comprasAnterior" class="button button--secondary" type="button" disabled>Anterior</button>
                    <span id="paginaCompras">Página 1</span>
                    <button id="comprasSiguiente" class="button button--secondary" type="button" disabled>Siguiente</button>
                </nav>
            </section>
        </main>
    </div>
    <template id="plantillaItemCompra">
        <tr class="compra-item">
            <td><select class="compra-insumo" required aria-label="Insumo"></select></td>
            <td class="compra-stock">-</td>
            <td><input class="compra-cantidad" type="number" min="0.01" step="0.01" required aria-label="Cantidad comprada"></td>
            <td><input class="compra-costo" type="number" min="0.01" step="0.01" required aria-label="Costo por unidad"></td>
            <td class="compra-subtotal">$0,00</td>
            <td><button class="compra-quitar" type="button" aria-label="Quitar insumo">Quitar</button></td>
        </tr>
    </template>
    <script>
    const tokenCsrfCompra = <?php echo json_encode($tokenCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let insumosCompra = [];
    let paginaCompras = 1;
    const filasCompra = document.getElementById("itemsCompra");
    const mensajeCompra = document.getElementById("mensajeCompra");
    const formatoMonedaCompra = new Intl.NumberFormat("es-UY", { style: "currency", currency: "UYU" });

    document.getElementById("agregarInsumo").addEventListener("click", agregarItemCompra);
    document.getElementById("formCompra").addEventListener("submit", guardarCompra);
    filasCompra.addEventListener("input", actualizarTotalesCompra);
    filasCompra.addEventListener("change", (evento) => {
        if (evento.target.matches(".compra-insumo")) actualizarFilaCompra(evento.target.closest(".compra-item"));
        actualizarTotalesCompra();
    });
    filasCompra.addEventListener("click", (evento) => {
        if (!evento.target.matches(".compra-quitar")) return;
        if (filasCompra.children.length > 1) {
            evento.target.closest(".compra-item").remove();
            actualizarTotalesCompra();
        }
    });
    document.getElementById("comprasAnterior").addEventListener("click", () => {
        if (paginaCompras > 1) {
            paginaCompras--;
            cargarHistorialCompras();
        }
    });
    document.getElementById("historialCompras").addEventListener("click", (evento) => {
        const boton = evento.target.closest("[data-compra]");
        if (boton) window.open(`apis/boleta_pedido.php?tipo=compra&id_compra=${encodeURIComponent(boton.dataset.compra)}`, "comprobanteJBM", "width=420,height=700");
    });
    document.getElementById("comprasSiguiente").addEventListener("click", () => {
        if (document.getElementById("comprasSiguiente").dataset.hayMas === "true") {
            paginaCompras++;
            cargarHistorialCompras();
        }
    });

    async function cargarCompras() {
        try {
            const respuesta = await fetch("apis/api_compras.php", { cache: "no-store" });
            const datos = await respuesta.json();
            if (!respuesta.ok) throw new Error(datos.error || "No se pudieron cargar las compras.");
            insumosCompra = datos.insumos;
            agregarItemCompra();
            dibujarHistorialCompras(datos);
        } catch (error) {
            document.getElementById("guardarCompra").disabled = true;
            document.getElementById("agregarInsumo").disabled = true;
            mensajeCompra.textContent = error instanceof Error ? error.message : "No se pudieron cargar las compras.";
            document.getElementById("mensajeHistorial").textContent = mensajeCompra.textContent;
        }
    }

    function agregarItemCompra() {
        const fila = document.getElementById("plantillaItemCompra").content.firstElementChild.cloneNode(true);
        const selector = fila.querySelector(".compra-insumo");
        selector.innerHTML = '<option value="">Seleccioná un insumo...</option>' + insumosCompra
            .map((insumo) => `<option value="${escaparCompra(insumo.id_insumo)}">${escaparCompra(insumo.nombre)} (${escaparCompra(insumo.unidad)})</option>`)
            .join("");
        filasCompra.appendChild(fila);
    }

    function actualizarFilaCompra(fila) {
        const insumo = insumosCompra.find((item) => String(item.id_insumo) === fila.querySelector(".compra-insumo").value);
        fila.querySelector(".compra-stock").textContent = insumo
            ? `${insumo.cantidad_actual} ${insumo.unidad}`
            : "-";
    }

    function actualizarTotalesCompra() {
        let total = 0;
        filasCompra.querySelectorAll(".compra-item").forEach((fila) => {
            const cantidad = Number(fila.querySelector(".compra-cantidad").value) || 0;
            const costo = Number(fila.querySelector(".compra-costo").value) || 0;
            const subtotal = cantidad * costo;
            total += subtotal;
            fila.querySelector(".compra-subtotal").textContent = formatoMonedaCompra.format(subtotal);
        });
        document.getElementById("totalCompra").textContent = formatoMonedaCompra.format(total);
    }

    async function guardarCompra(evento) {
        evento.preventDefault();
        mensajeCompra.textContent = "";
        const items = Array.from(filasCompra.querySelectorAll(".compra-item"), (fila) => ({
            id_insumo: Number(fila.querySelector(".compra-insumo").value),
            cantidad: Number(fila.querySelector(".compra-cantidad").value),
            costo_unitario: Number(fila.querySelector(".compra-costo").value)
        }));
        if (items.some((item) => !item.id_insumo || item.cantidad <= 0 || item.costo_unitario <= 0)) {
            mensajeCompra.textContent = "Completá el insumo, la cantidad y el costo de cada línea.";
            return;
        }
        if (new Set(items.map((item) => item.id_insumo)).size !== items.length) {
            mensajeCompra.textContent = "Cada insumo debe aparecer una sola vez. Ajustá la cantidad en su línea existente.";
            return;
        }

        const boton = document.getElementById("guardarCompra");
        boton.disabled = true;
        try {
            const respuesta = await fetch("apis/api_compras.php", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-Token": tokenCsrfCompra },
                body: JSON.stringify({
                    observacion: document.getElementById("observacionCompra").value,
                    items
                })
            });
            const datos = await respuesta.json();
            if (!respuesta.ok) throw new Error(datos.error || "No se pudo guardar la compra.");
            document.getElementById("formCompra").reset();
            filasCompra.innerHTML = "";
            agregarItemCompra();
            actualizarTotalesCompra();
            mensajeCompra.textContent = `Compra #${datos.id_compra} registrada por ${formatoMonedaCompra.format(datos.costo_total)}. Se actualizaron el stock y el costo promedio.`;
            paginaCompras = 1;
            window.open(`apis/boleta_pedido.php?tipo=compra&id_compra=${encodeURIComponent(datos.id_compra)}`, "comprobanteJBM", "width=420,height=700");
            await cargarHistorialCompras();
        } catch (error) {
            mensajeCompra.textContent = error instanceof Error ? error.message : "No se pudo guardar la compra.";
        } finally {
            boton.disabled = false;
        }
    }

    async function cargarHistorialCompras() {
        const mensaje = document.getElementById("mensajeHistorial");
        mensaje.textContent = "";
        try {
            const respuesta = await fetch(`apis/api_compras.php?pagina=${paginaCompras}`, { cache: "no-store" });
            const datos = await respuesta.json();
            if (!respuesta.ok) throw new Error(datos.error || "No se pudo cargar el historial de compras.");
            dibujarHistorialCompras(datos);
        } catch (error) {
            mensaje.textContent = error instanceof Error ? error.message : "No se pudo cargar el historial de compras.";
        }
    }

    function dibujarHistorialCompras(datos) {
        const contenedor = document.getElementById("historialCompras");
        contenedor.innerHTML = datos.compras.length
            ? datos.compras.map((compra) => `<article class="compra-card">
                <header><h3>Compra #${escaparCompra(compra.id_compra)}</h3><span>${escaparCompra(compra.fecha_hora)}</span></header>
                <p>Registrada por ${escaparCompra(compra.responsable)} · Total: ${formatoMonedaCompra.format(Number(compra.costo_total))}</p>
                ${compra.observacion ? `<p>${escaparCompra(compra.observacion)}</p>` : ""}
                <div class="compras-table-wrap"><table class="compras-table"><thead><tr><th>Insumo</th><th>Cantidad</th><th>Costo unitario</th><th>Subtotal</th></tr></thead><tbody>
                    ${compra.items.map((item) => `<tr><td>${escaparCompra(item.nombre)}</td><td>${escaparCompra(item.cantidad)} ${escaparCompra(item.unidad)}</td><td>${formatoMonedaCompra.format(Number(item.costo))}</td><td>${formatoMonedaCompra.format(Number(item.total))}</td></tr>`).join("")}
                </tbody></table></div>
                <div class="compra-card-actions"><button type="button" class="compras-button compra-imprimir" data-compra="${Number(compra.id_compra)}">Imprimir comprobante</button></div>
            </article>`).join("")
            : '<p class="compras-empty">Todavía no hay compras registradas.</p>';
        document.getElementById("mensajeHistorial").textContent = `Total: ${datos.total} compras`;
        document.getElementById("paginaCompras").textContent = `Página ${datos.pagina}`;
        document.getElementById("comprasAnterior").disabled = !datos.hay_anterior;
        document.getElementById("comprasSiguiente").disabled = !datos.hay_siguiente;
        document.getElementById("comprasSiguiente").dataset.hayMas = datos.hay_siguiente ? "true" : "false";
    }

    function escaparCompra(valor) {
        return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    cargarCompras();
    </script>
</body>
</html>
