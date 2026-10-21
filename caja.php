<?php
include_once "includes/funciones.php";
verificar_permiso("caja");
$esAdministrador = obtener_rol_sesion() === "administrador";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/caja.css">
</head>
<body>
<?php include_once 'includes/sidebar.php'; ?>
<div class="main">
    <div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once 'includes/profile.php'; ?></div>
    <main class="caja-content">
        <header class="caja-heading">
            <div><h1>Caja</h1><p>Control de turnos, cobros y cierres.</p></div>
            <span id="estadoCaja" class="caja-status cerrada">Consultando caja</span>
        </header>
        <p id="mensajeCaja" class="caja-message" role="status" aria-live="polite"></p>
        <section class="caja-current" aria-labelledby="tituloCajaActual">
            <div class="caja-current-top">
                <div><h2 id="tituloCajaActual">Turno actual</h2><p class="caja-subtitle" id="resumenTurno">Cargando información...</p></div>
                <div class="caja-actions"><button id="botonAbrir" class="caja-button" type="button" hidden>Abrir caja</button><button id="botonCerrar" class="caja-button secondary" type="button" hidden>Cerrar caja</button></div>
            </div>
            <div id="estadoTurno"></div>
            <div id="contenidoCaja" hidden>
                <div class="caja-metrics">
                    <div class="caja-metric"><span>Total cobrado</span><strong id="totalCobrado">$ 0,00</strong></div>
                    <div class="caja-metric"><span>Efectivo esperado en caja</span><strong id="efectivoEsperado">$ 0,00</strong></div>
                    <div class="caja-metric"><span>Tiempo abierta</span><strong id="tiempoCaja">00:00:00</strong></div>
                </div>
                <div id="avisoTurnoLargo" class="caja-warning" hidden>Esta sesión supera las 6 horas.</div>
                <div class="caja-section-heading"><h2>Movimientos del turno</h2></div>
                <div class="caja-table-wrap"><table class="caja-table"><thead><tr><th>Pedido</th><th>Hora</th><th>Importe</th><th>Medio de pago</th><th>Estado</th><th>Acción</th></tr></thead><tbody id="movimientosCaja"></tbody></table></div>
            </div>
        </section>
        <section class="caja-section" aria-labelledby="tituloSesionesPropias"><div class="caja-section-heading"><div><h2 id="tituloSesionesPropias">Mis últimas sesiones</h2><p class="caja-subtitle">Tus cinco turnos cerrados más recientes.</p></div></div><div class="caja-table-wrap"><table class="caja-table"><thead><tr><th>Sesión</th><th>Apertura</th><th>Cierre</th><th>Monto inicial</th><th>Monto final</th><th></th></tr></thead><tbody id="sesionesPropias"></tbody></table></div></section>
        <section id="seccionAdmin" class="caja-section" aria-labelledby="tituloHistorialAdmin" hidden><div class="caja-section-heading"><div><h2 id="tituloHistorialAdmin">Historial de todas las cajas</h2><p class="caja-subtitle">Sesiones de todos los empleados, ordenadas por apertura reciente.</p></div></div><div class="caja-table-wrap"><table class="caja-table"><thead><tr><th>Sesión</th><th>Responsable</th><th>Apertura</th><th>Cierre / estado</th><th>Monto inicial</th><th>Monto final</th><th></th></tr></thead><tbody id="historialAdmin"></tbody></table></div><nav class="caja-pagination" aria-label="Paginación del historial"><button id="paginaAnterior" class="caja-button secondary" type="button">Anterior</button><span id="paginaInfo"></span><button id="paginaSiguiente" class="caja-button secondary" type="button">Siguiente</button></nav></section>
    </main>
</div>

