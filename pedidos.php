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
    <link rel="stylesheet" href="assets/css/pedidos.css">
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
                <h2>Menú rápido</h2><p id="estadoCajaPedido" class="advertencia-caja">Comprobando sesión de caja...</p><p id="mensajeCreacion" class="mensaje"></p><div id="selectorMesa"><h3>Selecciona una mesa libre</h3><div id="mapaPedido" class="mapa-pedido"></div></div><input id="idMesa" type="hidden" required><p id="mesaSeleccionada">Ninguna mesa seleccionada.</p><button id="editarMesa" class="boton-editar-mesa" type="button" hidden>Editar mesa</button>
                <div id="menuProductos"></div>
                <div class="pedido-panel"><h3>Pedido actual</h3><div id="carrito" class="carrito">No hay productos agregados.</div><form id="formPedido" class="pedido-form"><label>Cliente<div class="cliente-selector"><input id="buscarCliente" type="search" autocomplete="off" placeholder="Buscar por nombre o apellido" aria-controls="resultadosClientes"><input id="idCliente" type="hidden" value="1"><div id="resultadosClientes" class="cliente-resultados" role="listbox" hidden></div></div><small id="clienteElegido" class="cliente-elegido">Cliente genérico (#1)</small></label><label>Detalle del pedido (opcional)<textarea id="descripcionPedido" maxlength="500" placeholder="Indicaciones o detalles del pedido"></textarea></label><button type="submit">Crear pedido</button></form></div>
            </section>
            <section id="vista-pendientes" class="pedido-view"><h2>Pedidos pendientes</h2><div class="cocina-toolbar"><button id="activarSonidoPendientes" class="boton-sonido" type="button">🔔 Activar sonido</button></div><div id="listaPendientes"></div></section>
            <?php endif; ?>
            <?php if ($puedeCocina): ?><section id="vista-cocina" class="pedido-view"><h2>Cocina</h2><p>Las bebidas se muestran al mozo en pedidos pendientes.</p><div class="cocina-toolbar"><button id="activarSonidoCocina" class="boton-sonido" type="button">🔔 Activar sonido</button></div><p id="mensajeBoleta" class="mensaje" role="status" aria-live="polite"></p><div id="listaCocina"></div></section><?php endif; ?>
        </main>
    </div>
    <div id="notificacionPedido" class="notificacion-pedido" role="alert" aria-live="assertive" hidden></div>
    <?php if ($puedeCrear): ?>
    <dialog id="dialogoExtras" class="extras-dialog"><h2>Agregar productos al pedido</h2><p id="tituloExtras"></p><input id="buscarExtra" type="search" placeholder="Filtrar productos"><div id="menuExtras"></div><p id="mensajeExtras" class="mensaje" role="status"></p><div class="extras-actions"><button id="cerrarExtras" class="secondary" type="button">Cancelar</button><button id="guardarExtras" type="button">Agregar al pedido</button></div></dialog>
    <?php endif; ?>
<script>
const puedeCrear = <?php echo $puedeCrear ? "true" : "false"; ?>;
const puedeCocina = <?php echo $puedeCocina ? "true" : "false"; ?>;
let carrito = [];
let contextoAudio = null;
let cajaAbierta = false;
let firmaMapaPedido = "";
let cargandoMapaPedido = false;
let productosDisponibles = [];
let pedidoExtrasActual = null;
let cantidadesExtras = {};
let temporizadorBusquedaCliente = null;
let solicitudClientes = 0;
let estadosPendientesAnteriores = null;
let productosCocinaAnteriores = null;
let temporizadorNotificacion = null;

document.addEventListener("DOMContentLoaded", () => {
    document.addEventListener("click", desbloquearSonido, { once: true, capture: true });
    document.querySelectorAll(".pedido-tabs button").forEach(boton => boton.addEventListener("click", () => activarVista(boton.dataset.vista)));
    const inicial = puedeCrear ? "creacion" : "cocina";
    activarVista(inicial);
    if (puedeCrear) {
        cargarEstadoCajaPedido();
        cargarMapaPedido();
        document.getElementById("editarMesa").addEventListener("click", editarMesa);
        document.getElementById("formPedido").addEventListener("submit", crearPedido);
        document.getElementById("buscarCliente").addEventListener("input", buscarClientes);
        document.getElementById("resultadosClientes").addEventListener("click", seleccionarCliente);
        document.getElementById("buscarExtra").addEventListener("input", renderMenuExtras);
        document.getElementById("menuExtras").addEventListener("click", cambiarCantidadExtra);
        document.getElementById("cerrarExtras").addEventListener("click", () => document.getElementById("dialogoExtras").close());
        document.getElementById("guardarExtras").addEventListener("click", guardarProductosExtras);
        setInterval(() => {
            if (document.visibilityState === "visible") cargarMapaPedido();
        }, 4000);
    }
    ["activarSonidoCocina", "activarSonidoPendientes"].forEach(id => {
        const boton = document.getElementById(id);
        if (boton) boton.addEventListener("click", activarSonido);
    });
});
function activarVista(vista) {
    document.querySelectorAll(".pedido-view").forEach(seccion => seccion.classList.remove("active"));
    document.querySelectorAll(".pedido-tabs button").forEach(boton => boton.classList.toggle("active", boton.dataset.vista === vista));
    const seccion = document.getElementById(`vista-${vista}`); if (seccion) seccion.classList.add("active");
    if (vista === "pendientes") cargarPedidos("pendientes");
    if (vista === "cocina") { cargarPedidos("cocina"); setTimeout(() => cargarPedidos("cocina"), 300); }
}
async function cargarMenu() {
    try {
        const respuesta = await fetch("apis/api_productos.php?limite=sin&campo=todos&filtro=");
        const productos = await respuesta.json();
        if (!respuesta.ok || !Array.isArray(productos)) throw new Error("No se pudo cargar el menú.");
        productosDisponibles = productos;
        const menu = document.getElementById("menuProductos");
        menu.innerHTML = renderProductosAgrupados(productos, producto => {
            const cantidad = carrito.find(linea => linea.id_producto === Number(producto.id_producto))?.cantidad || 0;
            return `<article class="producto-card"><h4>${escapeHTML(producto.nombre)}</h4><p>$ ${escapeHTML(producto.precio)}</p><div class="controles-cantidad"><button type="button" class="boton-producto" data-cambio="-1" data-id="${Number(producto.id_producto)}" aria-label="Quitar ${escapeHTML(producto.nombre)}">−</button><span data-cantidad="${Number(producto.id_producto)}">${cantidad}</span><button type="button" class="boton-producto" data-cambio="1" data-id="${Number(producto.id_producto)}" aria-label="Agregar ${escapeHTML(producto.nombre)}">+</button></div></article>`;
        });
        menu.querySelectorAll(".boton-producto").forEach(boton => boton.addEventListener("click", () => {
            const producto = productosDisponibles.find(p => Number(p.id_producto) === Number(boton.dataset.id));
            if (producto) cambiarCantidad(producto, Number(boton.dataset.cambio));
        }));
        aplicarEstadoBotonesMenu();
    } catch (error) {
        console.error(error);
        document.getElementById("menuProductos").textContent = error.message || "No se pudo cargar el menú.";
    }
}
function renderProductosAgrupados(productos, renderProducto) {
    const ordenCategorias = { bebidas: 0, postres: 1, parrilla: 2, comidas: 2 };
    const categorias = new Map();
    productos.forEach(producto => {
        const categoria = producto.categoria || "Sin categoría";
        if (!categorias.has(categoria)) categorias.set(categoria, []);
        categorias.get(categoria).push(producto);
    });
    const nombresCategorias = [...categorias.keys()].sort((a, b) => {
        const ordenA = ordenCategorias[a.toLocaleLowerCase()];
        const ordenB = ordenCategorias[b.toLocaleLowerCase()];
        if (ordenA !== undefined || ordenB !== undefined) return (ordenA === undefined ? Infinity : ordenA) - (ordenB === undefined ? Infinity : ordenB);
        return a.localeCompare(b, "es");
    });
    return nombresCategorias.map(categoria => {
        const titulo = categoria.toLocaleLowerCase() === "comidas" ? "Parrilla" : categoria;
        return `<section class="categoria-menu"><h3>${escapeHTML(titulo)}</h3><div class="menu-grid">${categorias.get(categoria).map(renderProducto).join("")}</div></section>`;
    }).join("");
}
function buscarClientes() {
    const consulta = document.getElementById("buscarCliente").value.trim();
    const resultados = document.getElementById("resultadosClientes");
    window.clearTimeout(temporizadorBusquedaCliente);
    const numeroSolicitud = ++solicitudClientes;
    if (consulta.length === 0) {
        document.getElementById("idCliente").value = "1";
        document.getElementById("clienteElegido").textContent = "Cliente genérico (#1)";
        resultados.hidden = true;
        resultados.innerHTML = "";
        return;
    }
    document.getElementById("idCliente").value = "";
    document.getElementById("clienteElegido").textContent = "Selecciona un cliente de los resultados.";
    if (consulta.length < 2) {
        resultados.hidden = true;
        resultados.innerHTML = "";
        return;
    }
    temporizadorBusquedaCliente = window.setTimeout(async () => {
        try {
            const respuesta = await fetch(`apis/api_clientes.php?campo=nombre_apellido&limite=30&filtro=${encodeURIComponent(consulta)}`);
            const clientes = await respuesta.json();
            if (!respuesta.ok) throw new Error(clientes.error || `No se pudieron buscar clientes (${respuesta.status}).`);
            if (numeroSolicitud !== solicitudClientes) return;
            resultados.innerHTML = Array.isArray(clientes) && clientes.length
                ? clientes.map(cliente => `<button type="button" role="option" data-cliente="${Number(cliente.id_cliente)}" data-nombre="${escapeHTML(`${cliente.nombre} ${cliente.apellido}`)}">#${Number(cliente.id_cliente)} · ${escapeHTML(cliente.nombre)} ${escapeHTML(cliente.apellido)}</button>`).join("")
                : '<p class="mensaje">No se encontraron clientes.</p>';
            resultados.hidden = false;
        } catch (error) {
            if (numeroSolicitud !== solicitudClientes) return;
            resultados.innerHTML = `<p class="mensaje error">${escapeHTML(error.message)}</p>`;
            resultados.hidden = false;
        }
    }, 250);
}
function seleccionarCliente(evento) {
    const opcion = evento.target.closest("button[data-cliente]");
    if (!opcion) return;
    document.getElementById("idCliente").value = opcion.dataset.cliente;
    document.getElementById("clienteElegido").textContent = `Cliente seleccionado: ${opcion.dataset.nombre}.`;
    document.getElementById("buscarCliente").value = "";
    const resultados = document.getElementById("resultadosClientes");
    resultados.hidden = true;
    resultados.innerHTML = "";
}
async function abrirExtras(idPedido) {
    pedidoExtrasActual = Number(idPedido);
    cantidadesExtras = {};
    document.getElementById("tituloExtras").textContent = `Pedido #${pedidoExtrasActual}`;
    document.getElementById("buscarExtra").value = "";
    document.getElementById("mensajeExtras").textContent = "";
    try {
        if (!productosDisponibles.length) await cargarMenu();
        if (!productosDisponibles.length) throw new Error("No se pudieron cargar los productos.");
        renderMenuExtras();
        document.getElementById("dialogoExtras").showModal();
    } catch (error) {
        mostrarNotificacion(error.message, true);
    }
}
function renderMenuExtras() {
    if (!productosDisponibles.length) return;
    const filtro = (document.getElementById("buscarExtra").value || "").trim().toLocaleLowerCase();
    const productos = productosDisponibles.filter(producto => `${producto.nombre} ${producto.categoria || ""}`.toLocaleLowerCase().includes(filtro));
    const menu = document.getElementById("menuExtras");
    menu.innerHTML = productos.length ? renderProductosAgrupados(productos, producto => {
        const id = Number(producto.id_producto);
        const cantidad = Number(cantidadesExtras[id] || 0);
        return `<article class="producto-extra"><span>${escapeHTML(producto.nombre)} · $ ${escapeHTML(producto.precio)}</span><div class="controles-cantidad"><button type="button" data-extra-cambio="-1" data-id="${id}" aria-label="Quitar ${escapeHTML(producto.nombre)}">−</button><span>${cantidad}</span><button type="button" data-extra-cambio="1" data-id="${id}" aria-label="Agregar ${escapeHTML(producto.nombre)}">+</button></div></article>`;
    }) : "<p>No se encontraron productos.</p>";
}
function cambiarCantidadExtra(evento) {
    const boton = evento.target.closest("button[data-extra-cambio]");
    if (!boton) return;
    const id = Number(boton.dataset.id);
    cantidadesExtras[id] = Math.max(0, Number(cantidadesExtras[id] || 0) + Number(boton.dataset.extraCambio));
    renderMenuExtras();
}
async function guardarProductosExtras() {
    const productos = Object.entries(cantidadesExtras)
        .filter(([, cantidad]) => cantidad > 0)
        .map(([id_producto, cantidad]) => ({ id_producto: Number(id_producto), cantidad }));
    if (!productos.length) {
        document.getElementById("mensajeExtras").textContent = "Agrega al menos un producto.";
        return;
    }
    const boton = document.getElementById("guardarExtras");
    boton.disabled = true;
    try {
        let respuesta = await enviarProductosExtras(productos, false);
        if (respuesta.status === 409 && respuesta.datos.faltantes && window.confirm(`${respuesta.datos.error}\n¿Quieres continuar igualmente?`)) {
            respuesta = await enviarProductosExtras(productos, true);
        }
        if (!respuesta.ok) throw new Error(respuesta.datos.error || "No se pudieron agregar los productos.");
        document.getElementById("dialogoExtras").close();
        mostrarNotificacion(`Se agregaron productos al pedido #${pedidoExtrasActual}.`);
        await cargarPedidos("pendientes");
    } catch (error) {
        document.getElementById("mensajeExtras").textContent = error.message;
        document.getElementById("mensajeExtras").className = "mensaje error";
    } finally {
        boton.disabled = false;
    }
}
async function enviarProductosExtras(productos, forzarStock) {
    const respuesta = await fetch("apis/api_pedidos.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ accion: "agregar_productos", id_pedido: pedidoExtrasActual, productos, forzar_stock: forzarStock })
    });
    const datos = await respuesta.json();
    return { ok: respuesta.ok, status: respuesta.status, datos };
}
async function cargarMapaPedido() {
    if (cargandoMapaPedido) return;
    cargandoMapaPedido = true;

    try {
        const respuesta = await fetch("apis/api_mesas.php", { cache: "no-store" });
        const datos = await respuesta.json();
        if (!respuesta.ok) return;

        const firma = JSON.stringify(datos.mesas || []);
        if (firma === firmaMapaPedido) return;

        const mapa = document.getElementById("mapaPedido");
        const idSeleccionado = document.getElementById("idMesa").value;
        const mesas = datos.mesas || [];
        const seleccionada = mesas.find((mesa) => String(mesa.id_mesa) === idSeleccionado && String(mesa.estado).toLowerCase() === "libre");
        if (idSeleccionado && !seleccionada) {
            document.getElementById("idMesa").value = "";
            document.getElementById("mesaSeleccionada").textContent = "Ninguna mesa seleccionada.";
            document.getElementById("selectorMesa").hidden = false;
            document.getElementById("editarMesa").hidden = true;
        }

        mapa.innerHTML = "";
        mesas.forEach((mesa) => {
            const estado = (mesa.estado || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = `mesa-pedido ${estado}`;
            boton.style.left = `${Number(mesa.pos_x || 0) / 1200 * 100}%`;
            boton.style.top = `${Number(mesa.pos_y || 0) / 700 * 100}%`;
            boton.style.width = `${Number(mesa.ancho || 100) / 1200 * 100}%`;
            boton.innerHTML = `<strong>Mesa ${escapeHTML(mesa.numero)}</strong><small>${escapeHTML(mesa.estado)}</small>`;
            boton.disabled = estado !== "libre";
            if (estado === "libre") boton.addEventListener("click", () => seleccionarMesa(mesa, boton));
            if (String(mesa.id_mesa) === idSeleccionado) boton.classList.add("seleccionada");
            mapa.appendChild(boton);
        });
        firmaMapaPedido = firma;
    } catch (error) {
        console.error(error);
    } finally {
        cargandoMapaPedido = false;
    }
}
function seleccionarMesa(mesa, elemento) {
    document.querySelectorAll(".mesa-pedido").forEach(m => m.classList.remove("seleccionada"));
    elemento.classList.add("seleccionada");
    document.getElementById("idMesa").value = mesa.id_mesa;
    document.getElementById("mesaSeleccionada").textContent = `Mesa ${mesa.numero} seleccionada.`;
    document.getElementById("selectorMesa").hidden = true;
    document.getElementById("editarMesa").hidden = false;
}
function editarMesa() {
    document.getElementById("selectorMesa").hidden = false;
    document.getElementById("editarMesa").hidden = true;
}
function cambiarCantidad(producto, cambio) {
    const id = Number(producto.id_producto);
    const existente = carrito.find(p => p.id_producto === id);
    if (!existente && cambio > 0) carrito.push({id_producto:id,nombre:producto.nombre,precio:Number(producto.precio),cantidad:1});
    else if (existente) {
        existente.cantidad += cambio;
        if (existente.cantidad <= 0) carrito = carrito.filter(p => p.id_producto !== id);
    }
    renderCarrito();
}
function renderCarrito() {
    const contenedor = document.getElementById("carrito");
    actualizarContadoresMenu();
    if (!carrito.length) { contenedor.textContent = "No hay productos agregados."; return; }
    contenedor.innerHTML = carrito.map(p => `<div class="linea-carrito"><span>${escapeHTML(p.nombre)}</span><div class="controles-cantidad"><button type="button" class="boton-carrito" data-id="${p.id_producto}" data-cambio="-1" aria-label="Quitar uno de ${escapeHTML(p.nombre)}">−</button><span>${p.cantidad}</span><button type="button" class="boton-carrito" data-id="${p.id_producto}" data-cambio="1" aria-label="Agregar uno de ${escapeHTML(p.nombre)}">+</button></div><strong>$ ${(p.precio * p.cantidad).toFixed(2)}</strong></div>`).join("");
    contenedor.querySelectorAll(".boton-carrito").forEach(boton => boton.addEventListener("click", () => {
        const producto = carrito.find(p => p.id_producto === Number(boton.dataset.id));
        if (producto) cambiarCantidad(producto, Number(boton.dataset.cambio));
    }));
}
function actualizarContadoresMenu() {
    document.querySelectorAll("#menuProductos [data-cantidad]").forEach(contador => {
        const producto = carrito.find(linea => linea.id_producto === Number(contador.dataset.cantidad));
        contador.textContent = producto ? producto.cantidad : "0";
    });
    aplicarEstadoBotonesMenu();
}
function aplicarEstadoBotonesMenu() {
    document.querySelectorAll("#menuProductos .boton-producto").forEach(boton => {
        const contador = document.querySelector(`#menuProductos [data-cantidad="${boton.dataset.id}"]`);
        const cantidad = Number(contador?.textContent || 0);
        const bloqueado = !cajaAbierta || (Number(boton.dataset.cambio) < 0 && cantidad === 0);
        boton.disabled = bloqueado;
        boton.classList.toggle("bloqueado", !cajaAbierta);
    });
}
function crearPedido(evento) {
    evento.preventDefault(); if (!cajaAbierta) { mostrarMensaje("No se puede crear el pedido: la caja está cerrada.", true); return; } if (!document.getElementById("idMesa").value) { mostrarMensaje("Selecciona una mesa libre antes de crear el pedido.", true); return; } if (!carrito.length) { mostrarMensaje("Agrega al menos un producto.", true); return; } if (!document.getElementById("idCliente").value) { mostrarMensaje("Selecciona un cliente de los resultados.", true); return; }
    const datos = {id_mesa:Number(document.getElementById("idMesa").value),id_cliente:document.getElementById("idCliente").value ? Number(document.getElementById("idCliente").value) : null,descripcion:document.getElementById("descripcionPedido").value.trim(),productos:carrito.map(p => ({id_producto:p.id_producto,cantidad:p.cantidad}))};
    fetch("apis/api_pedidos.php", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(datos)}).then(r => r.json().then(d => ({ok:r.ok,data:d,status:r.status}))).then(r => { if (!r.ok) throw new Error(r.data.error || `No se pudo crear el pedido (${r.status}).`); carrito=[]; renderCarrito(); document.getElementById("idMesa").value=""; document.getElementById("mesaSeleccionada").textContent="Ninguna mesa seleccionada."; document.getElementById("descripcionPedido").value=""; document.getElementById("selectorMesa").hidden=false; document.getElementById("editarMesa").hidden=true; document.getElementById("idCliente").value="1"; document.getElementById("buscarCliente").value=""; document.getElementById("clienteElegido").textContent="Cliente genérico (#1)"; cargarMapaPedido(); mostrarMensaje(`Pedido #${r.data.id_pedido} enviado a cocina.`, false); }).catch(e => { cargarMapaPedido(); mostrarMensaje(e.message, true); });
}
async function cargarEstadoCajaPedido() {
    try {
        const respuesta = await fetch("apis/api_pedidos.php?caja=estado");
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo consultar la caja.");
        cajaAbierta = Boolean(datos.abierta);
        const aviso = document.getElementById("estadoCajaPedido");
        aviso.textContent = cajaAbierta ? "Caja abierta. Se pueden crear pedidos." : "Caja cerrada. Abre una sesión de caja para crear pedidos.";
        aviso.className = cajaAbierta ? "mensaje exito" : "advertencia-caja";
        document.querySelectorAll("#formPedido button[type=submit]").forEach(boton => {
            boton.disabled = !cajaAbierta;
            boton.classList.toggle("bloqueado", !cajaAbierta);
        });
        if (cajaAbierta && !productosDisponibles.length) await cargarMenu();
        else aplicarEstadoBotonesMenu();
        if (cajaAbierta) cargarMapaPedido();
    } catch (error) {
        mostrarMensaje(error.message, true);
    }
}
function mostrarMensaje(texto, error) { const elemento=document.getElementById("mensajeCreacion"); elemento.textContent=texto; elemento.className=`mensaje ${error ? "error" : "exito"}`; }
function cargarPedidos(vista) {
    return fetch(`apis/api_pedidos.php?vista=${vista}&limite=100`).then(async respuesta => {
        const pedidos = await respuesta.json();
        if (!respuesta.ok) throw new Error(pedidos.error || `No se pudieron cargar los pedidos (${respuesta.status}).`);
        if (!Array.isArray(pedidos)) throw new Error("La respuesta de pedidos no tiene el formato esperado.");
        if (vista === "cocina") renderCocina(pedidos);
        else renderPendientes(pedidos);
    }).catch(error => {
        console.error(error);
        const lista = document.getElementById(vista === "cocina" ? "listaCocina" : "listaPendientes");
        if (lista && !lista.children.length) lista.textContent = error.message || "No se pudieron cargar los pedidos.";
    });
}
function renderPendientes(pedidos) {
    if (estadosPendientesAnteriores) {
        pedidos.forEach(pedido => {
            const anterior = estadosPendientesAnteriores.get(Number(pedido.id_pedido));
            if (anterior === "en preparación" && String(pedido.estado).toLowerCase() === "comiendo") {
                mostrarNotificacion(`Pedido #${pedido.id_pedido} está pronto para el mozo.`);
                emitirAlerta();
            }
        });
    }
    estadosPendientesAnteriores = new Map(pedidos.map(p => [Number(p.id_pedido), String(p.estado).toLowerCase()]));
    const contenedor = document.getElementById("listaPendientes");
    contenedor.innerHTML = pedidos.length ? pedidos.map(p => {
        const productos = p.productos.map(x => `<li>${escapeHTML(x.nombre)} × ${Number(x.cantidad)}</li>`).join("");
        const descripcion = p.descripcion ? `<p>Detalle: ${escapeHTML(p.descripcion)}</p>` : "";
        const estado = String(p.estado).toLowerCase();
        const acciones = `<div class="pedido-pendiente-actions">${estado !== "listo" ? `<button type="button" class="boton-accion-pedido" data-agregar="${Number(p.id_pedido)}">Agregar productos</button>` : ""}${estado === "comiendo" ? `<button type="button" class="boton-accion-pedido" data-cerrar="${Number(p.id_pedido)}">Cerrar pedido</button>` : ""}</div>`;
        return `<article class="pedido-panel"><h3>Pedido #${Number(p.id_pedido)} <span class="estado-pedido">${escapeHTML(p.estado)}</span></h3><p>Mesa: ${escapeHTML(p.mesa || "-")} | Creado: ${escapeHTML(p.fecha_hora_creacion)}</p>${descripcion}<ul>${productos}</ul>${acciones}</article>`;
    }).join("") : "No hay pedidos pendientes.";
}
function manejarAccionesPendientes(evento) {
    const botonAgregar = evento.target.closest("button[data-agregar]");
    if (botonAgregar) {
        abrirExtras(Number(botonAgregar.dataset.agregar));
        return;
    }
    const botonCerrar = evento.target.closest("button[data-cerrar]");
    if (botonCerrar) cerrarPedido(Number(botonCerrar.dataset.cerrar));
}
async function cerrarPedido(idPedido) {
    try {
        const respuesta = await fetch("apis/api_pedidos.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ accion: "cerrar_pedido", id_pedido: idPedido })
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo cerrar el pedido.");
        mostrarNotificacion(`Pedido #${idPedido} cerrado. La mesa quedó pendiente de limpieza.`);
        await cargarPedidos("pendientes");
    } catch (error) {
        mostrarNotificacion(error.message, true);
    }
}
function renderCocina(pedidos) {
    const anterior = productosCocinaAnteriores;
    const actual = new Map(pedidos.map(p => [Number(p.id_pedido), new Map(p.productos.map(x => [Number(x.id_producto), Number(x.cantidad)]))]));
    if (anterior) {
        for (const pedido of pedidos) {
            const idPedido = Number(pedido.id_pedido);
            if (!anterior.has(idPedido)) {
                mostrarNotificacion(`Nuevo pedido #${idPedido} recibido en cocina.`);
                emitirAlerta();
                break;
            }
            const cantidadesPrevias = anterior.get(idPedido);
            const agregado = pedido.productos.find(x => Number(x.cantidad) > Number(cantidadesPrevias.get(Number(x.id_producto)) || 0));
            if (agregado) {
                mostrarNotificacion(`Se agregó ${agregado.nombre} al pedido #${idPedido}.`);
                emitirAlerta();
                break;
            }
        }
    }
    productosCocinaAnteriores = actual;
    const contenedor = document.getElementById("listaCocina");
    contenedor.innerHTML = pedidos.length ? pedidos.map(p => {
        const completos = p.productos.every(x => Number(x.completado) === 1);
        const productos = p.productos.map(x => `<button type="button" class="producto-cocina ${Number(x.completado) ? "completo" : ""}" data-completar="${Number(p.id_pedido)}" data-producto="${Number(x.id_producto)}" ${Number(x.completado) ? "disabled" : ""}><span>${escapeHTML(x.nombre)} × ${Number(x.cantidad)}</span><strong>${Number(x.completado) ? "Completo" : "Tocar para completar"}</strong></button>`).join("");
        return `<article class="cocina-pedido"><h3>Pedido #${Number(p.id_pedido)}</h3><p>Mesa: ${escapeHTML(p.mesa || "-")} | <span class="reloj" data-inicio="${escapeHTML(p.fecha_hora_creacion)}"></span></p>${productos}<button type="button" class="boton-accion-pedido" data-listo="${Number(p.id_pedido)}" ${completos ? "" : "disabled"}>Marcar como listo</button></article>`;
    }).join("") : "No hay pedidos en cocina.";
    actualizarRelojes();
}
function manejarAccionesCocina(evento) {
    const producto = evento.target.closest("button[data-completar]");
    if (producto) {
        completarProducto(Number(producto.dataset.completar), Number(producto.dataset.producto));
        return;
    }
    const listo = evento.target.closest("button[data-listo]");
    if (listo) marcarPedidoListo(Number(listo.dataset.listo));
}
async function completarProducto(idPedido, idProducto) {
    try {
        const respuesta = await fetch("apis/api_pedidos.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ accion: "completar_producto", id_pedido: idPedido, id_producto: idProducto })
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo completar el producto.");
        cargarPedidos("cocina");
    } catch (error) {
        mostrarNotificacion(error.message, true);
    }
}
async function marcarPedidoListo(idPedido) {
    try {
        const respuesta = await fetch("apis/api_pedidos.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ accion: "finalizar", id_pedido: idPedido })
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo marcar el pedido como listo.");
        mostrarNotificacion(`¡Tin tin! El pedido #${idPedido} está pronto para el mozo.`);
        emitirAlerta();
        cargarPedidos("cocina");
    } catch (error) {
        mostrarNotificacion(error.message, true);
    }
}
function actualizarRelojes() { document.querySelectorAll(".reloj").forEach(el => { const minutos=Math.floor((Date.now()-new Date(el.dataset.inicio).getTime())/60000); const segundos=Math.max(0,Math.floor((Date.now()-new Date(el.dataset.inicio).getTime())/1000)); el.textContent=`Tiempo: ${String(Math.floor(segundos/60)).padStart(2,"0")}:${String(segundos%60).padStart(2,"0")}`; el.className=`reloj ${minutos>=30?"rojo":minutos>=20?"naranja":minutos>=10?"amarillo":""}`; }); }
setInterval(() => { actualizarRelojes(); if (puedeCrear) cargarEstadoCajaPedido(); if (puedeCocina && document.getElementById("vista-cocina")?.classList.contains("active")) cargarPedidos("cocina"); if (puedeCrear && document.visibilityState === "visible") cargarPedidos("pendientes"); }, 5000);
function mostrarNotificacion(texto, error = false) {
    const alerta = document.getElementById("notificacionPedido");
    alerta.textContent = texto;
    alerta.className = `notificacion-pedido${error ? " error" : ""}`;
    alerta.hidden = false;
    window.clearTimeout(temporizadorNotificacion);
    temporizadorNotificacion = window.setTimeout(() => { alerta.hidden = true; }, 7000);
}
async function activarSonido() {
    try {
        const AudioContexto = window.AudioContext || window.webkitAudioContext;
        if (!AudioContexto) throw new Error("Este navegador no admite alertas de sonido.");
        contextoAudio ||= new AudioContexto();
        await contextoAudio.resume();
        document.querySelectorAll(".boton-sonido").forEach(boton => { boton.textContent = "🔔 Sonido activado"; });
        emitirAlerta();
        mostrarNotificacion("Alertas de sonido activadas.");
    } catch (error) {
        console.error(error);
        mostrarNotificacion(error.message, true);
    }
}
function desbloquearSonido() {
    const AudioContexto = window.AudioContext || window.webkitAudioContext;
    if (!AudioContexto) return;
    contextoAudio ||= new AudioContexto();
    contextoAudio.resume().catch(error => console.warn("No se pudo habilitar el sonido de pedidos.", error));
}
function emitirAlerta() {
    if (!contextoAudio || contextoAudio.state !== "running") return;
    const ahora = contextoAudio.currentTime;
    [988, 1318].forEach((frecuencia, indice) => {
        const oscilador = contextoAudio.createOscillator();
        const ganancia = contextoAudio.createGain();
        const inicio = ahora + indice * 0.22;
        oscilador.type = "sine";
        oscilador.frequency.value = frecuencia;
        ganancia.gain.setValueAtTime(0.001, inicio);
        ganancia.gain.exponentialRampToValueAtTime(0.18, inicio + 0.025);
        ganancia.gain.exponentialRampToValueAtTime(0.001, inicio + 0.28);
        oscilador.connect(ganancia);
        ganancia.connect(contextoAudio.destination);
        oscilador.start(inicio);
        oscilador.stop(inicio + 0.3);
    });
}
function escapeJS(valor) { return String(valor).replace(/\\/g,"\\\\").replace(/'/g,"\\'"); }
function escapeHTML(valor) { if (valor === null || valor === undefined) return ""; return String(valor).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;").replace(/'/g,"&#039;"); }
</script>
</body>
</html>
