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
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <script src="assets/js/sidebar.js" defer></script>
    <script src="assets/js/notificaciones.js" defer></script>
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
                <?php if ($puedeCrear): ?><button type="button" data-vista="limpieza">Mesas para limpiar</button><?php endif; ?>
                <?php if ($puedeCocina): ?><button type="button" data-vista="cocina">Cocina</button><?php endif; ?>
            </nav>
            <?php if ($puedeCrear): ?>
            <section id="vista-creacion" class="pedido-view">
                <h2>Menú rápido</h2><p id="estadoCajaPedido" class="advertencia-caja">Comprobando sesión de caja...</p><div id="mensajeCreacion" class="mensaje" role="status" aria-live="polite" hidden></div><div id="selectorMesa"><h3>Selecciona una mesa libre</h3><div id="mapaPedido" class="mapa-pedido"></div></div><input id="idMesa" type="hidden" required><p id="mesaSeleccionada">Ninguna mesa seleccionada.</p><button id="editarMesa" class="boton-editar-mesa" type="button" hidden>Editar mesa</button>
                <div class="filtro-productos-pedido"><label for="buscarProductoPedido">Buscar producto</label><input id="buscarProductoPedido" type="search" placeholder="Nombre o categoría" autocomplete="off"></div>
                <div id="menuProductos"></div>
                <div class="pedido-panel"><h3>Pedido actual</h3><div id="carrito" class="carrito">No hay productos agregados.</div><form id="formPedido" class="pedido-form"><label>Cliente<div class="cliente-selector"><input id="buscarCliente" type="search" autocomplete="off" placeholder="Buscar por nombre o apellido" aria-controls="resultadosClientes"><input id="idCliente" type="hidden" value="1"><div id="resultadosClientes" class="cliente-resultados" role="listbox" hidden></div></div><small id="clienteElegido" class="cliente-elegido">Cliente genérico (#1)</small></label><label>Detalle del pedido (opcional)<textarea id="descripcionPedido" maxlength="500" placeholder="Indicaciones o detalles del pedido"></textarea></label><label class="pedido-forzar-stock"><input id="forzarStockPedido" type="checkbox"><span>Forzar pedido aunque falte stock<small>El stock se descontará al completar cada producto o pickear cada bebida; puede quedar negativo.</small></span></label><button type="submit">Crear pedido</button></form></div>
            </section>
            <section id="vista-pendientes" class="pedido-view"><h2>Pedidos pendientes</h2><div id="listaPendientes"></div></section>
            <section id="vista-limpieza" class="pedido-view"><h2>Mesas para limpiar</h2><p id="mensajeLimpieza" class="mensaje" role="status" aria-live="polite">Cargando mesas...</p><div id="listaMesasLimpieza" class="lista-mesas-limpieza"></div></section>
            <?php endif; ?>
            <?php if ($puedeCocina): ?><section id="vista-cocina" class="pedido-view"><h2>Cocina</h2><p>Las bebidas se muestran al mozo en pedidos pendientes.</p><p id="mensajeBoleta" class="mensaje" role="status" aria-live="polite"></p><div id="listaCocina"></div></section><?php endif; ?>
        </main>
    </div>
    <div id="notificacionPedido" class="notificacion-pedido" role="alert" aria-live="assertive" hidden></div>
    <?php if ($puedeCrear): ?>
    <dialog id="dialogoExtras" class="extras-dialog" tabindex="-1">
        <header class="extras-header">
            <div><h2>Agregar productos</h2><p id="tituloExtras"></p></div>
            <button id="cerrarExtrasX" class="extras-cerrar" type="button" aria-label="Cerrar">&times;</button>
        </header>
        <div class="extras-busqueda"><input id="buscarExtra" type="search" placeholder="Filtrar productos" aria-label="Filtrar productos" autocomplete="off"></div>
        <div id="menuExtras" class="extras-lista"></div>
        <p id="mensajeExtras" class="mensaje" role="status"></p>
        <footer class="extras-actions"><span id="resumenExtras" class="extras-resumen">Sin productos seleccionados</span><button id="cerrarExtras" class="secondary" type="button">Cancelar</button><button id="guardarExtras" type="button">Agregar al pedido</button></footer>
    </dialog>
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
        document.getElementById("listaPendientes").addEventListener("click", manejarAccionesPendientes);
        document.getElementById("editarMesa").addEventListener("click", editarMesa);
        document.getElementById("listaMesasLimpieza").addEventListener("click", marcarMesaLimpiaPedidos);
        document.getElementById("formPedido").addEventListener("submit", crearPedido);
        document.getElementById("buscarCliente").addEventListener("input", buscarClientes);
        document.getElementById("resultadosClientes").addEventListener("click", seleccionarCliente);
        document.getElementById("buscarExtra").addEventListener("input", renderMenuExtras);
        document.getElementById("menuExtras").addEventListener("click", cambiarCantidadExtra);
        document.getElementById("menuExtras").addEventListener("keydown", evento => {
            if (
                (evento.key === "Enter" || evento.key === " ")
                && evento.target.matches(".producto-extra[data-extra-producto]")
            ) {
                evento.preventDefault();
                cambiarCantidadExtra(evento);
            }
        });
        document.getElementById("cerrarExtras").addEventListener("click", () => document.getElementById("dialogoExtras").close());
        document.getElementById("cerrarExtrasX").addEventListener("click", () => document.getElementById("dialogoExtras").close());
        document.getElementById("guardarExtras").addEventListener("click", guardarProductosExtras);
        setInterval(() => {
            if (document.visibilityState === "visible") cargarMapaPedido();
        }, 4000);
    }
    if (puedeCocina) {
        document.getElementById("listaCocina").addEventListener("click", manejarAccionesCocina);
    }
});
function activarVista(vista) {
    document.querySelectorAll(".pedido-view").forEach(seccion => seccion.classList.remove("active"));
    document.querySelectorAll(".pedido-tabs button").forEach(boton => boton.classList.toggle("active", boton.dataset.vista === vista));
    const seccion = document.getElementById(`vista-${vista}`); if (seccion) seccion.classList.add("active");
    if (vista === "pendientes") cargarPedidos("pendientes");
    if (vista === "limpieza" && puedeCrear) cargarMesasLimpieza();
    if (vista === "cocina") { cargarPedidos("cocina"); setTimeout(() => cargarPedidos("cocina"), 300); }
}
async function cargarMesasLimpieza() {
    const contenedor = document.getElementById("listaMesasLimpieza");
    const mensaje = document.getElementById("mensajeLimpieza");
    try {
        const respuesta = await fetch("apis/api_mesas.php", { cache: "no-store" });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudieron cargar las mesas.");
        mensaje.classList.remove("error");
        const mesas = (datos.mesas || []).filter(mesa => String(mesa.estado).toLowerCase() === "para limpiar");
        contenedor.innerHTML = mesas.length ? mesas.map(mesa => `
            <article class="mesa-pendiente-limpieza">
                <h3>Mesa ${escapeHTML(mesa.numero)}</h3>
                <button class="boton-accion-pedido" type="button" data-limpiar-mesa="${Number(mesa.id_mesa)}">Marcar limpia</button>
            </article>`).join("") : '<p class="mensaje">No hay mesas pendientes de limpieza.</p>';
        mensaje.textContent = mesas.length ? "Tocá «Marcar limpia» cuando termines de limpiar la mesa." : "";
    } catch (error) {
        mensaje.textContent = error.message;
        mensaje.classList.add("error");
    }
}
async function marcarMesaLimpiaPedidos(evento) {
    const boton = evento.target.closest("button[data-limpiar-mesa]");
    if (!boton) return;
    boton.disabled = true;
    try {
        const respuesta = await fetch("apis/api_mesas.php", {
            method: "PATCH",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ accion: "marcar_limpia", id_mesa: Number(boton.dataset.limpiarMesa) })
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo marcar la mesa como limpia.");
        mostrarNotificacion(`Mesa ${boton.closest(".mesa-pendiente-limpieza").querySelector("h3").textContent.replace("Mesa ", "")} marcada como limpia.`);
        await cargarMesasLimpieza();
        await cargarMapaPedido();
    } catch (error) {
        mostrarNotificacion(error.message, true);
        boton.disabled = false;
    }
}
async function cargarMenu() {
    try {
        const respuesta = await fetch("apis/api_productos.php?limite=sin&campo=todos&filtro=");
        const productos = await respuesta.json();
        if (!respuesta.ok || !Array.isArray(productos)) throw new Error("No se pudo cargar el menú.");
        productosDisponibles = productos;
        renderMenuPedido();
    } catch (error) {
        console.error(error);
        document.getElementById("menuProductos").textContent = error.message || "No se pudo cargar el menú.";
    }
}
function renderMenuPedido() {
    const consulta = (document.getElementById("buscarProductoPedido")?.value || "").trim().toLocaleLowerCase();
    const filtrados = consulta
        ? productosDisponibles.filter(producto => `${producto.nombre || ""} ${producto.categoria || ""}`.toLocaleLowerCase().includes(consulta))
        : productosDisponibles;
    const menu = document.getElementById("menuProductos");
    menu.innerHTML = filtrados.length
        ? renderProductosAgrupados(filtrados, producto => {
            const cantidad = carrito.find(linea => linea.id_producto === Number(producto.id_producto))?.cantidad || 0;
            const disponible = Number(producto.disponible) !== 0;
            return `<article class="producto-card ${disponible ? "" : "producto-sin-insumos"}" data-producto="${Number(producto.id_producto)}" role="button" tabindex="${disponible ? "0" : "-1"}" aria-label="Agregar ${escapeHTML(producto.nombre)}"><h4>${escapeHTML(producto.nombre)}</h4><p>$ ${escapeHTML(producto.precio)}${disponible ? "" : " · Insumos insuficientes"}</p><div class="controles-cantidad"><button type="button" class="boton-producto boton-restar" data-cambio="-1" data-id="${Number(producto.id_producto)}" aria-label="Quitar ${escapeHTML(producto.nombre)}" ${cantidad ? "" : "disabled"}>−</button><span data-cantidad="${Number(producto.id_producto)}">${cantidad}</span></div></article>`;
        })
        : '<p class="mensaje">No se encontraron productos.</p>';
    menu.querySelectorAll(".boton-restar").forEach(boton => boton.addEventListener("click", evento => {
        evento.stopPropagation();
        const producto = productosDisponibles.find(item => Number(item.id_producto) === Number(boton.dataset.id));
        if (producto) cambiarCantidad(producto, -1);
    }));
    menu.querySelectorAll(".producto-card[data-producto]").forEach(tarjeta => {
        const agregarDesdeTarjeta = evento => {
            if (evento.target.closest("button")) return;
            const producto = productosDisponibles.find(item => Number(item.id_producto) === Number(tarjeta.dataset.producto));
            if (!producto || Number(producto.disponible) === 0) return;
            cambiarCantidad(producto, 1);
        };
        tarjeta.addEventListener("click", agregarDesdeTarjeta);
        tarjeta.addEventListener("keydown", evento => {
            if (evento.key === "Enter" || evento.key === " ") { evento.preventDefault(); agregarDesdeTarjeta(evento); }
        });
    });
    aplicarEstadoBotonesMenu();
}
document.addEventListener("DOMContentLoaded", () => document.getElementById("buscarProductoPedido")?.addEventListener("input", renderMenuPedido));
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
        const dialogoExtras = document.getElementById("dialogoExtras");
        dialogoExtras.showModal();
        dialogoExtras.focus({ preventScroll: true });
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
        const botonRestar = cantidad > 0
            ? `<button type="button" data-extra-restar="${id}" aria-label="Quitar ${escapeHTML(producto.nombre)}">−</button>`
            : "";
        return `<article class="producto-extra${cantidad > 0 ? " elegido" : ""}" data-extra-producto="${id}" role="button" tabindex="0" aria-label="Agregar ${escapeHTML(producto.nombre)}"><div class="extra-nombre">${escapeHTML(producto.nombre)}</div><div class="extra-footer"><span class="extra-precio">$ ${escapeHTML(producto.precio)}</span><div class="controles-cantidad">${botonRestar}<span class="extra-cantidad">${cantidad > 0 ? cantidad : "+"}</span></div></div></article>`;
    }) : '<p class="extras-vacio">No se encontraron productos.</p>';
    const totalUnidades = Object.values(cantidadesExtras).reduce((suma, n) => suma + Number(n || 0), 0);
    document.getElementById("resumenExtras").textContent = totalUnidades
        ? ` ${totalUnidades === 1 ? "producto seleccionado" : "productos seleccionados"}`
        : "Sin productos seleccionados";
}
function cambiarCantidadExtra(evento) {
    const botonRestar = evento.target.closest("button[data-extra-restar]");
    const tarjeta = evento.target.closest(".producto-extra[data-extra-producto]");
    if (botonRestar) {
        const id = Number(botonRestar.dataset.extraRestar);
        cantidadesExtras[id] = Math.max(0, Number(cantidadesExtras[id] || 0) - 1);
    } else if (tarjeta) {
        const id = Number(tarjeta.dataset.extraProducto);
        cantidadesExtras[id] = Number(cantidadesExtras[id] || 0) + 1;
    } else {
        return;
    }
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
            boton.innerHTML = `<strong><span class="mesa-prefijo">Mesa </span>${escapeHTML(mesa.numero)}</strong><small>${escapeHTML(mesa.estado)}</small>`;
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
        const bloqueado = cantidad === 0;
        boton.disabled = bloqueado;
        boton.classList.toggle("bloqueado", bloqueado);
    });
    document.querySelectorAll("#menuProductos .producto-card").forEach(tarjeta => {
        tarjeta.classList.remove("bloqueado");
    });
}
async function crearPedido(evento) {
    evento.preventDefault();
    if (!cajaAbierta) { mostrarMensaje("No se puede crear el pedido: la caja está cerrada.", true); return; }
    if (!document.getElementById("idMesa").value) { mostrarMensaje("Selecciona una mesa libre antes de crear el pedido.", true); return; }
    if (!carrito.length) { mostrarMensaje("Agrega al menos un producto.", true); return; }
    if (!document.getElementById("idCliente").value) { mostrarMensaje("Selecciona un cliente de los resultados.", true); return; }

    const formulario = document.getElementById("formPedido");
    const boton = formulario.querySelector('button[type="submit"]');
    const datos = {
        id_mesa: Number(document.getElementById("idMesa").value),
        id_cliente: document.getElementById("idCliente").value ? Number(document.getElementById("idCliente").value) : null,
        descripcion: document.getElementById("descripcionPedido").value.trim(),
        productos: carrito.map(producto => ({ id_producto: producto.id_producto, cantidad: producto.cantidad })),
        forzar_stock: document.getElementById("forzarStockPedido").checked
    };

    boton.disabled = true;
    mostrarMensaje("", false);
    try {
        const respuesta = await fetch("apis/api_pedidos.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        });
        const resultado = await respuesta.json();
        if (!respuesta.ok) {
            if (respuesta.status === 409 && Array.isArray(resultado.faltantes) && resultado.faltantes.length) {
                mostrarFaltantesStock(resultado.error, resultado.faltantes);
                return;
            }
            throw new Error(resultado.error || `No se pudo crear el pedido (${respuesta.status}).`);
        }

        carrito = [];
        renderCarrito();
        document.getElementById("idMesa").value = "";
        document.getElementById("mesaSeleccionada").textContent = "Ninguna mesa seleccionada.";
        document.getElementById("descripcionPedido").value = "";
        document.getElementById("forzarStockPedido").checked = false;
        document.getElementById("selectorMesa").hidden = false;
        document.getElementById("editarMesa").hidden = true;
        document.getElementById("idCliente").value = "1";
        document.getElementById("buscarCliente").value = "";
        document.getElementById("clienteElegido").textContent = "Cliente genérico (#1)";
        cargarMapaPedido();
        mostrarMensaje(`Pedido #${resultado.id_pedido} enviado a cocina.`, false);
        window.AlertasJBM?.reproducir("pedido");
        window.AlertasJBM?.navegador("Pedido enviado", `El pedido #${resultado.id_pedido} fue enviado a cocina.`);
    } catch (error) {
        cargarMapaPedido();
        mostrarMensaje(error.message, true);
    } finally {
        boton.disabled = !cajaAbierta;
    }
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
        if (!productosDisponibles.length) await cargarMenu();
        else aplicarEstadoBotonesMenu();
        if (cajaAbierta) cargarMapaPedido();
    } catch (error) {
        mostrarMensaje(error.message, true);
    }
}
function mostrarMensaje(texto, error) {
    const elemento = document.getElementById("mensajeCreacion");
    elemento.textContent = texto;
    elemento.className = `mensaje ${error ? "error" : "exito"}`;
    elemento.setAttribute("role", error ? "alert" : "status");
    elemento.setAttribute("aria-live", error ? "assertive" : "polite");
    elemento.hidden = !texto;
}
function mostrarFaltantesStock(mensaje, faltantes) {
    const detalle = faltantes.map(insumo => {
        const disponible = Number(insumo.disponible).toLocaleString("es-UY");
        const requerido = Number(insumo.requerido).toLocaleString("es-UY");
        return `• ${insumo.nombre}: disponible ${disponible} ${insumo.unidad || ""}; se necesitan ${requerido} ${insumo.unidad || ""}.`;
    });
    mostrarMensaje(`${mensaje}\n${detalle.join("\n")}\nActiva «Forzar pedido aunque falte stock» y vuelve a intentarlo si autorizas continuar.`, true);
}
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
                emitirAlerta("listo");
                window.AlertasJBM?.navegador("Pedido listo para levantar", `El pedido #${pedido.id_pedido} está listo.`);
            }
        });
    }
    estadosPendientesAnteriores = new Map(pedidos.map(p => [Number(p.id_pedido), String(p.estado).toLowerCase()]));
    const contenedor = document.getElementById("listaPendientes");
    contenedor.innerHTML = pedidos.length ? pedidos.map(p => {
        const estado = String(p.estado).toLowerCase();
        const bebidasPendientes = p.productos.some(x => Number(x.es_bebida) === 1 && Number(x.completado) !== 1);
        const productos = p.productos.map(x => {
            if (Number(x.es_bebida) !== 1) return `<li>${escapeHTML(x.nombre)} × ${Number(x.cantidad)}</li>`;
            const pickeada = Number(x.completado) === 1;
            const control = ["en preparación", "comiendo"].includes(estado)
                ? `<button type="button" class="boton-accion-pedido boton-bebida-pickeada" data-pickear-bebida="${Number(p.id_pedido)}" data-producto="${Number(x.id_producto)}" ${pickeada ? "disabled" : ""}>${pickeada ? "Pickeada" : "Pickear bebida"}</button>`
                : `<span class="estado-bebida">${pickeada ? "Pickeada" : "Pendiente de retirar"}</span>`;
            return `<li class="pedido-bebida"><span>${escapeHTML(x.nombre)} × ${Number(x.cantidad)} · Bebida</span>${control}</li>`;
        }).join("");
        const descripcion = p.descripcion ? `<p>Detalle: ${escapeHTML(p.descripcion)}</p>` : "";
        const avisoBebidas = estado === "comiendo" && bebidasPendientes ? '<p class="aviso-bebidas">Marca todas las bebidas como pickeadas para poder cerrar el pedido.</p>' : "";
        const acciones = `<div class="pedido-pendiente-actions">${estado !== "listo" ? `<button type="button" class="boton-accion-pedido" data-agregar="${Number(p.id_pedido)}">Agregar productos</button>` : ""}${estado === "comiendo" ? `<button type="button" class="boton-accion-pedido" data-cerrar="${Number(p.id_pedido)}" ${bebidasPendientes ? "disabled" : ""}>Cerrar pedido</button>` : ""}</div>`;
        return `<article class="pedido-panel"><h3>Pedido #${Number(p.id_pedido)} <span class="estado-pedido">${escapeHTML(p.estado)}</span></h3><p>Mesa: ${escapeHTML(p.mesa || "-")} | Creado: ${escapeHTML(p.fecha_hora_creacion)}</p>${descripcion}<ul class="pedido-productos">${productos}</ul>${avisoBebidas}${acciones}</article>`;
    }).join("") : "No hay pedidos pendientes.";
}
function manejarAccionesPendientes(evento) {
    const botonPickear = evento.target.closest("button[data-pickear-bebida]");
    if (botonPickear) {
        marcarBebidaPickeada(Number(botonPickear.dataset.pickearBebida), Number(botonPickear.dataset.producto), botonPickear);
        return;
    }
    const botonAgregar = evento.target.closest("button[data-agregar]");
    if (botonAgregar) {
        abrirExtras(Number(botonAgregar.dataset.agregar));
        return;
    }
    const botonCerrar = evento.target.closest("button[data-cerrar]");
    if (botonCerrar) cerrarPedido(Number(botonCerrar.dataset.cerrar));
}
async function marcarBebidaPickeada(idPedido, idProducto, boton) {
    boton.disabled = true;
    try {
        const respuesta = await fetch("apis/api_pedidos.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ accion: "marcar_bebida_pickeada", id_pedido: idPedido, id_producto: idProducto })
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.error || "No se pudo marcar la bebida como pickeada.");
        await cargarPedidos("pendientes");
    } catch (error) {
        boton.disabled = false;
        mostrarNotificacion(error.message, true);
    }
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
                emitirAlerta("cocina");
                window.AlertasJBM?.navegador("Nuevo pedido en cocina", `El pedido #${idPedido} llegó a cocina.`);
                break;
            }
            const cantidadesPrevias = anterior.get(idPedido);
            const agregado = pedido.productos.find(x => Number(x.cantidad) > Number(cantidadesPrevias.get(Number(x.id_producto)) || 0));
            if (agregado) {
                mostrarNotificacion(`Se agregó ${agregado.nombre} al pedido #${idPedido}.`);
                emitirAlerta("cocina");
                window.AlertasJBM?.navegador("Pedido actualizado en cocina", `Se agregó ${agregado.nombre} al pedido #${idPedido}.`);
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
        mostrarNotificacion(`El pedido #${idPedido} está listo para levantar.`);
        cargarPedidos("cocina");
    } catch (error) {
        mostrarNotificacion(error.message, true);
    }
}
function actualizarRelojes() { document.querySelectorAll(".reloj").forEach(el => { const minutos=Math.floor((Date.now()-new Date(el.dataset.inicio).getTime())/60000); const segundos=Math.max(0,Math.floor((Date.now()-new Date(el.dataset.inicio).getTime())/1000)); el.textContent=`Tiempo: ${String(Math.floor(segundos/60)).padStart(2,"0")}:${String(segundos%60).padStart(2,"0")}`; el.className=`reloj ${minutos>=30?"rojo":minutos>=20?"naranja":minutos>=10?"amarillo":""}`; }); }
setInterval(() => { actualizarRelojes(); if (puedeCrear) { cargarEstadoCajaPedido(); if (document.getElementById("vista-limpieza")?.classList.contains("active")) cargarMesasLimpieza(); } if (puedeCocina && document.getElementById("vista-cocina")?.classList.contains("active")) cargarPedidos("cocina"); if (puedeCrear && document.visibilityState === "visible") cargarPedidos("pendientes"); }, 5000);
function mostrarNotificacion(texto, error = false) {
    const alerta = document.getElementById("notificacionPedido");
    alerta.textContent = texto;
    alerta.className = `notificacion-pedido${error ? " error" : ""}`;
    alerta.hidden = false;
    window.clearTimeout(temporizadorNotificacion);
    temporizadorNotificacion = window.setTimeout(() => { alerta.hidden = true; }, 7000);
}
function desbloquearSonido() {
    const AudioContexto = window.AudioContext || window.webkitAudioContext;
    if (!AudioContexto) return;
    contextoAudio ||= new AudioContexto();
    contextoAudio.resume().catch(error => console.warn("No se pudo habilitar el sonido de pedidos.", error));
}
function emitirAlerta(tipo = "pedido") {
    if (window.AlertasJBM) window.AlertasJBM.reproducir(tipo);
}
function escapeJS(valor) { return String(valor).replace(/\\/g,"\\\\").replace(/'/g,"\\'"); }
function escapeHTML(valor) { if (valor === null || valor === undefined) return ""; return String(valor).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;").replace(/'/g,"&#039;"); }
</script>
</body>
</html>