<dialog id="dialogAbrir" class="caja-dialog" aria-labelledby="tituloDialogAbrir"><form id="formAbrir" class="caja-dialog-inner"><div class="caja-dialog-head"><div><h2 id="tituloDialogAbrir">Abrir caja</h2><p>Indica el efectivo disponible al iniciar el turno.</p></div><button class="caja-dialog-close" type="button" data-cerrar-dialog="dialogAbrir" aria-label="Cerrar">&times;</button></div><div class="caja-field"><label for="montoInicial">Monto inicial</label><input id="montoInicial" type="number" min="0" step="0.01" required inputmode="decimal" placeholder="0,00"></div><p id="errorAbrir" class="caja-message error" role="alert"></p><div class="caja-dialog-footer"><button class="caja-button secondary" type="button" data-cerrar-dialog="dialogAbrir">Cancelar</button><button class="caja-button" type="submit">Confirmar apertura</button></div></form></dialog>
<dialog id="dialogCerrar" class="caja-dialog" aria-labelledby="tituloDialogCerrar"><form id="formCerrar" class="caja-dialog-inner"><div class="caja-dialog-head"><div><h2 id="tituloDialogCerrar">Cerrar caja</h2><p>Cuenta el efectivo y registra el monto final del turno.</p></div><button class="caja-dialog-close" type="button" data-cerrar-dialog="dialogCerrar" aria-label="Cerrar">&times;</button></div><div class="caja-current-state"><p>Efectivo esperado</p><strong id="efectivoEsperadoCierre">$ 0,00</strong></div><div class="caja-field"><label for="montoFinal">Efectivo contado al cierre</label><input id="montoFinal" type="number" min="0" step="0.01" required inputmode="decimal" placeholder="0,00"></div><p id="diferenciaCierre" class="caja-subtitle"></p><p id="errorCerrar" class="caja-message error" role="alert"></p><div class="caja-dialog-footer"><button class="caja-button secondary" type="button" data-cerrar-dialog="dialogCerrar">Cancelar</button><button class="caja-button" type="submit">Confirmar cierre</button></div></form></dialog>
<dialog id="dialogDetalle" class="caja-dialog" aria-labelledby="tituloDialogDetalle"><div class="caja-dialog-inner"><div class="caja-dialog-head"><div><h2 id="tituloDialogDetalle">Detalle de sesión</h2><p id="subtituloDetalle"></p></div><button class="caja-dialog-close" type="button" data-cerrar-dialog="dialogDetalle" aria-label="Cerrar">&times;</button></div><dl id="resumenDetalle" class="caja-detail-summary"></dl><h3>Movimientos</h3><div class="caja-detail-movements caja-table-wrap"><table class="caja-table"><thead><tr><th>Pedido</th><th>Fecha</th><th>Importe</th><th>Medio</th><th>Estado</th></tr></thead><tbody id="movimientosDetalle"></tbody></table></div><p id="sinMovimientosDetalle" class="caja-empty" hidden>No hay movimientos en esta sesión.</p></div></dialog>
<dialog id="dialogCobroEfectivo" class="caja-dialog" aria-labelledby="tituloCobroEfectivo"><form id="formCobroEfectivo" class="caja-dialog-inner"><div class="caja-dialog-head"><div><h2 id="tituloCobroEfectivo">Cobrar en efectivo</h2><p>Ingresa cuánto dinero entregó el cliente.</p></div><button class="caja-dialog-close" type="button" data-cerrar-dialog="dialogCobroEfectivo" aria-label="Cerrar">&times;</button></div><div class="caja-current-state"><p>Total del pedido</p><strong id="totalCobroEfectivo">$ 0,00</strong></div><div class="caja-field"><label for="efectivoRecibido">Efectivo recibido</label><input id="efectivoRecibido" type="number" min="0" step="0.01" required inputmode="decimal" placeholder="0,00"></div><div class="caja-current-state"><p>Vuelto</p><strong id="vueltoCobroEfectivo">$ 0,00</strong></div><p id="errorCobroEfectivo" class="caja-message error" role="alert"></p><div class="caja-dialog-footer"><button class="caja-button secondary" type="button" data-cerrar-dialog="dialogCobroEfectivo">Cancelar</button><button id="confirmarCobroEfectivo" class="caja-button" type="submit">Confirmar cobro</button></div></form></dialog>

<script>
const esAdministrador = <?php echo $esAdministrador ? "true" : "false"; ?>;
let sesionAbierta = null;
let efectivoEsperadoActual = 0;
let paginaHistorial = 1;
let totalPaginas = 1;
let metodosPago = [];
let cobroEfectivoActual = null;
const pagosEfectivoTemporales = new Map();

