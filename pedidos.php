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
        .pedidos-content{padding:20px 30px}.pedidos-content h1{color:#001f47}.pedido-tabs{display:flex;gap:10px;margin:20px 0}.pedido-tabs button{background:#fff;border:1px solid #001f47;border-radius:6px;color:#001f47;cursor:pointer;padding:10px 16px}.pedido-tabs button.active{background:#001f47;color:#fff}.pedido-view{display:none}.pedido-view.active{display:block}.filtros-menu{display:grid;gap:10px;grid-template-columns:minmax(220px,2fr) minmax(180px,1fr);margin:14px 0}.filtros-menu input,.filtros-menu select,.producto-card input{border:1px solid #bbb;border-radius:5px;padding:10px}.menu-grid{align-content:start;display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));max-height:min(48vh,460px);overflow-y:auto;padding:4px 10px 4px 4px;overscroll-behavior:contain;scrollbar-gutter:stable}.producto-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px}.producto-card h3{margin-top:0}.producto-card button,.pedido-accion{background:#001f47;border:0;border-radius:5px;color:#fff;cursor:pointer;padding:9px}.producto-card .producto-controles{display:grid;gap:8px;grid-template-columns:1fr auto}.producto-card input{min-width:0;width:58px}.pedido-panel,.cocina-pedido{border:1px solid #ddd;border-radius:8px;margin-bottom:15px;padding:16px}.pedido-panel h3,.cocina-pedido h3{color:#001f47;margin-top:0}.pedido-form{align-items:end;display:grid;gap:10px;grid-template-columns:repeat(3,minmax(0,1fr));margin:20px 0}.pedido-form label{color:#001f47;font-weight:bold}.pedido-form input,.pedido-form select{border:1px solid #bbb;border-radius:5px;padding:10px;width:100%}.pedido-form button{background:#001f47;border:0;border-radius:5px;color:#fff;cursor:pointer;padding:10px}.pedido-form .forzar-stock{align-items:center;color:#765400;display:flex;gap:8px;grid-column:1/-1}.pedido-form .forzar-stock input{width:auto}.mapa-pedido{aspect-ratio:1200/700;background:#dce4e2;background-image:linear-gradient(rgba(0,31,71,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(0,31,71,.08) 1px,transparent 1px);background-size:3.333% 5.714%;border:2px solid #78908b;border-radius:8px;box-sizing:border-box;margin:15px 0;overflow:hidden;position:relative}.mesa-pedido{align-items:center;border:3px solid #27824b;border-radius:10px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:center;min-height:60px;position:absolute;z-index:1}.mesa-pedido.libre{background:#e9f8ed;cursor:pointer}.mesa-pedido.ocupada,.mesa-pedido.paralimpiar{background:#f5dada;cursor:not-allowed;opacity:.65}.mesa-pedido.seleccionada{box-shadow:0 0 0 4px #001f47}.carrito{border-top:1px solid #ddd;margin-top:20px;padding-top:10px}.linea-carrito{align-items:center;border-bottom:1px solid #eee;display:grid;gap:10px;grid-template-columns:minmax(120px,1fr) auto auto auto; padding:8px 0}.linea-carrito input{border:1px solid #bbb;border-radius:4px;padding:6px;text-align:center;width:70px}.linea-carrito button,.pedido-panel .pedido-accion{background:#001f47;border:0;border-radius:4px;color:#fff;cursor:pointer;padding:7px 10px}.linea-carrito .quitar-producto,.pedido-panel .cancelar-pedido{background:#a32929}.acciones-pedido{display:flex;gap:8px;margin-top:12px}.producto-cocina{align-items:center;border:1px solid #ddd;display:flex;gap:10px;justify-content:space-between;margin:8px 0;padding:10px}.producto-cocina.completo{background:#e9f8ed;text-decoration:line-through}.producto-cocina button{background:#001f47;border:0;border-radius:4px;color:#fff;cursor:pointer;padding:7px}.reloj{font-weight:bold}.reloj.amarillo{color:#c69200}.reloj.naranja{color:#e66c00}.reloj.rojo{color:#d62828}.mensaje{padding:10px}.error{color:#b42318}.exito{color:#16723b}@media(max-width:800px){.pedidos-content{padding:15px}.pedido-form{grid-template-columns:1fr 1fr}}@media(max-width:500px){.pedido-form,.filtros-menu{grid-template-columns:1fr}.pedido-tabs{flex-wrap:wrap}.linea-carrito{grid-template-columns:minmax(0,1fr) auto}.linea-carrito strong{grid-column:1}.linea-carrito .quitar-producto{grid-column:2;grid-row:1}}
        .mesa-pedido.con-pedido{background:#fff2cc;border-color:#d99018;cursor:pointer;opacity:1}
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
                <h2>Menú rápido</h2><p id="estadoCajaPedido" class="advertencia-caja">Comprobando sesión de caja...</p><p id="mensajeCreacion" class="mensaje"></p><h3>Selecciona una mesa</h3><div id="mapaPedido" class="mapa-pedido"></div><input id="idMesa" type="hidden" required><input id="idPedidoActivo" type="hidden"><p id="mesaSeleccionada">Ninguna mesa seleccionada.</p>
                <div class="filtros-menu"><input id="buscarProducto" type="search" placeholder="Buscar producto..." aria-label="Buscar productos"><select id="categoriaProducto" aria-label="Filtrar por categoría"><option value="">Todas las categorías</option></select></div>
                <div id="menuProductos" class="menu-grid"></div>
                <div class="pedido-panel"><h3>Pedido actual</h3><div id="carrito" class="carrito">No hay productos agregados.</div><form id="formPedido" class="pedido-form"><label>Cliente (opcional)<select id="idCliente"><option value="">Cargando clientes...</option></select></label><label class="forzar-stock"><input id="forzarStock" type="checkbox"> Forzar pedido aunque falten insumos</label><button type="submit">Crear pedido</button></form></div>
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
let productosMenu = [];
let mesasPedido = [];
let pedidosPendientes = [];
let pedidoEnEdicion = 0;
let ultimoPedidoCocina = 0;
let contextoAudio = null;
let cajaAbierta = false;

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".pedido-tabs button").forEach(boton => boton.addEventListener("click", () => activarVista(boton.dataset.vista)));
    const inicial = puedeCrear ? "creacion" : "cocina";
    activarVista(inicial);
    if (puedeCrear) { cargarEstadoCajaPedido(); cargarMapaPedido(); cargarMenu(); cargarClientesPedido(); document.getElementById("formPedido").addEventListener("submit", crearPedido); document.getElementById("buscarProducto").addEventListener("input", renderMenu); document.getElementById("categoriaProducto").addEventListener("change", renderMenu); document.getElementById("menuProductos").addEventListener("click", evento => { const boton = evento.target.closest("[data-agregar]"); if (boton) agregarProducto(Number(boton.dataset.agregar)); }); }
});
async function cargarClientesPedido() {
    const selector = document.getElementById("idCliente");
    try {
        const respuesta = await fetch("apis/api_clientes.php?limite=sin&campo=todos&filtro=");
        const contenido = await respuesta.text();
        let clientes;
        try {
            clientes = contenido ? JSON.parse(contenido) : null;
        } catch (error) {
            throw new Error("El servidor devolvió una respuesta no válida al cargar los clientes.");
        }
        if (!respuesta.ok || !Array.isArray(clientes)) {
            throw new Error(clientes?.error || "No se pudieron cargar los clientes.");
        }

        selector.replaceChildren(new Option("Sin cliente", ""));
        if (clientes.length === 0) {
            selector.add(new Option("No hay clientes activos", ""));
            return;
        }
        clientes.forEach(cliente => {
            const nombre = `${cliente.nombre} ${cliente.apellido}`.trim();
            selector.add(new Option(nombre, cliente.id_cliente));
        });
    } catch (error) {
        selector.replaceChildren(new Option(error.message || "No se pudieron cargar los clientes.", ""));
        selector.disabled = true;
    }
}

