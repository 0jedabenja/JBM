<?php
include_once "includes/funciones.php";
verificar_permiso("mapa");
$puedeEditar = obtener_rol_sesion() === "administrador";
$puedeLimpiar = usuario_tiene_permiso("crear_pedido");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa del local</title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
        .navigation ul li:nth-child(3){background:#fff}.navigation ul li:nth-child(3) a{color:#001f47}.navigation ul li:nth-child(3) a .icon img{content:url('assets/img/sidebar/theme-map.svg')}
        .mapa-content{padding:20px 30px 40px}.mapa-header{align-items:center;display:flex;gap:16px;justify-content:space-between;margin-bottom:20px}.mapa-header h1{color:#001f47;margin:0}.mapa-header p{color:#62737a;margin:4px 0 0}.mapa-tools-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.mapa-button{background:#001f47;border:0;border-radius:8px;color:#fff;cursor:pointer;font:inherit;font-weight:700;padding:10px 14px}.mapa-button.secondary{background:#fff;border:1px solid #d4dde0;color:#001f47}.mapa-button.ghost{background:#edf3f2;border:1px solid #d8e5e1;color:#001f47}.mapa-layout{display:grid;gap:20px}.mapa-superficie{aspect-ratio:16/10;background:radial-gradient(circle at top,#edf3f2 0,#dde9e7 30%,#d1ddd7 100%);border:1px solid #c8d4d1;border-radius:18px;overflow:hidden;position:relative;width:100%;box-shadow:0 12px 30px rgba(16,37,45,.08)}.mapa-superficie::before{background:linear-gradient(rgba(17,48,68,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(17,48,68,.06) 1px,transparent 1px);background-size:12.5% 12.5%;content:"";inset:0;position:absolute}.mapa-salida{bottom:16px;left:16px;position:absolute;z-index:0}.mapa-salida span{background:rgba(17,48,68,.08);border:1px solid rgba(17,48,68,.1);border-radius:999px;color:#214b5d;display:inline-block;font-size:12px;font-weight:700;letter-spacing:.03em;padding:8px 12px;text-transform:uppercase}.mesa{align-items:center;background:#fff;border:3px solid #2d7a5f;border-radius:16px;box-shadow:0 8px 16px rgba(28,52,58,.12);cursor:grab;display:flex;flex-direction:column;gap:3px;justify-content:center;min-height:72px;position:absolute;touch-action:none;transition:transform .15s ease,box-shadow .15s ease;user-select:none;z-index:2}.mesa:hover{transform:translateY(-1px);box-shadow:0 12px 20px rgba(28,52,58,.14)}.mesa:active{cursor:grabbing}.mesa.libre{border-color:#2d7a5f;background:linear-gradient(180deg,#f2fff7,#ebf8f0)}.mesa.ocupada{border-color:#b84a4a;background:linear-gradient(180deg,#fff5f5,#fbeaea)}.mesa.paralimpiar{border-color:#d99018;background:linear-gradient(180deg,#fffaf0,#fef1d6)}.mesa strong{color:#001f47;font-size:15px}.mesa small{color:#465b66}.mesa .mesa-actions{display:none;gap:5px;position:absolute;right:6px;top:6px}.mesa:hover .mesa-actions,.mesa:focus-within .mesa-actions{display:flex}.mesa-actions button{background:rgba(255,255,255,.9);border:0;border-radius:8px;cursor:pointer;height:24px;line-height:1;padding:0 6px}.mesa-actions button[aria-label="Editar"]{color:#001f47}.mesa-actions button[aria-label="Eliminar"]{color:#b42318}.mapa-mensaje{font-size:.9rem;min-height:20px;margin-top:12px}.mapa-mensaje.error{color:#b42318}.mapa-mensaje.success{color:#16723b}.mapa-empty{color:#62737a;left:50%;position:absolute;top:50%;transform:translate(-50%,-50%);z-index:1}.mapa-summary{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}.mapa-summary .chip{background:#fff;border:1px solid #dfece8;border-radius:999px;color:#214b5d;font-size:12px;font-weight:700;padding:8px 12px}.mapa-legend{display:flex;flex-wrap:wrap;gap:8px 12px;margin-top:14px}.mapa-legend-item{align-items:center;color:#405d69;display:inline-flex;gap:8px;font-size:13px}.mapa-legend-item .dot{border-radius:50%;display:inline-block;height:12px;width:12px}.mapa-legend-item .dot.libre{background:#2d7a5f}.mapa-legend-item .dot.ocupada{background:#b84a4a}.mapa-legend-item .dot.paralimpiar{background:#d99018}.mapa-dialog{border:0;border-radius:12px;box-shadow:0 18px 60px rgba(12,24,29,.22);margin:auto;max-height:min(90vh,780px);max-width:560px;padding:0;position:fixed;width:min(92vw,560px)}.mapa-dialog::backdrop{background:rgba(13,28,34,.62)}.mapa-dialog-inner{padding:28px}.mapa-dialog-head{align-items:flex-start;border-bottom:1px solid #dfe8e8;display:flex;justify-content:space-between;gap:12px;margin-bottom:18px;padding-bottom:16px}.mapa-dialog-head h2{color:#001f47;font-size:22px;margin:0}.mapa-dialog-head p{color:#62737a;margin:6px 0 0}.mapa-close{background:transparent;border:0;color:#405d69;cursor:pointer;font-size:28px;line-height:1;padding:0}.mapa-field{display:grid;gap:7px;margin:16px 0}.mapa-field label{color:#173744;font-size:14px;font-weight:700}.mapa-field input,.mapa-field select{border:1px solid #b8c5c5;border-radius:8px;font:inherit;padding:11px}.mapa-field input:focus,.mapa-field select:focus{border-color:#16723b;outline:2px solid rgba(22,114,59,.18)}.mapa-actions-bar{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}.mapa-actions-bar button{border-radius:8px;cursor:pointer;font:inherit;font-weight:700;padding:10px 14px}.mapa-actions-bar .secondary{background:#fff;border:1px solid #d5dde0;color:#001f47}.mapa-actions-bar .danger{background:#b42318;border:0;color:#fff}.mapa-actions-bar .primary{background:#001f47;border:0;color:#fff}.mapa-mobile-note{display:none}@keyframes mapa-entrada{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}@keyframes mapa-salida{from{opacity:1;transform:translateY(0) scale(1)}to{opacity:0;transform:translateY(8px) scale(.97)}}.mapa-dialog[open]{animation:mapa-entrada .22s ease-out both}.mapa-dialog[open].cerrando{animation:mapa-salida .18s ease-in both}.mapa-dialog[open].cerrando::backdrop{animation:fade-out .18s ease-in both}.mapa-dialog::backdrop{animation:fade-in .22s ease-out both}@keyframes fade-in{from{opacity:0}to{opacity:1}}@keyframes fade-out{from{opacity:1}to{opacity:0}}@media(max-width:900px){.mapa-content{padding:16px}.mapa-header{align-items:flex-start;flex-direction:column}.mapa-tools-row{width:100%}.mapa-button{flex:1}.mapa-summary{display:none}.mapa-mobile-note{background:#f3f8f7;border:1px solid #d3e1dd;border-radius:12px;color:#47606a;display:block;margin-top:12px;padding:10px 12px}.}.mapa-superficie{min-height:300px}
    </style>
    <style>
        .mapa-superficie{aspect-ratio:1200/700;min-height:0}
    </style>
    <style>
        .mesa-actions button[data-limpiar]{height:auto;padding:5px 8px;white-space:nowrap;color:#765400;font-size:11px}
    </style>
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
            <div class="mapa-mobile-note">Arrastrá una mesa para reposicionarla. Tocá una mesa para editarla.</div>
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
                <strong>Mesa ${escapeHTML(mesa.numero)}</strong>
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
                elemento.querySelector("[data-limpiar]").addEventListener("click", (evento) => {
                    evento.preventDefault();
                    evento.stopPropagation();
                    marcarMesaLimpia(mesa.id_mesa);
                });
            }

            superficie.appendChild(elemento);
        });
    }

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
        dialogo.style.display = "block";
        dialogo.removeAttribute("aria-hidden");

        try {
            if (typeof dialogo.showModal === "function") {
                dialogo.showModal();
            } else {
                dialogo.setAttribute("open", "open");
            }
        } catch (error) {
            dialogo.setAttribute("open", "open");
            dialogo.style.display = "block";
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
                dialogo.style.display = "none";
                dialogo.classList.remove("cerrando");
                dialogo.setAttribute("aria-hidden", "true");
                formMesa.reset();
            }, 180);
        } else {
            dialogo.style.display = "none";
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
    }, 4000);
    </script>

</body>
</html>