async function cargarCaja(pagina = paginaHistorial) {
    paginaHistorial = pagina;
    try {
        const respuesta = await fetch(`apis/api_caja.php?pagina=${pagina}`);
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo consultar la caja.");
        sesionAbierta = datos.abierta;
        metodosPago = datos.metodos_pago || [];
        renderizarTurno(datos);
        renderizarSesiones(datos.sesiones_propias || []);
        if (esAdministrador) renderizarHistorial(datos);
    } catch (error) {
        mostrarMensaje(error.message, true);
        document.getElementById("resumenTurno").textContent = "No se pudo cargar la información de caja.";
    }
}

function renderizarTurno(datos) {
    const estado = document.getElementById("estadoCaja");
    const botonAbrir = document.getElementById("botonAbrir");
    const botonCerrar = document.getElementById("botonCerrar");
    const contenido = document.getElementById("contenidoCaja");
    estado.textContent = sesionAbierta ? "Caja abierta" : "Caja cerrada";
    estado.className = `caja-status ${sesionAbierta ? "abierta" : "cerrada"}`;
    botonAbrir.hidden = Boolean(sesionAbierta);
    botonCerrar.hidden = !sesionAbierta;
    contenido.hidden = !sesionAbierta;
    if (!sesionAbierta) {
        document.getElementById("resumenTurno").textContent = "No hay un turno abierto. Abre caja para habilitar nuevos pedidos.";
        document.getElementById("estadoTurno").innerHTML = '<div class="caja-current-state"><p>La caja está cerrada. El monto inicial se solicitará al abrir el próximo turno.</p></div>';
        document.getElementById("movimientosCaja").innerHTML = '<tr><td class="caja-empty" colspan="6">No hay movimientos en un turno abierto.</td></tr>';
        return;
    }
    const apertura = formatearFecha(sesionAbierta.fecha_hora_apertura);
    document.getElementById("resumenTurno").textContent = `Sesión #${sesionAbierta.id_turno} · Responsable: ${sesionAbierta.empleado} · Apertura: ${apertura}`;
    document.getElementById("estadoTurno").innerHTML = "";
    document.getElementById("totalCobrado").textContent = moneda(datos.total_cobrado);
    efectivoEsperadoActual = Number(datos.efectivo_esperado || 0);
    document.getElementById("efectivoEsperado").textContent = moneda(efectivoEsperadoActual);
    renderizarMovimientos(datos.movimientos || [], "movimientosCaja", true);
    actualizarTiempoCaja();
}

function renderizarMovimientos(movimientos, idContenedor, permitirCobro) {
    const contenedor = document.getElementById(idContenedor);
    if (!movimientos.length) {
        contenedor.innerHTML = `<tr><td class="caja-empty" colspan="${permitirCobro ? 6 : 5}">No hay movimientos registrados.</td></tr>`;
        return;
    }
    contenedor.innerHTML = movimientos.map(movimiento => {
        const listo = String(movimiento.estado).toLowerCase() === "listo";
        const cobrado = Number(movimiento.cobrado) === 1;
        const estado = cobrado ? "Cobrado" : listo ? "Listo para cobrar" : "En preparación";
        const estadoClass = cobrado ? "cobrado" : "pendiente";
        const opcionesPago = metodosPago.map(metodo => `<option value="${Number(metodo.id_mp)}" ${Number(metodo.id_mp) === Number(movimiento.id_mp) ? "selected" : ""}>${escapeHTML(metodo.descripcion)}</option>`).join("");
        const efectivoRecibido = pagosEfectivoTemporales.get(Number(movimiento.id_pedido));
        const datosEfectivo = efectivoRecibido === undefined ? "" : `&efectivo_recibido=${encodeURIComponent(efectivoRecibido.toFixed(2))}`;
        const accion = permitirCobro && listo && !cobrado
            ? `<select aria-label="Medio de pago del pedido ${Number(movimiento.id_pedido)}" data-metodo-pago><option value="">Medio de pago...</option>${opcionesPago}</select> <button class="caja-button secondary" type="button" data-cobrar="${Number(movimiento.id_pedido)}" data-total="${Number(movimiento.monto_total)}">Cobrar</button>`
            : permitirCobro && cobrado
                ? `<a class="caja-button secondary" href="apis/boleta_pedido.php?id_pedido=${Number(movimiento.id_pedido)}${datosEfectivo}" target="_blank" rel="noopener">Imprimir comprobante</a>`
                : "-";
        return `<tr><td>#${escapeHTML(movimiento.id_pedido)}</td><td>${escapeHTML(formatearFecha(movimiento.fecha_hora_creacion))}</td><td>${moneda(movimiento.monto_total)}</td><td>${escapeHTML(movimiento.metodo_pago)}</td><td><span class="caja-movement-state ${estadoClass}">${estado}</span></td>${permitirCobro ? `<td>${accion}</td>` : ""}</tr>`;
    }).join("");
}