function activarVista(vista) {
    document.querySelectorAll(".pedido-view").forEach(seccion => seccion.classList.remove("active"));
    document.querySelectorAll(".pedido-tabs button").forEach(boton => boton.classList.toggle("active", boton.dataset.vista === vista));
    const seccion = document.getElementById(`vista-${vista}`); if (seccion) seccion.classList.add("active");
    if (vista === "pendientes") cargarPedidos("pendientes");
    if (vista === "cocina") { cargarPedidos("cocina"); setTimeout(() => cargarPedidos("cocina"), 300); }
}
function cargarMenu() {
    fetch("apis/api_productos.php?limite=sin&campo=todos&filtro=").then(r => r.json()).then(productos => {
        if (!Array.isArray(productos)) throw new Error(productos.error || "No se pudo cargar el menú.");
        productosMenu = productos;
        const categorias = [...new Set(productos.map(p => p.categoria).filter(Boolean))].sort((a, b) => a.localeCompare(b, "es"));
        const selector = document.getElementById("categoriaProducto");
        categorias.forEach(categoria => selector.add(new Option(categoria, categoria)));
        renderMenu();
        actualizarControlesPedido();
    }).catch(() => document.getElementById("menuProductos").textContent = "No se pudo cargar el menú.");
}
function renderMenu() {
    const busqueda = document.getElementById("buscarProducto").value.trim().toLocaleLowerCase("es");
    const categoria = document.getElementById("categoriaProducto").value;
    const productos = productosMenu.filter(p => (!categoria || p.categoria === categoria) && (!busqueda || `${p.nombre} ${p.categoria || ""}`.toLocaleLowerCase("es").includes(busqueda)));
    document.getElementById("menuProductos").innerHTML = productos.length ? productos.map(p => `<article class="producto-card"><h3>${escapeHTML(p.nombre)}</h3><p>$ ${Number(p.precio).toFixed(2)}<br>${escapeHTML(p.categoria || "Sin categoría")}</p><div class="producto-controles"><input type="number" min="1" value="1" aria-label="Cantidad de ${escapeHTML(p.nombre)}"><button type="button" class="boton-producto" data-agregar="${Number(p.id_producto)}">Agregar</button></div></article>`).join("") : "No hay productos que coincidan.";
    actualizarControlesPedido();
}
async function cargarMapaPedido() { const respuesta=await fetch("apis/api_mesas.php"); const datos=await respuesta.json(); if(!respuesta.ok)return; mesasPedido=datos.mesas; const mapa=document.getElementById("mapaPedido"); mapa.innerHTML=""; mesasPedido.forEach(mesa=>{const estado=(mesa.estado||"").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"");const pedidoActivo=Number(mesa.id_pedido_activo)||0;const seleccionable=estado==="libre"||(estado==="ocupada"&&pedidoActivo>0);const boton=document.createElement("button");boton.type="button";boton.className=`mesa-pedido ${estado}${pedidoActivo?" con-pedido":""}`;boton.style.left=`${Number(mesa.pos_x||0)/1200*100}%`;boton.style.top=`${Number(mesa.pos_y||0)/700*100}%`;boton.style.width=`${Math.max(60,Number(mesa.ancho)||100)/1200*100}%`;boton.innerHTML=`<strong>Mesa ${escapeHTML(mesa.numero)}</strong><small>${pedidoActivo?`Pedido #${pedidoActivo}`:escapeHTML(mesa.estado)}</small>`;boton.disabled=!seleccionable;if(seleccionable)boton.addEventListener("click",()=>seleccionarMesa(mesa,boton));mapa.appendChild(boton);}); }
function seleccionarMesa(mesa,elemento){document.querySelectorAll(".mesa-pedido").forEach(m=>m.classList.remove("seleccionada"));elemento.classList.add("seleccionada");const idPedido=Number(mesa.id_pedido_activo)||0;document.getElementById("idMesa").value=mesa.id_mesa;document.getElementById("idPedidoActivo").value=idPedido||"";document.getElementById("mesaSeleccionada").textContent=idPedido?`Mesa ${mesa.numero}: se agregarán productos al pedido #${idPedido}.`:`Mesa ${mesa.numero} seleccionada. Se creará un pedido nuevo.`;document.querySelector("#formPedido button[type=submit]").textContent=idPedido?"Agregar al pedido":"Crear pedido";}
function agregarProducto(id) {
    const producto = productosMenu.find(p => Number(p.id_producto) === id);
    const boton = document.querySelector(`[data-agregar="${id}"]`);
    const cantidad = Number.parseInt(boton?.parentElement.querySelector("input").value, 10);
    if (!producto || !Number.isInteger(cantidad) || cantidad <= 0) return;
    const existente = carrito.find(p => Number(p.id_producto) === id);
    if (existente) existente.cantidad += cantidad; else carrito.push({id_producto:id,nombre:producto.nombre,precio:Number(producto.precio),cantidad});
    renderCarrito();
}
function renderCarrito() {
    const contenedor = document.getElementById("carrito");
    if (!carrito.length) { contenedor.textContent = "No hay productos agregados."; return; }
    contenedor.innerHTML = carrito.map(p => `<div class="linea-carrito"><span>${escapeHTML(p.nombre)}</span><input type="number" min="1" value="${p.cantidad}" aria-label="Cantidad de ${escapeHTML(p.nombre)}" onchange="actualizarCantidad(${p.id_producto},this.value)"><strong>$ ${(p.precio * p.cantidad).toFixed(2)}</strong><button type="button" class="quitar-producto" aria-label="Quitar ${escapeHTML(p.nombre)}" onclick="quitarProducto(${p.id_producto})">Quitar</button><span><button type="button" aria-label="Restar ${escapeHTML(p.nombre)}" onclick="cambiarCantidad(${p.id_producto},-1)">-</button> <button type="button" aria-label="Sumar ${escapeHTML(p.nombre)}" onclick="cambiarCantidad(${p.id_producto},1)">+</button></span></div>`).join("") + `<p><strong>Total: $ ${carrito.reduce((total,p)=>total+p.precio*p.cantidad,0).toFixed(2)}</strong></p>`;
}
function cambiarCantidad(id, cambio) { const producto=carrito.find(p=>p.id_producto===id); if(!producto)return; actualizarCantidad(id,producto.cantidad+cambio); }
function actualizarCantidad(id, cantidad) { const valor=Number.parseInt(cantidad,10); if(!Number.isInteger(valor)||valor<=0)return; const producto=carrito.find(p=>p.id_producto===id); if(producto){producto.cantidad=valor;renderCarrito();} }
function quitarProducto(id) { carrito=carrito.filter(p=>p.id_producto!==id);renderCarrito(); }
function crearPedido(evento) {
    evento.preventDefault(); if (!cajaAbierta) { mostrarMensaje("No se puede crear el pedido: la caja está cerrada.", true); return; } if (!document.getElementById("idMesa").value) { mostrarMensaje("Selecciona una mesa antes de continuar.", true); return; } if (!carrito.length) { mostrarMensaje("Agrega al menos un producto.", true); return; }
    const idPedidoActivo=Number(document.getElementById("idPedidoActivo").value)||0;
    const forzarStock=document.getElementById("forzarStock").checked;
    if(forzarStock&&!confirm("El inventario puede quedar negativo para los insumos faltantes. ¿Continuar de todos modos?"))return;
    const datos = {id_mesa:Number(document.getElementById("idMesa").value),id_cliente:document.getElementById("idCliente").value ? Number(document.getElementById("idCliente").value) : null,productos:carrito.map(p => ({id_producto:p.id_producto,cantidad:p.cantidad})),forzar_stock:forzarStock};
    if(pedidoEnEdicion){datos.accion="editar";datos.id_pedido=pedidoEnEdicion;} else if(idPedidoActivo){datos.accion="agregar_productos";datos.id_pedido=idPedidoActivo;}
    fetch("apis/api_pedidos.php", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(datos)}).then(r => r.json().then(d => ({ok:r.ok,data:d}))).then(r => { if (!r.ok) throw new Error(r.data.error || "No se pudo guardar el pedido."); const mensaje=pedidoEnEdicion?`Pedido #${r.data.id_pedido} actualizado.`:idPedidoActivo?`Productos agregados al pedido #${r.data.id_pedido}.`:`Pedido #${r.data.id_pedido} enviado a cocina.`; carrito=[]; pedidoEnEdicion=0;document.getElementById("forzarStock").checked=false;renderCarrito(); document.getElementById("idMesa").value=""; document.getElementById("idPedidoActivo").value=""; document.getElementById("mesaSeleccionada").textContent="Ninguna mesa seleccionada."; document.querySelector("#formPedido button[type=submit]").textContent="Crear pedido"; cargarMapaPedido(); mostrarMensaje(mensaje, false); }).catch(e => { cargarMapaPedido(); mostrarMensaje(e.message, true); });
}
async function cargarEstadoCajaPedido() { const respuesta = await fetch("apis/api_pedidos.php?caja=estado"); const datos = await respuesta.json(); cajaAbierta = Boolean(respuesta.ok && datos.abierta); const aviso = document.getElementById("estadoCajaPedido"); aviso.textContent = cajaAbierta ? "Caja abierta. Se pueden crear pedidos." : "Caja cerrada. El menú está visible, pero se necesita abrir caja para agregar productos."; aviso.className = cajaAbierta ? "mensaje exito" : "advertencia-caja"; actualizarControlesPedido(); if (cajaAbierta) cargarMapaPedido(); }
function actualizarControlesPedido() { document.querySelectorAll(".boton-producto, #formPedido button[type=submit]").forEach(boton => { boton.disabled = !cajaAbierta; boton.classList.toggle("bloqueado", !cajaAbierta); }); }
function mostrarMensaje(texto, error) { const elemento=document.getElementById("mensajeCreacion"); elemento.textContent=texto; elemento.className=`mensaje ${error ? "error" : "exito"}`; }
function cargarPedidos(vista) {
    fetch(`apis/api_pedidos.php?vista=${vista}&limite=100`).then(r=>r.json()).then(pedidos => { if (vista === "cocina") renderCocina(pedidos); else renderPendientes(pedidos); }).catch(() => {});
}
function renderPendientes(pedidos) { pedidosPendientes=pedidos; document.getElementById("listaPendientes").innerHTML = pedidos.length ? pedidos.map(p => `<article class="pedido-panel"><h3>Pedido #${p.id_pedido} · $ ${Number(p.monto_total).toFixed(2)}</h3><p>Estado: ${escapeHTML(p.estado)} | Mesa: ${escapeHTML(p.mesa || "-")} | Cliente: ${escapeHTML(p.cliente || "Sin cliente")} | Creado: ${escapeHTML(p.fecha_hora_creacion)}</p><p>${p.productos.map(x => `${escapeHTML(x.nombre)} x ${x.cantidad}`).join(", ")}</p><div class="acciones-pedido">${String(p.estado).toLocaleLowerCase("es")==="en preparación"?`<button type="button" class="pedido-accion" onclick="editarPedido(${Number(p.id_pedido)})">Editar</button>`:""}<button type="button" class="pedido-accion cancelar-pedido" onclick="cancelarPedido(${Number(p.id_pedido)})">Cancelar</button></div></article>`).join("") : "No hay pedidos pendientes."; }
function editarPedido(id) { const pedido=pedidosPendientes.find(p=>Number(p.id_pedido)===id); if(!pedido)return; pedidoEnEdicion=id;carrito=pedido.productos.map(p=>({id_producto:Number(p.id_producto),nombre:p.nombre,precio:Number(p.precio),cantidad:Number(p.cantidad)})); document.getElementById("idMesa").value=pedido.id_mesa;document.getElementById("idPedidoActivo").value=id;document.getElementById("idCliente").value=pedido.id_cliente||"";document.getElementById("forzarStock").checked=false;document.getElementById("mesaSeleccionada").textContent=`Editando pedido #${id} de la mesa ${pedido.mesa}.`;document.querySelector("#formPedido button[type=submit]").textContent="Guardar cambios";document.querySelectorAll(".mesa-pedido").forEach(m=>m.classList.remove("seleccionada"));const mesa=mesasPedido.find(m=>Number(m.id_mesa)===Number(pedido.id_mesa));if(mesa){const boton=[...document.querySelectorAll(".mesa-pedido")].find(m=>m.textContent.includes(`Mesa ${mesa.numero}`));boton?.classList.add("seleccionada");}renderCarrito();activarVista("creacion"); }
async function cancelarPedido(id) { if(!confirm(`¿Cancelar el pedido #${id}? Esta acción conservará el registro, pero liberará la mesa.`))return;const respuesta=await fetch("apis/api_pedidos.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({accion:"cancelar",id_pedido:id})});const datos=await respuesta.json();if(!respuesta.ok){alert(datos.error||"No se pudo cancelar el pedido.");return;}cargarPedidos("pendientes");cargarMapaPedido(); }
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
