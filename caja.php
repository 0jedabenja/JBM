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
    <style>
        .navigation ul li:nth-child(4){background:#fff}.navigation ul li:nth-child(4) a{color:#001f47}.navigation ul li:nth-child(4) a .icon img{content:url('assets/img/sidebar/theme-payments.svg')}
        .caja-content{color:#182b33;margin:0 auto;max-width:1280px;padding:28px 32px 48px}.caja-heading{align-items:center;display:flex;justify-content:space-between;gap:16px}.caja-heading h1{color:#001f47;font-size:30px;margin:0}.caja-heading p{color:#62737a;margin:7px 0 0}.caja-status{align-items:center;border-radius:4px;display:inline-flex;font-size:13px;font-weight:700;gap:8px;padding:8px 12px}.caja-status:before{background:currentColor;border-radius:50%;content:"";height:8px;width:8px}.caja-status.abierta{background:#e4f3ea;color:#16723b}.caja-status.cerrada{background:#f8eddb;color:#98620d}
        .caja-current{border-bottom:1px solid #dce3e3;margin-top:24px;padding:0 0 26px}.caja-current-top{align-items:flex-start;display:flex;justify-content:space-between;gap:20px}.caja-current h2,.caja-section h2{color:#001f47;font-size:20px;margin:0 0 8px}.caja-subtitle{color:#62737a;margin:0}.caja-actions{display:flex;flex-wrap:wrap;gap:9px}.caja-button{background:#001f47;border:1px solid #001f47;border-radius:4px;color:#fff;cursor:pointer;font:inherit;font-weight:650;padding:10px 15px}.caja-button:hover{background:#123b5c}.caja-button.secondary{background:#fff;color:#001f47}.caja-button.secondary:hover{background:#eef3f3}.caja-button:disabled{cursor:not-allowed;opacity:.55}.caja-metrics{display:grid;gap:1px;grid-template-columns:repeat(3,minmax(0,1fr));margin:22px 0 25px;background:#dce3e3;border:1px solid #dce3e3}.caja-metric{background:#fff;padding:16px 18px}.caja-metric span{color:#62737a;display:block;font-size:13px;margin-bottom:8px}.caja-metric strong{color:#172f37;font-size:21px}.caja-warning{background:#fff3db;border-left:4px solid #d99018;color:#765400;margin-top:15px;padding:11px 13px}.caja-message{min-height:20px;margin:10px 0;color:#16723b}.caja-message.error{color:#b42318}
        .caja-section{margin-top:28px}.caja-section-heading{align-items:baseline;display:flex;justify-content:space-between;gap:12px}.caja-table-wrap{overflow-x:auto}.caja-table{border-collapse:collapse;margin-top:12px;min-width:680px;text-align:left;width:100%}.caja-table th{background:#edf2f1;color:#41565e;font-size:12px;font-weight:700;letter-spacing:.04em;padding:11px 12px;text-transform:uppercase}.caja-table td{border-bottom:1px solid #e3e8e7;padding:12px}.caja-table tr:last-child td{border-bottom:0}.caja-table td small{color:#62737a;display:block;margin-top:4px}.caja-table .caja-button{font-size:13px;padding:7px 10px}.caja-empty{color:#62737a;padding:18px 0}.caja-pagination{align-items:center;display:flex;gap:10px;justify-content:flex-end;margin-top:14px}.caja-pagination span{color:#62737a;font-size:14px}.caja-current-state{align-items:center;background:#f3f7f6;border-left:4px solid #16723b;display:flex;gap:14px;margin-top:20px;padding:16px}.caja-current-state p{margin:0}.caja-current-state strong{color:#123b5c}.caja-movement-state{font-size:12px;font-weight:700}.caja-movement-state.cobrado{color:#16723b}.caja-movement-state.pendiente{color:#98620d}
        dialog.caja-dialog{border:0;border-radius:8px;box-shadow:0 16px 55px #10252d40;inset:0;margin:auto;max-height:min(88vh,780px);max-width:620px;padding:0;position:fixed;width:calc(100% - 32px)}dialog.caja-dialog::backdrop{background:#10252d88}.caja-dialog-inner{padding:28px}.caja-dialog-head{align-items:flex-start;border-bottom:1px solid #dce3e3;display:flex;justify-content:space-between;margin-bottom:20px;padding-bottom:15px}.caja-dialog-head h2{color:#001f47;font-size:21px;margin:0}.caja-dialog-head p{color:#62737a;margin:6px 0 0}.caja-dialog-close{background:transparent;border:0;color:#41565e;cursor:pointer;font-size:25px;line-height:1;padding:0 2px}.caja-field{display:grid;gap:7px;margin:15px 0}.caja-field label{color:#233b43;font-size:14px;font-weight:700}.caja-field input{border:1px solid #b8c5c5;border-radius:4px;font:inherit;padding:11px}.caja-field input:focus{border-color:#16723b;outline:2px solid #16723b33}.caja-dialog-footer{display:flex;gap:9px;justify-content:flex-end;margin-top:22px}.caja-detail-summary{display:grid;gap:0 20px;grid-template-columns:repeat(2,minmax(0,1fr));margin:0}.caja-detail-summary div{border-bottom:1px solid #e3e8e7;padding:10px 0}.caja-detail-summary dt{color:#62737a;font-size:12px;margin-bottom:5px}.caja-detail-summary dd{color:#172f37;font-weight:650;margin:0}.caja-detail-movements{max-height:250px;overflow:auto}
        @keyframes caja-dialog-entrada{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
        @keyframes caja-dialog-salida{from{opacity:1;transform:translateY(0) scale(1)}to{opacity:0;transform:translateY(8px) scale(.97)}}
        @keyframes caja-fondo-entrada{from{opacity:0}to{opacity:1}}
        @keyframes caja-fondo-salida{from{opacity:1}to{opacity:0}}
        dialog.caja-dialog[open]{animation:caja-dialog-entrada .2s ease-out both}
        dialog.caja-dialog[open]::backdrop{animation:caja-fondo-entrada .2s ease-out both}
        dialog.caja-dialog[open].cerrando{animation:caja-dialog-salida .18s ease-in both;pointer-events:none}
        dialog.caja-dialog[open].cerrando::backdrop{animation:caja-fondo-salida .18s ease-in both}
        @media(prefers-reduced-motion:reduce){dialog.caja-dialog[open],dialog.caja-dialog[open]::backdrop,dialog.caja-dialog[open].cerrando,dialog.caja-dialog[open].cerrando::backdrop{animation-duration:.01ms}}
        @media(max-width:720px){.caja-content{padding:22px 16px 36px}.caja-heading,.caja-current-top{align-items:flex-start;flex-direction:column}.caja-heading h1{font-size:26px}.caja-metrics{grid-template-columns:1fr}.caja-metric{padding:13px 15px}.caja-current-state{align-items:flex-start;flex-direction:column}.caja-detail-summary{grid-template-columns:1fr}.caja-dialog-inner{padding:20px}}
    </style>
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

<script>
const esAdministrador = <?php echo $esAdministrador ? "true" : "false"; ?>;
let sesionAbierta = null;
let efectivoEsperadoActual = 0;
let paginaHistorial = 1;
let totalPaginas = 1;

async function cargarCaja(pagina = paginaHistorial) {
    paginaHistorial = pagina;
    try {
        const respuesta = await fetch(`apis/api_caja.php?pagina=${pagina}`);
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo consultar la caja.");
        sesionAbierta = datos.abierta;
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
        const accion = permitirCobro && listo && !cobrado ? `<button class="caja-button secondary" type="button" data-cobrar="${Number(movimiento.id_pedido)}">Cobrar</button>` : "-";
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

document.getElementById("botonAbrir").addEventListener("click", () => { document.getElementById("errorAbrir").textContent = ""; document.getElementById("formAbrir").reset(); document.getElementById("dialogAbrir").showModal(); document.getElementById("montoInicial").focus(); });
document.getElementById("botonCerrar").addEventListener("click", () => { document.getElementById("errorCerrar").textContent = ""; document.getElementById("formCerrar").reset(); document.getElementById("efectivoEsperadoCierre").textContent = moneda(efectivoEsperadoActual); document.getElementById("dialogCerrar").showModal(); document.getElementById("montoFinal").focus(); });
document.getElementById("formAbrir").addEventListener("submit", async evento => { evento.preventDefault(); await enviar({accion:"abrir", monto_inicial:Number(document.getElementById("montoInicial").value)}, "Caja abierta.", "errorAbrir"); });
document.getElementById("formCerrar").addEventListener("submit", async evento => { evento.preventDefault(); await enviar({accion:"cerrar", monto_final:Number(document.getElementById("montoFinal").value)}, "Caja cerrada.", "errorCerrar"); });
document.getElementById("montoFinal").addEventListener("input", evento => { const diferencia = Number(evento.target.value || 0) - efectivoEsperadoActual; document.getElementById("diferenciaCierre").textContent = evento.target.value === "" ? "" : `Diferencia respecto al efectivo esperado: ${moneda(diferencia)}`; });
document.getElementById("paginaAnterior").addEventListener("click", () => { if (paginaHistorial > 1) cargarCaja(paginaHistorial - 1); });
document.getElementById("paginaSiguiente").addEventListener("click", () => { if (paginaHistorial < totalPaginas) cargarCaja(paginaHistorial + 1); });
document.querySelectorAll("dialog.caja-dialog").forEach(dialogo => dialogo.addEventListener("cancel", evento => { evento.preventDefault(); cerrarDialogo(dialogo.id); }));
document.addEventListener("click", async evento => {
    const botonDetalle = evento.target.closest("[data-detalle]");
    const botonCobrar = evento.target.closest("[data-cobrar]");
    const botonCerrarDialogo = evento.target.closest("[data-cerrar-dialog]");
    if (botonDetalle) await verDetalle(Number(botonDetalle.dataset.detalle));
    if (botonCobrar) await enviar({accion:"cobrar", id_pedido:Number(botonCobrar.dataset.cobrar)}, "Pedido cobrado.");
    if (botonCerrarDialogo) cerrarDialogo(botonCerrarDialogo.dataset.cerrarDialog);
});
setInterval(actualizarTiempoCaja, 1000);
setInterval(() => cargarCaja(), 30000);
cargarCaja();
</script>
</body>
</html>