function renderizarSesiones(sesiones) {
    const contenedor = document.getElementById("sesionesPropias");
    contenedor.innerHTML = sesiones.length ? sesiones.map(sesion => `<tr><td>#${escapeHTML(sesion.id_turno)}</td><td>${escapeHTML(formatearFecha(sesion.fecha_hora_apertura))}</td><td>${escapeHTML(formatearFecha(sesion.fecha_hora_cierre))}</td><td>${moneda(sesion.monto_inicial)}</td><td>${moneda(sesion.monto_final)}</td><td><button class="caja-button secondary" type="button" data-detalle="${Number(sesion.id_turno)}">Ver movimientos</button></td></tr>`).join("") : '<tr><td class="caja-empty" colspan="6">Todavía no tienes sesiones cerradas.</td></tr>';
}

function renderizarHistorial(datos) {
    const contenedor = document.getElementById("historialAdmin");
    document.getElementById("seccionAdmin").hidden = false;
    contenedor.innerHTML = datos.historial.length ? datos.historial.map(sesion => `<tr><td>#${escapeHTML(sesion.id_turno)}</td><td>${escapeHTML(sesion.empleado)}</td><td>${escapeHTML(formatearFecha(sesion.fecha_hora_apertura))}</td><td>${sesion.fecha_hora_cierre ? escapeHTML(formatearFecha(sesion.fecha_hora_cierre)) : '<span class="caja-movement-state pendiente">Abierta</span>'}</td><td>${moneda(sesion.monto_inicial)}</td><td>${sesion.monto_final === null ? "-" : moneda(sesion.monto_final)}</td><td><button class="caja-button secondary" type="button" data-detalle="${Number(sesion.id_turno)}">Ver movimientos</button></td></tr>`).join("") : '<tr><td class="caja-empty" colspan="7">No hay sesiones registradas.</td></tr>';
    totalPaginas = Math.max(1, Math.ceil(Number(datos.total_sesiones || 0) / Number(datos.por_pagina || 10)));
    document.getElementById("paginaInfo").textContent = `Página ${datos.pagina} de ${totalPaginas} · ${datos.total_sesiones} sesiones`;
    document.getElementById("paginaAnterior").disabled = Number(datos.pagina) <= 1;
    document.getElementById("paginaSiguiente").disabled = Number(datos.pagina) >= totalPaginas;
}

async function enviar(datos, mensaje, idError) {
    const respuesta = await fetch("apis/api_caja.php", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(datos)});
    const resultado = await respuesta.json();
    if (!respuesta.ok) {
        if (idError) document.getElementById(idError).textContent = resultado.error || "No se pudo completar la operación.";
        else mostrarMensaje(resultado.error || "No se pudo completar la operación.", true);
        return false;
    }
    cerrarDialogo("dialogAbrir");
    cerrarDialogo("dialogCerrar");
    mostrarMensaje(mensaje, false);
    await cargarCaja();
    return true;
}

