<?php
include_once "includes/funciones.php";
verificar_permiso("mapa");
$puedeEditar = obtener_rol_sesion() === "administrador";
$puedeLimpiar = usuario_tiene_permiso("crear_pedido");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa del local</title>
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/mapa.css">
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once 'includes/profile.php'; ?>
        </div>
        <main class="mapa-content">
            <header class="mapa-header">
                <div>
                    <p>Plano del salón</p>
                    <h1>Mapa del local</h1>
                </div>
                <?php if ($puedeEditar): ?>
                    <div class="mapa-tools-row">
                        <button id="btnNuevaMesa" class="mapa-button" type="button">+ Nueva mesa</button>
                    </div>
                <?php endif; ?>
            </header>
            <div class="mapa-summary">
                <span class="chip" id="chipLibre">0 libres</span>
                <span class="chip" id="chipOcupadas">0 ocupadas</span>
                <span class="chip" id="chipLimpieza">0 para limpiar</span>
            </div>
            <div class="mapa-legend" aria-label="Leyenda de estado de mesas">
                <span class="mapa-legend-item"><span class="dot libre"></span>Libre</span>
                <span class="mapa-legend-item"><span class="dot ocupada"></span>Ocupada</span>
                <span class="mapa-legend-item"><span class="dot paralimpiar"></span>Para limpiar</span>
            </div>
            <div class="mapa-mobile-note"><?php echo $puedeEditar ? "Arrastrá una mesa para reposicionarla. Tocá una mesa para editarla." : ($puedeLimpiar ? "Tocá una mesa pendiente de limpieza y luego presioná «Marcar limpia»." : "Tocá una mesa para verla."); ?></div>
            <p id="mensajeMapa" class="mapa-mensaje" role="status" aria-live="polite"></p>
            <section id="superficie" class="mapa-superficie" aria-label="Mapa del local"></section>
        </main>
    </div>

    <?php if ($puedeEditar): ?>
    <dialog id="dialogMesa" class="mapa-dialog" aria-labelledby="tituloDialogMesa">
        <form id="formMesa" class="mapa-dialog-inner" method="dialog">
            <div class="mapa-dialog-head">
                <div>
                    <h2 id="tituloDialogMesa">Mesa</h2>
                    <p id="subtituloDialogMesa">Crea o edita una mesa del local.</p>
                </div>
                <button type="button" class="mapa-close" data-cerrar-dialog="dialogMesa" aria-label="Cerrar">&times;</button>
            </div>
            <input id="idMesa" type="hidden">
            <div class="mapa-field">
                <label for="numeroMesa">Número</label>
                <input id="numeroMesa" type="number" min="1" step="1" required>
            </div>
            <div class="mapa-actions-bar">
                <button type="button" class="secondary" data-cerrar-dialog="dialogMesa">Cancelar</button>
                <button type="button" id="btnEliminarMesa" class="danger" hidden>Eliminar</button>
                <button type="submit" class="primary">Guardar</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <script>
    const puedeEditar = <?php echo $puedeEditar ? "true" : "false"; ?>;
    const puedeLimpiar = <?php echo $puedeLimpiar ? "true" : "false"; ?>;
    const MAPA_WIDTH = 1200;
    const MAPA_HEIGHT = 700;
    const MESA_HEIGHT = 80;
    let mesas = [];
    const superficie = document.getElementById("superficie");
    const dialogMesa = document.getElementById("dialogMesa");
    const formMesa = document.getElementById("formMesa");
    const idMesaInput = document.getElementById("idMesa");
    const numeroMesaInput = document.getElementById("numeroMesa");
    const btnEliminarMesa = document.getElementById("btnEliminarMesa");
    const btnNuevaMesa = document.getElementById("btnNuevaMesa");
    let firmaMesas = "";
    let cargandoMapa = false;
    let arrastrando = false;

    function inicializarMapa() {
        if (btnNuevaMesa) {
            btnNuevaMesa.addEventListener("click", () => abrirDialogMesa());
        }

        document.querySelectorAll("[data-cerrar-dialog]").forEach((boton) => {
            boton.addEventListener("click", () => cerrarDialog(dialogMesa));
        });

        if (formMesa) {
            formMesa.addEventListener("submit", async (evento) => {
                evento.preventDefault();
                const idMesa = idMesaInput.value ? Number(idMesaInput.value) : null;
                const payload = { numero: Number(numeroMesaInput.value) };

                if (!Number.isInteger(payload.numero) || payload.numero <= 0) {
                    mostrarMensaje("El número de la mesa es obligatorio.", true);
                    return;
                }

                const metodo = idMesa ? "PATCH" : "POST";
                const body = idMesa ? { ...payload, id_mesa: idMesa } : payload;
                const respuesta = await fetch("apis/api_mesas.php", { method: metodo, headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });
                const datos = await respuesta.json();

                if (!respuesta.ok) {
                    mostrarMensaje(datos.error || "No se pudo guardar la mesa.", true);
                    return;
                }

                mostrarMensaje(idMesa ? "Mesa actualizada correctamente." : "Mesa creada correctamente.", false);
                cerrarDialog(dialogMesa);
                await cargarMapa();
            });
        }

        if (btnEliminarMesa) {
            btnEliminarMesa.addEventListener("click", async () => {
                const idMesa = Number(idMesaInput.value);
                if (!idMesa) return;
                if (await eliminarMesa(idMesa, "¿Seguro que querés eliminar esta mesa?")) cerrarDialog(dialogMesa);
            });
        }

        if (dialogMesa) {
            dialogMesa.addEventListener("cancel", (evento) => {
                evento.preventDefault();
                cerrarDialog(dialogMesa);
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", inicializarMapa);
    } else {
        inicializarMapa();
    }

    async function cargarMapa(mostrarError = true) {
        if (cargandoMapa) return;
        cargandoMapa = true;

        try {
            const respuesta = await fetch("apis/api_mesas.php", { cache: "no-store" });
            const datos = await respuesta.json();
            if (!respuesta.ok) throw new Error(datos.error || "No se pudo cargar el mapa.");
            const nuevasMesas = datos.mesas || [];
            const nuevaFirma = JSON.stringify(nuevasMesas);
            if (nuevaFirma !== firmaMesas) {
                mesas = nuevasMesas;
                firmaMesas = nuevaFirma;
                renderMesas();
                actualizarResumen();
            }
        } catch (error) {
            if (mostrarError) mostrarMensaje(error.message, true);
        } finally {
            cargandoMapa = false;
        }
    }

    function renderMesas() {
        if (!superficie) return;

        superficie.innerHTML = "";
        if (!mesas.length) {
            superficie.innerHTML = '<div class="mapa-empty">Todavía no hay mesas en este local.</div>';
            return;
        }

        mesas.forEach((mesa) => {
            const estado = normalizarEstado(mesa.estado || "");
            const elemento = document.createElement("article");
            elemento.className = `mesa ${estado}`;
            elemento.tabIndex = 0;
            elemento.setAttribute("role", "button");
            elemento.setAttribute("aria-label", `Mesa ${mesa.numero}`);
            elemento.style.left = `${Math.max(0, Number(mesa.pos_x || 0)) / MAPA_WIDTH * 100}%`;
            elemento.style.top = `${Math.max(0, Number(mesa.pos_y || 0)) / MAPA_HEIGHT * 100}%`;
            elemento.style.width = `${Math.max(60, Number(mesa.ancho || 100)) / MAPA_WIDTH * 100}%`;
            elemento.innerHTML = `
                <strong><span class="mesa-prefijo">Mesa </span>${escapeHTML(mesa.numero)}</strong>
                <small>${escapeHTML(mesa.estado)}</small>
                ${puedeEditar || (puedeLimpiar && estado === "paralimpiar") ? `
                    <span class="mesa-actions">
                        ${puedeEditar ? `<button type="button" aria-label="Editar" data-editar="${mesa.id_mesa}">✎</button><button type="button" aria-label="Eliminar" data-eliminar="${mesa.id_mesa}">×</button>` : ""}
                        ${puedeLimpiar && estado === "paralimpiar" ? `<button type="button" aria-label="Marcar mesa como limpia" data-limpiar="${mesa.id_mesa}">Marcar limpia</button>` : ""}
                    </span>
                ` : ""}
            `;

            if (puedeEditar) {
                let suprimirClick = false;
                elemento.addEventListener("pointerdown", (evento) => iniciarArrastre(evento, mesa, elemento, () => {
                    suprimirClick = true;
                }));
                elemento.addEventListener("click", (evento) => {
                    if (suprimirClick) {
                        suprimirClick = false;
                        evento.preventDefault();
                        evento.stopPropagation();
                        return;
                    }

                    if (evento.target.closest("button[data-limpiar]")) {
                        evento.preventDefault();
                        evento.stopPropagation();
                        marcarMesaLimpia(mesa.id_mesa);
                        return;
                    }

                    if (evento.target.closest("button[data-editar]")) {
                        evento.preventDefault();
                        abrirDialogMesa(mesa.id_mesa);
                        return;
                    }

                    if (evento.target.closest("button[data-eliminar]")) {
                        evento.preventDefault();
                        eliminarMesaDirecto(mesa.id_mesa);
                        return;
                    }

                    abrirDialogMesa(mesa.id_mesa);
                });

                elemento.addEventListener("keydown", (evento) => {
                    if (evento.key === "Enter" || evento.key === " ") {
                        evento.preventDefault();
                        abrirDialogMesa(mesa.id_mesa);
                    }
                });
            }

            if (puedeLimpiar && estado === "paralimpiar" && !puedeEditar) {
                elemento.setAttribute("aria-label", `Mesa ${mesa.numero}, para limpiar. Tócala para mostrar las acciones.`);
                elemento.addEventListener("click", (evento) => {
                    if (evento.target.closest("[data-limpiar]")) {
                        evento.preventDefault();
                        evento.stopPropagation();
                        marcarMesaLimpia(mesa.id_mesa);
                        return;
                    }
                    document.querySelectorAll(".mesa.seleccionada").forEach((seleccionada) => {
                        if (seleccionada !== elemento) seleccionada.classList.remove("seleccionada");
                    });
                    elemento.classList.toggle("seleccionada");
                });
                elemento.addEventListener("keydown", (evento) => {
                    if (evento.target === elemento && (evento.key === "Enter" || evento.key === " ")) {
                        evento.preventDefault();
                        elemento.classList.toggle("seleccionada");
                    }
                });
            }

            superficie.appendChild(elemento);
        });
    }

    //callback
    function iniciarArrastre(evento, mesa, elemento, marcarArrastre) {
        if (!puedeEditar || evento.target.closest("button")) return;
        if (evento.button !== 0) return;
        const rect = superficie.getBoundingClientRect();
        const mapaWidth = rect.width || 1200;
        const mapaHeight = rect.height || 700;
        const startX = evento.clientX;
        const startY = evento.clientY;
        const startPosX = Number(mesa.pos_x || 0);
        const startPosY = Number(mesa.pos_y || 0);
        arrastrando = true;

        const mover = (event) => {
            if (!event.isPrimary) return;
            const diferenciaX = event.clientX - startX;
            const diferenciaY = event.clientY - startY;
            if (Math.hypot(diferenciaX, diferenciaY) > 5) {
                marcarArrastre();
            }

            const deltaX = (event.clientX - startX) * MAPA_WIDTH / mapaWidth;
            const deltaY = (event.clientY - startY) * MAPA_HEIGHT / mapaHeight;
            const ancho = Number(mesa.ancho || 100);
            const nuevaX = Math.round(Math.min(Math.max(0, startPosX + deltaX), MAPA_WIDTH - ancho));
            const nuevaY = Math.round(Math.min(Math.max(0, startPosY + deltaY), MAPA_HEIGHT - MESA_HEIGHT));

            mesa.pos_x = nuevaX;
            mesa.pos_y = nuevaY;
            elemento.style.left = `${(nuevaX / MAPA_WIDTH) * 100}%`;
            elemento.style.top = `${(nuevaY / MAPA_HEIGHT) * 100}%`;
        };

        const fin = async () => {
            window.removeEventListener("pointermove", mover);
            window.removeEventListener("pointerup", fin);
            window.removeEventListener("pointercancel", fin);
            arrastrando = false;
            if (mesa.pos_x === startPosX && mesa.pos_y === startPosY) return;
            await guardarMesaPosicion(mesa);
        };

        window.addEventListener("pointermove", mover);
        window.addEventListener("pointerup", fin, { once: true });
        window.addEventListener("pointercancel", fin, { once: true });
    }

    async function guardarMesaPosicion(mesa) {
        const respuesta = await fetch("apis/api_mesas.php", {
            method: "PATCH",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id_mesa: Number(mesa.id_mesa),
                pos_x: Number(mesa.pos_x),
                pos_y: Number(mesa.pos_y)
            })
        });

        const datos = await respuesta.json();
        if (!respuesta.ok) {
            mostrarMensaje(datos.error || "No se pudo guardar la posición de la mesa.", true);
            return;
        }

        mostrarMensaje("Posición actualizada.", false);
    }

    async function marcarMesaLimpia(idMesa) {
        try {
            const respuesta = await fetch("apis/api_mesas.php", {
                method: "PATCH",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ accion: "marcar_limpia", id_mesa: Number(idMesa) })
            });
            const datos = await respuesta.json();
            if (!respuesta.ok) throw new Error(datos.error || "No se pudo marcar la mesa como limpia.");
            mostrarMensaje("Mesa marcada como limpia y disponible.", false);
            await cargarMapa();
        } catch (error) {
            mostrarMensaje(error.message, true);
        }
    }

    function abrirDialogMesa(idMesa = null) {
        if (!dialogMesa) {
            mostrarMensaje("No se pudo abrir la edición de mesas.", true);
            return;
        }

        formMesa.reset();
        btnEliminarMesa.hidden = !idMesa;
        idMesaInput.value = idMesa || "";

        if (!idMesa) {
            numeroMesaInput.focus();
            document.getElementById("tituloDialogMesa").textContent = "Nueva mesa";
            document.getElementById("subtituloDialogMesa").textContent = "Creá una mesa nueva para este salón.";
            btnEliminarMesa.hidden = true;
            abrirDialogNative(dialogMesa);
            return;
        }

        const mesa = mesas.find((item) => Number(item.id_mesa) === Number(idMesa));
        if (!mesa) return;

        document.getElementById("tituloDialogMesa").textContent = `Mesa #${mesa.numero}`;
        document.getElementById("subtituloDialogMesa").textContent = "Editá los datos de la mesa.";
        numeroMesaInput.value = mesa.numero;
        abrirDialogNative(dialogMesa);
    }

    function abrirDialogNative(dialogo) {
        if (!dialogo) return;
        dialogo.removeAttribute("aria-hidden");

        try {
            if (typeof dialogo.showModal === "function") {
                dialogo.showModal();
            } else {
                dialogo.setAttribute("open", "open");
            }
        } catch (error) {
            dialogo.setAttribute("open", "open");
        }
    }

    function cerrarDialog(dialogo) {
        if (!dialogo) return;
        if (dialogo.classList.contains("cerrando")) return;

        if (dialogo.open || dialogo.hasAttribute("open")) {
            dialogo.classList.add("cerrando");
            setTimeout(() => {
                if (typeof dialogo.close === "function") {
                    dialogo.close();
                }
                dialogo.removeAttribute("open");
                dialogo.classList.remove("cerrando");
                dialogo.setAttribute("aria-hidden", "true");
                formMesa.reset();
            }, 180);
        } else {
            dialogo.setAttribute("aria-hidden", "true");
        }
    }

    async function eliminarMesaDirecto(idMesa) {
        await eliminarMesa(idMesa, "¿Querés eliminar esta mesa?");
    }

    async function eliminarMesa(idMesa, confirmacion) {
        if (!window.confirm(confirmacion)) return false;

        const respuesta = await fetch("apis/api_mesas.php", {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id_mesa: idMesa })
        });
        const datos = await respuesta.json();

        if (!respuesta.ok) {
            mostrarMensaje(datos.error || "No se pudo eliminar la mesa.", true);
            return false;
        }

        mostrarMensaje("Mesa eliminada.", false);
        await cargarMapa();
        return true;
    }

    function actualizarResumen() {
        const libres = mesas.filter((mesa) => String(mesa.estado).toLowerCase() === "libre").length;
        const ocupadas = mesas.filter((mesa) => String(mesa.estado).toLowerCase() === "ocupada").length;
        const limpiezas = mesas.filter((mesa) => String(mesa.estado).toLowerCase() === "para limpiar").length;

        document.getElementById("chipLibre").textContent = `${libres} libres`;
        document.getElementById("chipOcupadas").textContent = `${ocupadas} ocupadas`;
        document.getElementById("chipLimpieza").textContent = `${limpiezas} para limpiar`;
    }

    function normalizarEstado(estado) {
        const valor = String(estado || "").trim().toLowerCase();
        if (valor === "ocupada") return "ocupada";
        if (valor === "para limpiar") return "paralimpiar";
        return "libre";
    }

    function mostrarMensaje(texto, error) {
        const elemento = document.getElementById("mensajeMapa");
        if (!elemento) return;
        elemento.textContent = texto;
        elemento.className = `mapa-mensaje ${error ? "error" : "success"}`;
    }

    function escapeHTML(valor) {
        if (valor === null || valor === undefined) return "";
        return String(valor)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    cargarMapa();
    setInterval(() => {
        if (document.visibilityState === "visible" && !arrastrando) cargarMapa(false);
    }, 1000);
    </script>

</body>
</html>