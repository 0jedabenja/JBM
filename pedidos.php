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
        .navigation ul li:nth-child(2) {
            background: #fff;
        }
        .navigation ul li:nth-child(2) a {
            color: #001f47;
        }
        .navigation ul li:nth-child(2) a .icon img {
            content: url("assets/img/sidebar/theme-restaurant.svg");
        }
        .advertencia-caja {
            background: #fff3cd;
            border: 1px solid #d9a441;
            border-radius: 6px;
            color: #765400;
            padding: 12px;
        }
        .bloqueado {
            cursor: not-allowed;
            opacity: 0.5;
        }
        .pedidos-content {
            padding: 20px 30px;
        }
        .pedidos-content h1,
        .pedido-panel h3,
        .cocina-pedido h3 {
            color: #001f47;
        }
        .pedido-tabs,
        .menu-filtros {
            display: flex;
            gap: 10px;
        }
        .pedido-tabs {
            margin: 20px 0;
        }
        .pedido-tabs button {
            background: #fff;
            border: 1px solid #001f47;
            border-radius: 6px;
            color: #001f47;
            cursor: pointer;
            padding: 10px 16px;
        }
        .pedido-tabs button.active {
            background: #001f47;
            color: #fff;
        }
        .pedido-view {
            display: none;
        }
        .pedido-view.active {
            display: block;
        }
        .menu-filtros {
            margin: 14px 0;
        }
        .menu-filtros input,
        .menu-filtros select {
            border: 1px solid #bbb;
            border-radius: 5px;
            font: inherit;
            padding: 10px;
        }
        .menu-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
        .menu-scroll {
            max-height: 420px;
            overflow-y: auto;
            padding: 4px;
        }
        .producto-card,
        .pedido-panel,
        .cocina-pedido {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
        }
        .pedido-panel,
        .cocina-pedido {
            margin-bottom: 15px;
        }
        .pedido-panel h3,
        .cocina-pedido h3 {
            margin-top: 0;
        }
        .producto-card button,
        .pedido-form button,
        .pedido-action {
            background: #001f47;
            border: 0;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
            padding: 10px;
        }
        .producto-card button {
            padding: 9px;
            width: 100%;
        }
        .pedido-form {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(3, 1fr);
            margin: 20px 0;
        }
        .pedido-form label {
            color: #001f47;
            font-weight: bold;
        }
        .pedido-form input {
            border: 1px solid #bbb;
            border-radius: 5px;
            padding: 10px;
        }
        .pedido-form .pedido-stock-override {
            display: flex;
            gap: 8px;
        }
        .pedido-stock-override {
            align-items: center;
            color: #765400;
            display: flex;
            font-weight: 600;
            gap: 8px;
            margin: 12px 0;
        }
        .pedido-stock-override input {
            accent-color: #001f47;
            border: 0;
            height: 18px;
            margin: 0;
            padding: 0;
            width: 18px;
        }
        .pedido-action.secondary {
            background: #fff;
            border: 1px solid #001f47;
            color: #001f47;
            margin-right: 8px;
        }
        .mapa-pedido {
            aspect-ratio: 1200 / 700;
            background-color: #dce4e2;
            background-image:
                linear-gradient(rgba(0, 31, 71, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 31, 71, 0.08) 1px, transparent 1px);
            background-size: 3.333% 5.714%;
            border: 2px solid #78908b;
            border-radius: 8px;
            margin: 15px 0;
            overflow: hidden;
            position: relative;
        }
        .mesa-pedido {
            align-items: center;
            border: 3px solid #27824b;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 60px;
            position: absolute;
            transform: translate(-50%, -50%);
            width: 8%;
            z-index: 1;
        }
        .mesa-pedido.libre {
            background: #e9f8ed;
            cursor: pointer;
        }
        .mesa-pedido.ocupada,
        .mesa-pedido.paralimpiar {
            background: #f5dada;
            cursor: not-allowed;
            opacity: 0.65;
        }
        .mesa-pedido.seleccionada {
            box-shadow: 0 0 0 4px #001f47;
        }
        .carrito {
            border-top: 1px solid #ddd;
            margin-top: 20px;
            padding-top: 10px;
        }
        .linea-carrito {
            align-items: center;
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
        }
        .producto-cocina {
            align-items: center;
            border: 1px solid #ddd;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin: 8px 0;
            padding: 10px;
        }
        .producto-cocina.completo {
            background: #e9f8ed;
            text-decoration: line-through;
        }
        .producto-cocina button {
            background: #001f47;
            border: 0;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            padding: 7px;
        }
        .reloj {
            font-weight: bold;
        }
        .reloj.amarillo {
            color: #c69200;
        }
        .reloj.naranja {
            color: #e66c00;
        }
        .reloj.rojo,
        .error {
            color: #b42318;
        }
        .mensaje {
            padding: 10px;
        }
        .mensaje.error {
            white-space: pre-line;
        }
        .exito {
            color: #16723b;
        }
        dialog.pedido-dialog {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 16px 55px #10252d40;
            margin: auto;
            max-height: 90vh;
            max-width: 900px;
            padding: 24px;
            width: calc(100% - 32px);
        }
        dialog.pedido-dialog::backdrop {
            background: #10252d88;
        }
        .dialogo-acciones {
            display: flex;
            justify-content: flex-end;
            margin-top: 14px;
        }
        @media (max-width: 800px) {
            .pedido-form {
                grid-template-columns: repeat(2, 1fr);
            }
            .pedidos-content {
                padding: 15px;
            }
        }
        @media (max-width: 500px) {
            .pedido-form {
                grid-template-columns: 1fr;
            }
            .pedido-tabs,
            .menu-filtros {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include_once "includes/sidebar.php"; ?>
    <div class="main">
        <div class="topbar">
            <div class="toggle">
                <img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú">
            </div>
            <?php include_once "includes/profile.php"; ?>
        </div>
        <main class="pedidos-content">
            <h1>Pedidos</h1>
            <nav class="pedido-tabs" aria-label="Secciones de pedidos">
                <?php if ($puedeCrear): ?>
                    <button type="button" data-vista="creacion">Crear pedido</button>
                    <button type="button" data-vista="pendientes">Pendientes</button>
                <?php endif; ?>
                <?php if ($puedeCocina): ?>
                    <button type="button" data-vista="cocina">Cocina</button>
                <?php endif; ?>
            </nav>

            <?php if ($puedeCrear): ?>
                <section id="vista-creacion" class="pedido-view">
                    <h2>Menú rápido</h2>
                    <p id="estadoCajaPedido" class="advertencia-caja">Comprobando sesión de caja...</p>
                    <p id="mensajeCreacion" class="mensaje" role="status" aria-live="polite"></p>

                    <h3>Selecciona una mesa libre</h3>
                    <div id="mapaPedido" class="mapa-pedido"></div>
                    <input id="idMesa" type="hidden" required>
                    <p id="mesaSeleccionada">Ninguna mesa seleccionada.</p>

                    <p>
                        Si no hay insumos suficientes, se mostrará un aviso. Activa la opción de forzar
                        solo si el conteo de inventario es incorrecto.
                    </p>
                    <div class="menu-filtros">
                        <input id="buscarProducto" type="search" placeholder="Buscar producto..." aria-label="Buscar producto">
                        <select id="categoriaProducto" aria-label="Filtrar por categoría">
                            <option value="">Todas las categorías</option>
                        </select>
                    </div>
                    <div class="menu-scroll">
                        <div id="menuProductos" class="menu-grid"></div>
                    </div>

                    <div class="pedido-panel">
                        <h3>Pedido actual</h3>
                        <div id="carrito" class="carrito">No hay productos agregados.</div>
                        <form id="formPedido" class="pedido-form">
                            <label for="idCliente">
                                Cliente (opcional)
                                <input id="idCliente" type="number" min="1">
                            </label>
                            <label class="pedido-stock-override" for="forzarStock">
                                <input id="forzarStock" type="checkbox">
                                Forzar pedido aunque falte stock
                            </label>
                            <button type="submit">Crear pedido</button>
                        </form>
                    </div>
                </section>

                <section id="vista-pendientes" class="pedido-view">
                    <h2>Pedidos pendientes</h2>
                    <p id="mensajePendientes" class="mensaje" role="status" aria-live="polite"></p>
                    <div id="listaPendientes"></div>
                </section>
            <?php endif; ?>

            <?php if ($puedeCocina): ?>
                <section id="vista-cocina" class="pedido-view">
                    <h2>Cocina</h2>
                    <p>Los pedidos aparecen automáticamente cuando se crean.</p>
                    <p id="mensajeBoleta" class="mensaje" role="status" aria-live="polite"></p>
                    <div id="listaCocina"></div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <dialog id="dialogAgregarProductos" class="pedido-dialog" aria-labelledby="tituloAgregarProductos">
        <h2 id="tituloAgregarProductos">Agregar productos</h2>
        <p id="mensajeAgregarProductos" class="mensaje" role="status" aria-live="polite"></p>
        <label class="pedido-stock-override" for="forzarStockExtra">
            <input id="forzarStockExtra" type="checkbox">
            Forzar pedido aunque falte stock
        </label>
        <div class="menu-filtros">
            <input id="buscarProductoExtra" type="search" placeholder="Buscar producto..." aria-label="Buscar producto">
            <select id="categoriaProductoExtra" aria-label="Filtrar por categoría">
                <option value="">Todas las categorías</option>
            </select>
        </div>
        <div class="menu-scroll">
            <div id="menuAgregarProductos" class="menu-grid"></div>
        </div>
        <div class="dialogo-acciones">
            <button class="pedido-action secondary" type="button" data-cerrar-dialogo-productos>
                Cerrar
            </button>
        </div>
    </dialog>
    <script>
const puedeCrear = <?php echo $puedeCrear ? "true" : "false"; ?>;
const puedeCocina = <?php echo $puedeCocina ? "true" : "false"; ?>;
let carrito = [];
let ultimoPedidoCocina = 0;
let contextoAudio = null;
let cajaAbierta = false;
let firmaMapaPedido = "";
let cargandoMapaPedido = false;
let productosMenu = [];
let pedidoParaAgregar = null;
let cargandoMenu = false;
let menuCargado = false;

    document.addEventListener("DOMContentLoaded", inicializarPedidos);

    function inicializarPedidos() {
        document.querySelectorAll(".pedido-tabs button").forEach((boton) => {
            boton.addEventListener("click", () => activarVista(boton.dataset.vista));
        });

        const vistaInicial = puedeCrear ? "creacion" : "cocina";
        activarVista(vistaInicial);
        configurarAccionesDialogo();

        if (puedeCrear) {
            inicializarCreacionPedido();
        }

        if (puedeCocina) {
            document.getElementById("listaCocina").addEventListener("click", manejarAccionCocina);
        }

        setInterval(actualizarVistasActivas, 5000);
    });

    function inicializarCreacionPedido() {
        cargarMenu();
        cargarEstadoCajaPedido();
        cargarMapaPedido();

        document.getElementById("formPedido").addEventListener("submit", crearPedido);
        document.getElementById("carrito").addEventListener("click", (evento) => {
            const boton = evento.target.closest("[data-ajustar-producto]");
            if (boton) {
                ajustarCantidad(Number(boton.dataset.ajustarProducto), Number(boton.dataset.cambio));
            }
        });
        document.getElementById("buscarProducto").addEventListener("input", () => renderizarMenu());
        document.getElementById("categoriaProducto").addEventListener("change", () => renderizarMenu());
        document.getElementById("buscarProductoExtra").addEventListener("input", () => {
            renderizarMenu("menuAgregarProductos", "buscarProductoExtra", "categoriaProductoExtra");
        });
        document.getElementById("categoriaProductoExtra").addEventListener("change", () => {
        renderizarMenu("menuAgregarProductos", "buscarProductoExtra", "categoriaProductoExtra");
        });
        document.getElementById("listaPendientes").addEventListener("click", manejarAccionPendiente);

        setInterval(() => {
            if (document.visibilityState === "visible") {
                cargarMapaPedido();
            }
        }, 4000);
    }

    function configurarAccionesDialogo() {
        document.querySelector("[data-cerrar-dialogo-productos]").addEventListener("click", () => {
            document.getElementById("dialogAgregarProductos").close();
        });
        document.getElementById("menuAgregarProductos").addEventListener("click", (evento) => {
            const boton = evento.target.closest("[data-producto-extra]");
            if (boton) {
                agregarProductoExtra(Number(boton.dataset.productoExtra), boton.dataset.nombre);
            }
        });
    }

    function activarVista(vista) {
        document.querySelectorAll(".pedido-view").forEach((seccion) => {
            seccion.classList.remove("active");
        });
        document.querySelectorAll(".pedido-tabs button").forEach((boton) => {
            boton.classList.toggle("active", boton.dataset.vista === vista);
        });

        const seccion = document.getElementById(`vista-${vista}`);
        if (seccion) {
            seccion.classList.add("active");
        }

        if (vista === "pendientes") {
            cargarPedidos("pendientes");
        }
        if (vista === "cocina") {
            cargarPedidos("cocina");
            setTimeout(() => cargarPedidos("cocina"), 300);
        }
    }

    function cargarMenu() {
        if (menuCargado || cargandoMenu) {
            return;
        }
        cargandoMenu = true;

        fetch("apis/api_productos.php?limite=sin&campo=todos&filtro=")
            .then((respuesta) => leerRespuestaApi(respuesta, "No se pudo cargar el menú"))
            .then((productos) => {
                productosMenu = productos;
                menuCargado = true;

                ["categoriaProducto", "categoriaProductoExtra"].forEach((id) => {
                    const selector = document.getElementById(id);
                    const opciones = [...new Set(productos.map((producto) => producto.categoria).filter(Boolean))]
                        .sort()
                        .map((categoria) => `<option value="${escapeHTML(categoria)}">${escapeHTML(categoria)}</option>`)
                        .join("");
                    selector.innerHTML = `<option value="">Todas las categorías</option>${opciones}`;
                });

                renderizarMenu();
                renderizarMenu("menuAgregarProductos", "buscarProductoExtra", "categoriaProductoExtra");
            })
            .catch((error) => {
                document.getElementById("menuProductos").textContent = error.message;
                document.getElementById("menuAgregarProductos").textContent = error.message;
            })
            .finally(() => {
                cargandoMenu = false;
            });
    }

    function renderizarMenu(
        contenedorId = "menuProductos",
        busquedaId = "buscarProducto",
        categoriaId = "categoriaProducto"
    ) {
        const contenedor = document.getElementById(contenedorId);
        const busqueda = normalizarTexto(document.getElementById(busquedaId).value.trim());
        const categoria = document.getElementById(categoriaId).value;
        const productos = productosMenu.filter((producto) => {
            const coincideCategoria = !categoria || producto.categoria === categoria;
            const textoProducto = normalizarTexto(`${producto.nombre} ${producto.categoria || ""}`);
            return coincideCategoria && (!busqueda || textoProducto.includes(busqueda));
        });

        contenedor.replaceChildren();
        if (!productos.length) {
            contenedor.textContent = "No hay productos para este filtro.";
            return;
        }

        productos.forEach((producto) => {
            const tarjeta = document.createElement("article");
            tarjeta.className = "producto-card";

            const titulo = document.createElement("h3");
            titulo.textContent = producto.nombre;

            const detalle = document.createElement("p");
            detalle.textContent = `$ ${producto.precio} · ${producto.categoria || "Sin categoría"}`;

            const boton = document.createElement("button");
            boton.type = "button";
            boton.className = "boton-producto";
            boton.disabled = !cajaAbierta;
            boton.textContent = contenedorId === "menuProductos" ? "Agregar" : "Agregar al pedido";
            if (contenedorId === "menuProductos") {
                boton.addEventListener("click", () => {
                    agregarProducto(Number(producto.id_producto), producto.nombre, Number(producto.precio));
                });
            } else {
                boton.dataset.productoExtra = producto.id_producto;
                boton.dataset.nombre = producto.nombre;
            }

            tarjeta.append(titulo, detalle, boton);
            contenedor.appendChild(tarjeta);
        });
    }

    async function cargarMapaPedido() {
        if (cargandoMapaPedido) {
            return;
        }
        cargandoMapaPedido = true;

        try {
            const respuesta = await fetch("apis/api_mesas.php", { cache: "no-store" });
            const datos = await leerRespuestaApi(respuesta, "No se pudo cargar el mapa de mesas");
            const firma = JSON.stringify(datos.mesas || []);
            if (firma === firmaMapaPedido) {
                return;
            }

            const mapa = document.getElementById("mapaPedido");
            const idSeleccionado = document.getElementById("idMesa").value;
            const mesas = datos.mesas || [];
            const seleccionada = mesas.find((mesa) =>
                String(mesa.id_mesa) === idSeleccionado && String(mesa.estado).toLowerCase() === "libre"
            );
            if (idSeleccionado && !seleccionada) {
                document.getElementById("idMesa").value = "";
                document.getElementById("mesaSeleccionada").textContent = "Ninguna mesa seleccionada.";
            }

            mapa.replaceChildren();
            mesas.forEach((mesa) => {
                const estado = normalizarTexto(mesa.estado || "");
                const boton = document.createElement("button");
                boton.type = "button";
                boton.className = `mesa-pedido ${estado}`;
                boton.style.left = `${Number(mesa.pos_x || 0) / 1200 * 100}%`;
                boton.style.top = `${Number(mesa.pos_y || 0) / 700 * 100}%`;
                boton.style.width = `${Number(mesa.ancho || 100) / 1200 * 100}%`;
                boton.style.transform = "none";
                boton.style.minHeight = "72px";
                boton.style.borderRadius = "16px";
                boton.innerHTML = `<strong>Mesa ${escapeHTML(mesa.numero)}</strong><small>${escapeHTML(mesa.estado)}</small>`;
                boton.disabled = estado !== "libre";
                if (estado === "libre") {
                    boton.addEventListener("click", () => seleccionarMesa(mesa, boton));
                }
                if (String(mesa.id_mesa) === idSeleccionado) {
                    boton.classList.add("seleccionada");
                }
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
        document.querySelectorAll(".mesa-pedido").forEach((mesaElemento) => {
            mesaElemento.classList.remove("seleccionada");
        });
        elemento.classList.add("seleccionada");
        document.getElementById("idMesa").value = mesa.id_mesa;
        document.getElementById("mesaSeleccionada").textContent = `Mesa ${mesa.numero} seleccionada.`;
    }

    function agregarProducto(id, nombre, precio) {
        const existente = carrito.find((producto) => producto.id_producto === id);
        if (existente) {
            existente.cantidad += 1;
        } else {
            carrito.push({ id_producto: id, nombre, precio, cantidad: 1 });
        }
        renderCarrito();
    }

    function ajustarCantidad(id, cambio) {
        const producto = carrito.find((elemento) => elemento.id_producto === id);
        if (!producto) {
            return;
        }

        producto.cantidad += cambio;
        if (producto.cantidad <= 0) {
            carrito = carrito.filter((elemento) => elemento.id_producto !== id);
        }
        renderCarrito();
}

    function renderCarrito() {
        const contenedor = document.getElementById("carrito");
        if (!carrito.length) {
            contenedor.textContent = "No hay productos agregados.";
            return;
        }

        contenedor.innerHTML = carrito.map((producto) => `
            <div class="linea-carrito">
                <span>${escapeHTML(producto.nombre)} · $ ${(producto.precio * producto.cantidad).toFixed(2)}</span>
                <div>
                    <button type="button" aria-label="Quitar una unidad de ${escapeHTML(producto.nombre)}"
                        data-ajustar-producto="${producto.id_producto}" data-cambio="-1">−</button>
                    <strong>${producto.cantidad}</strong>
                    <button type="button" aria-label="Agregar una unidad de ${escapeHTML(producto.nombre)}"
                        data-ajustar-producto="${producto.id_producto}" data-cambio="1">+</button>
                </div>
            </div>
        `).join("");
    }

    function normalizarTexto(valor) {
        return String(valor ?? "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLocaleLowerCase();
    }

    async function crearPedido(evento) {
        evento.preventDefault();
        const idMesa = document.getElementById("idMesa").value;
        if (!cajaAbierta) {
            mostrarMensaje("No se puede crear el pedido: la caja está cerrada.", true);
            return;
        }
        if (!idMesa) {
            mostrarMensaje("Selecciona una mesa libre antes de crear el pedido.", true);
            return;
        }
        if (!carrito.length) {
            mostrarMensaje("Agrega al menos un producto.", true);
            return;
        }

        const datos = {
            id_mesa: Number(idMesa),
            id_cliente: document.getElementById("idCliente").value
                ? Number(document.getElementById("idCliente").value)
                : null,
            forzar_stock: document.getElementById("forzarStock").checked,
            productos: carrito.map((producto) => ({
                id_producto: producto.id_producto,
                cantidad: producto.cantidad
            }))
        };

        try {
            const respuesta = await fetch("apis/api_pedidos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            });
            const resultado = await leerRespuestaApi(respuesta, "No se pudo crear el pedido");

            carrito = [];
            document.getElementById("forzarStock").checked = false;
            renderCarrito();
            document.getElementById("idMesa").value = "";
            document.getElementById("mesaSeleccionada").textContent = "Ninguna mesa seleccionada.";
            cargarMapaPedido();
            mostrarMensaje(`Pedido #${resultado.id_pedido} enviado a cocina.`, false);
        } catch (error) {
            cargarMapaPedido();
            mostrarMensaje(error.message, true);
        }
    }

    async function cargarEstadoCajaPedido() {
        const aviso = document.getElementById("estadoCajaPedido");
        try {
            const respuesta = await fetch("apis/api_pedidos.php?caja=estado");
            const datos = await leerRespuestaApi(respuesta, "No se pudo consultar el estado de caja");
            cajaAbierta = Boolean(datos.abierta);
            aviso.textContent = cajaAbierta
                ? "Caja abierta. Se pueden crear pedidos."
                : "Caja cerrada. Abre una sesión de caja para crear pedidos.";
            aviso.className = cajaAbierta ? "mensaje exito" : "advertencia-caja";

            document.querySelectorAll(".boton-producto, #formPedido button[type=submit]").forEach((boton) => {
                boton.disabled = !cajaAbierta;
                boton.classList.toggle("bloqueado", !cajaAbierta);
            });
            if (cajaAbierta) {
                cargarMapaPedido();
            }
        } catch (error) {
            cajaAbierta = false;
            aviso.textContent = error.message;
            aviso.className = "advertencia-caja";
        }
    }

    async function leerRespuestaApi(respuesta, operacion = "La solicitud") {
        const texto = await respuesta.text();
        let datos;
        try {
            datos = texto.trim() ? JSON.parse(texto) : null;
        } catch (error) {
            throw new Error(`${operacion}: el servidor devolvió una respuesta no válida (HTTP ${respuesta.status}).`);
        }
        if (!datos) {
            throw new Error(`${operacion}: el servidor devolvió una respuesta vacía (HTTP ${respuesta.status}).`);
        }
        if (!respuesta.ok) {
            const detalleFaltantes = Array.isArray(datos.faltantes)
                ? datos.faltantes.map((insumo) => {
                    const unidad = insumo.unidad ? ` ${insumo.unidad}` : "";
                    return `${insumo.nombre}: se necesitan ${Number(insumo.requerido).toLocaleString("es-AR")}${unidad} y hay ${Number(insumo.disponible).toLocaleString("es-AR")}${unidad}.`;
                }).join("\n")
                : "";
            throw new Error([
                datos.error || `${operacion} (HTTP ${respuesta.status}).`,
                detalleFaltantes
            ].filter(Boolean).join("\n"));
        }
        return datos;
    }

    function mostrarMensaje(texto, error) {
        const elemento = document.getElementById("mensajeCreacion");
        elemento.textContent = texto;
        elemento.className = `mensaje ${error ? "error" : "exito"}`;
    }

    function cargarPedidos(vista) {
        fetch(`apis/api_pedidos.php?vista=${vista}&limite=100`)
            .then((respuesta) => leerRespuestaApi(respuesta, "No se pudieron cargar los pedidos"))
            .then((pedidos) => {
                if (vista === "cocina") {
                    renderCocina(pedidos);
                } else {
                    renderPendientes(pedidos);
                }
            })
            .catch((error) => {
                const idContenedor = vista === "cocina" ? "listaCocina" : "listaPendientes";
                const contenedor = document.getElementById(idContenedor);
                if (contenedor) {
                    contenedor.textContent = error.message;
                }
                console.error(error);
            });
    }

    function renderPendientes(pedidos) {
        const contenedor = document.getElementById("listaPendientes");
        contenedor.innerHTML = pedidos.length
            ? pedidos.map((pedido) => `
                <article class="pedido-panel">
                    <h3>Pedido #${pedido.id_pedido}</h3>
                    <p>Estado: ${escapeHTML(pedido.estado === "Listo" ? "Listo para cobrar" : pedido.estado)}
                        | Mesa: ${escapeHTML(pedido.mesa || "-")}
                        | Creado: ${escapeHTML(pedido.fecha_hora_creacion)}</p>
                    <p>${pedido.productos.map((producto) =>
                        `${escapeHTML(producto.nombre)} x ${producto.cantidad}`).join(", ")}</p>
                    ${pedido.estado === "Comiendo" ? `
                        <button class="pedido-action secondary" type="button"
                            data-accion-pedido="agregar" data-id-pedido="${Number(pedido.id_pedido)}">
                            Agregar productos
                        </button>
                        <button class="pedido-action" type="button"
                            data-accion-pedido="cerrar" data-id-pedido="${Number(pedido.id_pedido)}">
                            Cerrar pedido
                        </button>
                    ` : ""}
                </article>
            `).join("")
            : "No hay pedidos pendientes.";
    }

    function manejarAccionPendiente(evento) {
        const boton = evento.target.closest("[data-accion-pedido]");
        if (!boton) {
            return;
        }

        const idPedido = Number(boton.dataset.idPedido);
        if (boton.dataset.accionPedido === "agregar") {
            abrirAgregarProductos(idPedido);
        } else if (boton.dataset.accionPedido === "cerrar") {
            cerrarPedido(idPedido);
        }
    }

    function renderCocina(pedidos) {
        const nuevos = pedidos.filter((pedido) => Number(pedido.id_pedido) > ultimoPedidoCocina);
        if (ultimoPedidoCocina && nuevos.length) {
            emitirAlerta();
        }
        if (pedidos.length) {
            ultimoPedidoCocina = Math.max(...pedidos.map((pedido) => Number(pedido.id_pedido)));
        }

        const contenedor = document.getElementById("listaCocina");
        contenedor.innerHTML = pedidos.length
            ? pedidos.map((pedido) => `
                <article class="cocina-pedido">
                    <h3>Pedido #${pedido.id_pedido}</h3>
                    <p>Mesa: ${escapeHTML(pedido.mesa || "-")}
                        | <span class="reloj" data-inicio="${escapeHTML(pedido.fecha_hora_creacion)}"></span></p>
                    ${pedido.productos.map((producto) => `
                        <div class="producto-cocina ${Number(producto.completado) ? "completo" : ""}">
                            <span>${escapeHTML(producto.nombre)} x ${producto.cantidad}</span>
                            ${Number(producto.completado)
                                ? "Completo"
                                : `<button type="button" data-accion-cocina="completar"
                                    data-id-pedido="${Number(pedido.id_pedido)}"
                                    data-id-producto="${Number(producto.id_producto)}">Completar</button>`}
                        </div>
                    `).join("")}
                    <button type="button" data-accion-cocina="finalizar"
                        data-id-pedido="${Number(pedido.id_pedido)}">Marcar como listo</button>
                </article>
            `).join("")
            : "No hay pedidos en cocina.";

        actualizarRelojes();
    }

    function manejarAccionCocina(evento) {
        const boton = evento.target.closest("[data-accion-cocina]");
        if (!boton) {
            return;
        }

        const idPedido = Number(boton.dataset.idPedido);
        if (boton.dataset.accionCocina === "completar") {
            completarProducto(idPedido, Number(boton.dataset.idProducto));
        } else if (boton.dataset.accionCocina === "finalizar") {
            finalizarPedido(idPedido);
        }
    }

    async function completarProducto(idPedido, idProducto) {
        try {
            const respuesta = await fetch("apis/api_pedidos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ accion: "completar_producto", id_pedido: idPedido, id_producto: idProducto })
            });
            await leerRespuestaApi(respuesta, "No se pudo completar el producto");
            cargarPedidos("cocina");
        } catch (error) {
            const mensaje = document.getElementById("mensajeBoleta");
            if (mensaje) {
                mensaje.textContent = error.message;
            }
        }
    }

    async function finalizarPedido(idPedido) {
        const mensaje = document.getElementById("mensajeBoleta");
        mensaje.textContent = "";
        try {
            const respuesta = await fetch("apis/api_pedidos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ accion: "finalizar", id_pedido: idPedido })
            });
            await leerRespuestaApi(respuesta, "No se pudo finalizar el pedido");
            mensaje.textContent = `Pedido #${idPedido} listo y en estado Comiendo.`;
            cargarPedidos("cocina");
        } catch (error) {
            mensaje.textContent = error.message;
        }
    }

    function abrirAgregarProductos(idPedido) {
        pedidoParaAgregar = idPedido;
        document.getElementById("forzarStockExtra").checked = false;

        const mensaje = document.getElementById("mensajeAgregarProductos");
        mensaje.textContent = `Pedido #${idPedido}. Los productos agregados volverán a cocina.`;
        mensaje.className = "mensaje";
        document.getElementById("buscarProductoExtra").value = "";
        document.getElementById("categoriaProductoExtra").value = "";
        renderizarMenu("menuAgregarProductos", "buscarProductoExtra", "categoriaProductoExtra");
        document.getElementById("dialogAgregarProductos").showModal();
    }

    async function agregarProductoExtra(idProducto, nombre) {
        const mensaje = document.getElementById("mensajeAgregarProductos");
        if (!cajaAbierta) {
            mensaje.textContent = "No se pueden agregar productos: la caja está cerrada.";
            mensaje.className = "mensaje error";
            return;
        }

        try {
            const respuesta = await fetch("apis/api_pedidos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    accion: "agregar_productos",
                    id_pedido: pedidoParaAgregar,
                    forzar_stock: document.getElementById("forzarStockExtra").checked,
                    productos: [{ id_producto: idProducto, cantidad: 1 }]
                })
            });
            await leerRespuestaApi(respuesta, "No se pudieron agregar los productos");

            document.getElementById("forzarStockExtra").checked = false;
            mensaje.textContent = `1 × ${nombre} agregado al pedido #${pedidoParaAgregar}.`;
            mensaje.className = "mensaje exito";
            cargarPedidos("pendientes");
        } catch (error) {
            mensaje.textContent = error.message;
            mensaje.className = "mensaje error";
        }
    }

    async function cerrarPedido(idPedido) {
        if (!window.confirm(`¿Cerrar el pedido #${idPedido} y enviarlo a caja para cobrar?`)) {
            return;
        }

        const mensaje = document.getElementById("mensajePendientes");
        try {
            const respuesta = await fetch("apis/api_pedidos.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ accion: "cerrar_pedido", id_pedido: idPedido })
            });
            await leerRespuestaApi(respuesta, "No se pudo cerrar el pedido");
            mensaje.textContent = `Pedido #${idPedido} enviado a caja para cobrar.`;
            mensaje.className = "mensaje exito";
            cargarPedidos("pendientes");
        } catch (error) {
            mensaje.textContent = error.message;
            mensaje.className = "mensaje error";
        }
    }

    function actualizarRelojes() {
        document.querySelectorAll(".reloj").forEach((reloj) => {
            const segundos = Math.max(0, Math.floor((Date.now() - new Date(reloj.dataset.inicio).getTime()) / 1000));
            const minutosTotales = Math.floor(segundos / 60);
            reloj.textContent = `Tiempo: ${String(minutosTotales).padStart(2, "0")}:${String(segundos % 60).padStart(2, "0")}`;
            reloj.className = `reloj ${
                minutosTotales >= 30 ? "rojo" : minutosTotales >= 20 ? "naranja" : minutosTotales >= 10 ? "amarillo" : ""
            }`;
        });
    }

    function actualizarVistasActivas() {
        actualizarRelojes();
        if (puedeCrear) {
            cargarEstadoCajaPedido();
        }
        if (puedeCocina && document.getElementById("vista-cocina").classList.contains("active")) {
            cargarPedidos("cocina");
        }
        if (puedeCrear && document.getElementById("vista-pendientes").classList.contains("active")) {
            cargarPedidos("pendientes");
        }
    }

    function emitirAlerta() {
        try {
            contextoAudio ||= new (window.AudioContext || window.webkitAudioContext)();
            const oscilador = contextoAudio.createOscillator();
            const ganancia = contextoAudio.createGain();
            oscilador.connect(ganancia);
            ganancia.connect(contextoAudio.destination);
            oscilador.frequency.value = 880;
            ganancia.gain.value = 0.15;
            oscilador.start();
            oscilador.stop(contextoAudio.currentTime + 0.25);
        } catch (error) {
            console.warn("No se pudo reproducir el aviso de cocina.", error);
        }
    }

    function escapeHTML(valor) {
        if (valor === null || valor === undefined) {
            return "";
        }
        return String(valor)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    </script>
</body>
</html>