async function verDetalle(idTurno) {
    const dialogo = document.getElementById("dialogDetalle");
    document.getElementById("tituloDialogDetalle").textContent = `Sesión #${idTurno}`;
    document.getElementById("subtituloDetalle").textContent = "Cargando movimientos...";
    document.getElementById("resumenDetalle").innerHTML = "";
    document.getElementById("movimientosDetalle").innerHTML = "";
    dialogo.showModal();
    try {
        const respuesta = await fetch(`apis/api_caja.php?detalle=${idTurno}`);
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo cargar el detalle.");
        const sesion = datos.sesion;
        document.getElementById("subtituloDetalle").textContent = `${sesion.empleado} · Apertura ${formatearFecha(sesion.fecha_hora_apertura)}`;
        const valores = [["Estado", sesion.fecha_hora_cierre ? "Cerrada" : "Abierta"], ["Cierre", sesion.fecha_hora_cierre ? formatearFecha(sesion.fecha_hora_cierre) : "En curso"], ["Monto inicial", moneda(sesion.monto_inicial)], ["Monto final", sesion.monto_final === null ? "Pendiente" : moneda(sesion.monto_final)], ["Total cobrado", moneda(sesion.total_cobrado)], ["Efectivo esperado", moneda(sesion.efectivo_esperado)]];
        document.getElementById("resumenDetalle").innerHTML = valores.map(([etiqueta, valor]) => `<div><dt>${etiqueta}</dt><dd>${escapeHTML(valor)}</dd></div>`).join("");
        renderizarMovimientos(datos.movimientos || [], "movimientosDetalle", false);
        document.getElementById("sinMovimientosDetalle").hidden = Boolean(datos.movimientos.length);
    } catch (error) {
        document.getElementById("subtituloDetalle").textContent = error.message;
    }
}

function actualizarTiempoCaja() {
    if (!sesionAbierta) return;
    const segundos = Math.max(0, Math.floor((Date.now() - new Date(sesionAbierta.fecha_hora_apertura.replace(" ", "T")).getTime()) / 1000));
    const horas = Math.floor(segundos / 3600);
    document.getElementById("tiempoCaja").textContent = `${String(horas).padStart(2, "0")}:${String(Math.floor((segundos % 3600) / 60)).padStart(2, "0")}:${String(segundos % 60).padStart(2, "0")}`;
    document.getElementById("avisoTurnoLargo").hidden = horas < 6;
}

function cerrarDialogo(id) {
    const dialogo = document.getElementById(id);
    if (!dialogo.open || dialogo.classList.contains("cerrando")) return;
    dialogo.classList.add("cerrando");
    window.setTimeout(() => {
        dialogo.close();
        dialogo.classList.remove("cerrando");
    }, 180);
}

function moneda(valor) { return `$ ${Number(valor || 0).toLocaleString("es-AR", {minimumFractionDigits:2, maximumFractionDigits:2})}`; }
function formatearFecha(valor) { return valor ? String(valor).replace("T", " ") : "-"; }
function escapeHTML(valor) { return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;"); }
function mostrarMensaje(texto, error) { const elemento = document.getElementById("mensajeCaja"); elemento.textContent = texto; elemento.className = `caja-message${error ? " error" : ""}`; }

function actualizarVuelto() {
    const recibido = Number(document.getElementById("efectivoRecibido").value);
    const vuelto = Number.isFinite(recibido) && cobroEfectivoActual && recibido >= cobroEfectivoActual.total
        ? Math.round((recibido - cobroEfectivoActual.total) * 100) / 100
        : 0;
    document.getElementById("vueltoCobroEfectivo").textContent = moneda(vuelto);
}

async function confirmarCobroEfectivo(evento) {
    evento.preventDefault();
    if (!cobroEfectivoActual) return;
    const efectivoRecibido = Number(document.getElementById("efectivoRecibido").value);
    if (!Number.isFinite(efectivoRecibido) || efectivoRecibido < cobroEfectivoActual.total) {
        document.getElementById("errorCobroEfectivo").textContent = "El efectivo recibido debe ser igual o mayor que el total.";
        return;
    }
    const boton = document.getElementById("confirmarCobroEfectivo");
    boton.disabled = true;
    try {
        const respuesta = await fetch("apis/api_caja.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                accion: "cobrar",
                id_pedido: cobroEfectivoActual.idPedido,
                id_mp: cobroEfectivoActual.idMetodoPago,
                efectivo_recibido: efectivoRecibido
            })
        });
        const resultado = await respuesta.json();
        if (!respuesta.ok) throw new Error(resultado.error || "No se pudo registrar el cobro.");
        pagosEfectivoTemporales.set(cobroEfectivoActual.idPedido, Math.round(efectivoRecibido * 100) / 100);
        cerrarDialogo("dialogCobroEfectivo");
        mostrarMensaje(`Pedido cobrado. Vuelto: ${moneda(resultado.vuelto)}.`, false);
        cobroEfectivoActual = null;
        await cargarCaja();
    } catch (error) {
        document.getElementById("errorCobroEfectivo").textContent = error.message;
    } finally {
        boton.disabled = false;
    }
}

