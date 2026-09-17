<?php
include_once "includes/funciones.php";
verificar_permiso("pedidos");
$rolActual = obtener_rol_sesion();
$puedeCrear = in_array($rolActual, array("administrador", "cajero", "mozo"), true);
$puedeCocina = in_array($rolActual, array("administrador", "cocina"), true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos</title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(2){background:#fff}.navigation ul li:nth-child(2) a{color:#001f47}.navigation ul li:nth-child(2) a .icon img{content:url('assets/img/sidebar/theme-restaurant.svg')}
        .advertencia-caja{background:#fff3cd;border:1px solid #d9a441;border-radius:6px;color:#765400;padding:12px}.bloqueado{cursor:not-allowed;opacity:.5}
        .pedidos-content{padding:20px 30px}.pedidos-content h1{color:#001f47}.pedido-tabs{display:flex;gap:10px;margin:20px 0}.pedido-tabs button{background:#fff;border:1px solid #001f47;border-radius:6px;color:#001f47;cursor:pointer;padding:10px 16px}.pedido-tabs button.active{background:#001f47;color:#fff}.pedido-view{display:none}.pedido-view.active{display:block}.menu-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}.producto-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px}.producto-card button{background:#001f47;border:0;border-radius:5px;color:#fff;cursor:pointer;padding:9px;width:100%}.pedido-panel,.cocina-pedido{border:1px solid #ddd;border-radius:8px;margin-bottom:15px;padding:16px}.pedido-panel h3,.cocina-pedido h3{color:#001f47;margin-top:0}.pedido-form{display:grid;gap:10px;grid-template-columns:repeat(4,1fr);margin:20px 0}.pedido-form label{color:#001f47;font-weight:bold}.pedido-form input{border:1px solid #bbb;border-radius:5px;padding:10px}.pedido-form button{background:#001f47;border:0;border-radius:5px;color:#fff;cursor:pointer;padding:10px}.mapa-pedido{aspect-ratio:1200/700;background:#dce4e2;background-image:linear-gradient(rgba(0,31,71,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(0,31,71,.08) 1px,transparent 1px);background-size:3.333% 5.714%;border:2px solid #78908b;border-radius:8px;margin:15px 0;overflow:hidden;position:relative}.mesa-pedido{align-items:center;border:3px solid #27824b;border-radius:10px;display:flex;flex-direction:column;justify-content:center;min-height:60px;position:absolute;transform:translate(-50%,-50%);width:8%;z-index:1}.mesa-pedido.libre{background:#e9f8ed;cursor:pointer}.mesa-pedido.ocupada,.mesa-pedido.paralimpiar{background:#f5dada;cursor:not-allowed;opacity:.65}.mesa-pedido.seleccionada{box-shadow:0 0 0 4px #001f47}.carrito{border-top:1px solid #ddd;margin-top:20px;padding-top:10px}.linea-carrito{display:flex;justify-content:space-between;padding:6px 0}.producto-cocina{align-items:center;border:1px solid #ddd;display:flex;gap:10px;justify-content:space-between;margin:8px 0;padding:10px}.producto-cocina.completo{background:#e9f8ed;text-decoration:line-through}.producto-cocina button{background:#001f47;border:0;border-radius:4px;color:#fff;cursor:pointer;padding:7px}.reloj{font-weight:bold}.reloj.amarillo{color:#c69200}.reloj.naranja{color:#e66c00}.reloj.rojo{color:#d62828}.mensaje{padding:10px}.error{color:#b42318}.exito{color:#16723b}@media(max-width:800px){.pedido-form{grid-template-columns:1fr 1fr}.pedidos-content{padding:15px}}@media(max-width:500px){.pedido-form{grid-template-columns:1fr}.pedido-tabs{flex-wrap:wrap}}
    </style>
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar"><div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div><?php include_once 'includes/profile.php'; ?></div>
        <main class="pedidos-content">
            <h1>Pedidos</h1>
            <nav class="pedido-tabs" aria-label="Secciones de pedidos">
                <?php if ($puedeCrear): ?><button type="button" data-vista="creacion">Crear pedido</button><?php endif; ?>
                <?php if ($puedeCrear): ?><button type="button" data-vista="pendientes">Pendientes</button><?php endif; ?>
                <?php if ($puedeCocina): ?><button type="button" data-vista="cocina">Cocina</button><?php endif; ?>
            </nav>
            <?php if ($puedeCrear): ?>
            <section id="vista-creacion" class="pedido-view">
                <h2>Menú rápido</h2><p id="estadoCajaPedido" class="advertencia-caja">Comprobando sesión de caja...</p><p id="mensajeCreacion" class="mensaje"></p><h3>Selecciona una mesa libre</h3><div id="mapaPedido" class="mapa-pedido"></div><input id="idMesa" type="hidden" required><p id="mesaSeleccionada">Ninguna mesa seleccionada.</p>
                <div id="menuProductos" class="menu-grid"></div>
                <div class="pedido-panel"><h3>Pedido actual</h3><div id="carrito" class="carrito">No hay productos agregados.</div><form id="formPedido" class="pedido-form"><label>Medio de pago<input id="idMp" type="number" min="1" value="1" required></label><label>Cliente (opcional)<input id="idCliente" type="number" min="1"></label><button type="submit">Crear pedido</button></form></div>
            </section>
            <section id="vista-pendientes" class="pedido-view"><h2>Pedidos pendientes</h2><div id="listaPendientes"></div></section>
            <?php endif; ?>
            <?php if ($puedeCocina): ?><section id="vista-cocina" class="pedido-view"><h2>Cocina</h2><p>Los pedidos aparecen automáticamente cuando se crean.</p><div id="listaCocina"></div></section><?php endif; ?>
        </main>
    </div>
<script>
const puedeCrear = <?php echo $puedeCrear ? "true" : "false"; ?>;
const puedeCocina = <?php echo $puedeCocina ? "true" : "false"; ?>;
let carrito = [];
let ultimoPedidoCocina = 0;
let contextoAudio = null;
let cajaAbierta = false;

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".pedido-tabs button").forEach(boton => boton.addEventListener("click", () => activarVista(boton.dataset.vista)));
    const inicial = puedeCrear ? "creacion" : "cocina";
    activarVista(inicial);
    if (puedeCrear) { cargarEstadoCajaPedido(); cargarMapaPedido(); document.getElementById("formPedido").addEventListener("submit", crearPedido); }
});
function activarVista(vista) {
    document.querySelectorAll(".pedido-view").forEach(seccion => seccion.classList.remove("active"));
    document.querySelectorAll(".pedido-tabs button").forEach(boton => boton.classList.toggle("active", boton.dataset.vista === vista));
    const seccion = document.getElementById(`vista-${vista}`); if (seccion) seccion.classList.add("active");
    if (vista === "pendientes") cargarPedidos("pendientes");
    if (vista === "cocina") { cargarPedidos("cocina"); setTimeout(() => cargarPedidos("cocina"), 300); }
}
function cargarMenu() {
    fetch("apis/api_productos.php?limite=sin&campo=todos&filtro=").then(r => r.json()).then(productos => {
        document.getElementById("menuProductos").innerHTML = productos.map(p => `<article class="producto-card"><h3>${escapeHTML(p.nombre)}</h3><p>$ ${escapeHTML(p.precio)}<br>${escapeHTML(p.categoria)}</p><button type="button" class="boton-producto" onclick="agregarProducto(${p.id_producto}, '${escapeJS(p.nombre)}', ${Number(p.precio)})">Agregar</button></article>`).join("");
    }).catch(() => document.getElementById("menuProductos").textContent = "No se pudo cargar el menú.");
}
async function cargarMapaPedido() { const respuesta=await fetch("apis/api_mesas.php"); const datos=await respuesta.json(); if(!respuesta.ok)return; const mapa=document.getElementById("mapaPedido"); mapa.innerHTML=""; datos.mesas.forEach(mesa=>{const estado=(mesa.estado||"").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"");const boton=document.createElement("button");boton.type="button";boton.className=`mesa-pedido ${estado}`;boton.style.left=`${Number(mesa.pos_x||0)/1200*100}%`;boton.style.top=`${Number(mesa.pos_y||0)/700*100}%`;boton.innerHTML=`<strong>Mesa ${escapeHTML(mesa.numero)}</strong><small>${escapeHTML(mesa.estado)}</small>`;boton.disabled=estado!=="libre";if(estado==="libre")boton.addEventListener("click",()=>seleccionarMesa(mesa,boton));mapa.appendChild(boton);}); }
function seleccionarMesa(mesa,elemento){document.querySelectorAll(".mesa-pedido").forEach(m=>m.classList.remove("seleccionada"));elemento.classList.add("seleccionada");document.getElementById("idMesa").value=mesa.id_mesa;document.getElementById("mesaSeleccionada").textContent=`Mesa ${mesa.numero} seleccionada.`;}
function agregarProducto(id, nombre, precio) {
    const cantidad = Number.parseInt(prompt(`Cantidad de ${nombre}:`, "1"), 10);
    if (!Number.isInteger(cantidad) || cantidad <= 0) return;
    const existente = carrito.find(p => p.id_producto === id);
    if (existente) existente.cantidad += cantidad; else carrito.push({id_producto:id,nombre,precio,cantidad});
    renderCarrito();
}
function renderCarrito() {
    const contenedor = document.getElementById("carrito");
    if (!carrito.length) { contenedor.textContent = "No hay productos agregados."; return; }
    contenedor.innerHTML = carrito.map(p => `<div class="linea-carrito"><span>${escapeHTML(p.nombre)} x ${p.cantidad}</span><strong>$ ${(p.precio * p.cantidad).toFixed(2)}</strong></div>`).join("");
}
function crearPedido(evento) {
    evento.preventDefault(); if (!cajaAbierta) { mostrarMensaje("No se puede crear el pedido: la caja está cerrada.", true); return; } if (!document.getElementById("idMesa").value) { mostrarMensaje("Selecciona una mesa libre antes de crear el pedido.", true); return; } if (!carrito.length) { mostrarMensaje("Agrega al menos un producto.", true); return; }
    const datos = {id_mesa:Number(document.getElementById("idMesa").value),id_mp:Number(document.getElementById("idMp").value),id_cliente:document.getElementById("idCliente").value ? Number(document.getElementById("idCliente").value) : null,productos:carrito.map(p => ({id_producto:p.id_producto,cantidad:p.cantidad}))};
    fetch("apis/api_pedidos.php", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(datos)}).then(r => r.json().then(d => ({ok:r.ok,data:d}))).then(r => { if (!r.ok) throw new Error(r.data.error || "No se pudo crear el pedido."); carrito=[]; renderCarrito(); document.getElementById("idMesa").value=""; document.getElementById("mesaSeleccionada").textContent="Ninguna mesa seleccionada."; cargarMapaPedido(); mostrarMensaje(`Pedido #${r.data.id_pedido} enviado a cocina.`, false); }).catch(e => { cargarMapaPedido(); mostrarMensaje(e.message, true); });
}
async function cargarEstadoCajaPedido() { const respuesta = await fetch("apis/api_pedidos.php?caja=estado"); const datos = await respuesta.json(); cajaAbierta = Boolean(respuesta.ok && datos.abierta); const aviso = document.getElementById("estadoCajaPedido"); aviso.textContent = cajaAbierta ? "Caja abierta. Se pueden crear pedidos." : "Caja cerrada. Abre una sesión de caja para crear pedidos."; aviso.className = cajaAbierta ? "mensaje exito" : "advertencia-caja"; document.querySelectorAll(".boton-producto, #formPedido button[type=submit]").forEach(boton => { boton.disabled = !cajaAbierta; boton.classList.toggle("bloqueado", !cajaAbierta); }); if (cajaAbierta && !document.getElementById("menuProductos").children.length) cargarMenu(); if (cajaAbierta) cargarMapaPedido(); }
function mostrarMensaje(texto, error) { const elemento=document.getElementById("mensajeCreacion"); elemento.textContent=texto; elemento.className=`mensaje ${error ? "error" : "exito"}`; }
function cargarPedidos(vista) {
    fetch(`apis/api_pedidos.php?vista=${vista}&limite=100`).then(r=>r.json()).then(pedidos => { if (vista === "cocina") renderCocina(pedidos); else renderPendientes(pedidos); }).catch(() => {});
}
function renderPendientes(pedidos) { document.getElementById("listaPendientes").innerHTML = pedidos.length ? pedidos.map(p => `<article class="pedido-panel"><h3>Pedido #${p.id_pedido}</h3><p>Estado: ${escapeHTML(p.estado)} | Mesa: ${escapeHTML(p.mesa || "-")} | Creado: ${escapeHTML(p.fecha_hora_creacion)}</p><p>${p.productos.map(x => `${escapeHTML(x.nombre)} x ${x.cantidad}`).join(", ")}</p></article>`).join("") : "No hay pedidos pendientes."; }
function renderCocina(pedidos) {
    const nuevos = pedidos.filter(p => Number(p.id_pedido) > ultimoPedidoCocina); if (ultimoPedidoCocina && nuevos.length) emitirAlerta(); if (pedidos.length) ultimoPedidoCocina = Math.max(...pedidos.map(p => Number(p.id_pedido)));
    document.getElementById("listaCocina").innerHTML = pedidos.length ? pedidos.map(p => `<article class="cocina-pedido"><h3>Pedido #${p.id_pedido}</h3><p>Mesa: ${escapeHTML(p.mesa || "-")} | <span class="reloj" data-inicio="${p.fecha_hora_creacion}"></span></p>${p.productos.map(x => `<div class="producto-cocina ${Number(x.completado) ? "completo" : ""}"><span>${escapeHTML(x.nombre)} x ${x.cantidad}</span>${Number(x.completado) ? "Completo" : `<button type="button" onclick="completarProducto(${p.id_pedido},${x.id_producto})">Completar</button>`}</div>`).join("")}<button type="button" onclick="finalizarPedido(${p.id_pedido})">Finalizar pedido</button></article>`).join("") : "No hay pedidos en cocina.";
    actualizarRelojes();
}
function completarProducto(id_pedido,id_producto) { fetch("apis/api_pedidos.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({accion:"completar_producto",id_pedido,id_producto})}).then(()=>cargarPedidos("cocina")); }
function finalizarPedido(id_pedido) { fetch("apis/api_pedidos.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({accion:"finalizar",id_pedido})}).then(r=>r.json().then(d=>({ok:r.ok,data:d}))).then(r=>{if(!r.ok) alert(r.data.error); cargarPedidos("cocina");}); }
function actualizarRelojes() { document.querySelectorAll(".reloj").forEach(el => { const minutos=Math.floor((Date.now()-new Date(el.dataset.inicio).getTime())/60000); const segundos=Math.max(0,Math.floor((Date.now()-new Date(el.dataset.inicio).getTime())/1000)); el.textContent=`Tiempo: ${String(Math.floor(segundos/60)).padStart(2,"0")}:${String(segundos%60).padStart(2,"0")}`; el.className=`reloj ${minutos>=30?"rojo":minutos>=20?"naranja":minutos>=10?"amarillo":""}`; }); }
setInterval(() => { actualizarRelojes(); if (puedeCrear) cargarEstadoCajaPedido(); if (puedeCocina && document.getElementById("vista-cocina")?.classList.contains("active")) cargarPedidos("cocina"); if (puedeCrear && document.getElementById("vista-pendientes")?.classList.contains("active")) cargarPedidos("pendientes"); }, 5000);
function emitirAlerta() { try { contextoAudio ||= new (window.AudioContext || window.webkitAudioContext)(); const oscilador=contextoAudio.createOscillator(); const ganancia=contextoAudio.createGain(); oscilador.connect(ganancia); ganancia.connect(contextoAudio.destination); oscilador.frequency.value=880; ganancia.gain.value=.15; oscilador.start(); oscilador.stop(contextoAudio.currentTime+.25); } catch (e) {} }
function escapeJS(valor) { return String(valor).replace(/\\/g,"\\\\").replace(/'/g,"\\'"); }
function escapeHTML(valor) { if (valor === null || valor === undefined) return ""; return String(valor).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;").replace(/'/g,"&#039;"); }
</script>
</body>
</html>