document.getElementById("botonAbrir").addEventListener("click", () => { document.getElementById("errorAbrir").textContent = ""; document.getElementById("formAbrir").reset(); document.getElementById("dialogAbrir").showModal(); document.getElementById("montoInicial").focus(); });
document.getElementById("botonCerrar").addEventListener("click", () => { document.getElementById("errorCerrar").textContent = ""; document.getElementById("formCerrar").reset(); document.getElementById("efectivoEsperadoCierre").textContent = moneda(efectivoEsperadoActual); document.getElementById("dialogCerrar").showModal(); document.getElementById("montoFinal").focus(); });
document.getElementById("formAbrir").addEventListener("submit", async evento => { evento.preventDefault(); await enviar({accion:"abrir", monto_inicial:Number(document.getElementById("montoInicial").value)}, "Caja abierta.", "errorAbrir"); });
document.getElementById("formCerrar").addEventListener("submit", async evento => { evento.preventDefault(); await enviar({accion:"cerrar", monto_final:Number(document.getElementById("montoFinal").value)}, "Caja cerrada.", "errorCerrar"); });
document.getElementById("montoFinal").addEventListener("input", evento => { const diferencia = Number(evento.target.value || 0) - efectivoEsperadoActual; document.getElementById("diferenciaCierre").textContent = evento.target.value === "" ? "" : `Diferencia respecto al efectivo esperado: ${moneda(diferencia)}`; });
document.getElementById("efectivoRecibido").addEventListener("input", actualizarVuelto);
document.getElementById("formCobroEfectivo").addEventListener("submit", confirmarCobroEfectivo);
document.getElementById("paginaAnterior").addEventListener("click", () => { if (paginaHistorial > 1) cargarCaja(paginaHistorial - 1); });
document.getElementById("paginaSiguiente").addEventListener("click", () => { if (paginaHistorial < totalPaginas) cargarCaja(paginaHistorial + 1); });
document.querySelectorAll("dialog.caja-dialog").forEach(dialogo => dialogo.addEventListener("cancel", evento => { evento.preventDefault(); cerrarDialogo(dialogo.id); }));
document.addEventListener("click", async evento => {
    const botonDetalle = evento.target.closest("[data-detalle]");
    const botonCobrar = evento.target.closest("[data-cobrar]");
    const botonCerrarDialogo = evento.target.closest("[data-cerrar-dialog]");
    if (botonDetalle) await verDetalle(Number(botonDetalle.dataset.detalle));
    if (botonCobrar) {
        const selectorMetodo = botonCobrar.closest("tr").querySelector("[data-metodo-pago]");
        const idMetodoPago = Number(selectorMetodo?.value);
        if (!idMetodoPago) {
            mostrarMensaje("Selecciona el medio de pago antes de cobrar.", true);
            return;
        }
        if (selectorMetodo.selectedOptions[0].textContent.trim().toLowerCase() === "efectivo") {
            cobroEfectivoActual = {
                idPedido: Number(botonCobrar.dataset.cobrar),
                idMetodoPago,
                total: Math.round(Number(botonCobrar.dataset.total) * 100) / 100
            };
            document.getElementById("errorCobroEfectivo").textContent = "";
            document.getElementById("totalCobroEfectivo").textContent = moneda(cobroEfectivoActual.total);
            const campoRecibido = document.getElementById("efectivoRecibido");
            campoRecibido.value = cobroEfectivoActual.total.toFixed(2);
            campoRecibido.min = cobroEfectivoActual.total.toFixed(2);
            actualizarVuelto();
            document.getElementById("dialogCobroEfectivo").showModal();
            campoRecibido.select();
            return;
        }
        await enviar({accion:"cobrar", id_pedido:Number(botonCobrar.dataset.cobrar), id_mp:idMetodoPago}, "Pedido cobrado.");
    }
    if (botonCerrarDialogo) cerrarDialogo(botonCerrarDialogo.dataset.cerrarDialog);
});
setInterval(actualizarTiempoCaja, 1000);
setInterval(() => cargarCaja(), 30000);
cargarCaja();
</script>
</body>
</html>