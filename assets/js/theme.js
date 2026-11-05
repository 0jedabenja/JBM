(() => {
    const storageKey = "jbm-theme";
    function getToggle() {
        return document.querySelector("[data-theme-toggle]");
    }

    function applyTheme(theme) {
        const isDark = theme === "dark";
        document.documentElement.dataset.theme = isDark ? "dark" : "light";
        if (document.body) document.body.dataset.theme = isDark ? "dark" : "light";
        const toggleActual = getToggle();
        if (toggleActual) {
            toggleActual.textContent = isDark ? "Tema: oscuro" : "Tema: claro";
            toggleActual.setAttribute("aria-pressed", String(isDark));
        }
    }

    let savedTheme = "light";
    try {
        savedTheme = localStorage.getItem(storageKey) === "dark" ? "dark" : "light";
    } catch (error) {
        savedTheme = "light";
    }
    applyTheme(savedTheme);

    function inicializarAlternador() {
        const toggle = getToggle();
        if (!toggle || toggle.dataset.themeInicializado === "true") return;
        toggle.dataset.themeInicializado = "true";
        toggle.addEventListener("click", () => {
            const nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
            applyTheme(nextTheme);
            try {
                localStorage.setItem(storageKey, nextTheme);
            } catch (error) {
            }
        });
    }
    inicializarAlternador();
    document.addEventListener("DOMContentLoaded", () => {
        applyTheme(savedTheme);
        inicializarAlternador();
    });
})();

// Desplaza suavemente hasta los mensajes (alertas, errores, avisos) cuando aparecen o cambian.
(() => {
    const selector = '[role="alert"], [role="status"], .alerta, .caja-message, .mensaje, .alta-error, .alta-success, .form-error';
    const textos = new WeakMap();

    function revisar(el) {
        const texto = el.textContent.trim();
        if (textos.get(el) === texto) return;
        textos.set(el, texto);
        if (!texto || el.hidden || el.offsetParent === null && getComputedStyle(el).position !== "fixed") return;
        const r = el.getBoundingClientRect();
        if (r.top >= 0 && r.bottom <= window.innerHeight) return;
        el.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    function revisarTodo(raiz) {
        if (raiz.matches && raiz.matches(selector)) revisar(raiz);
        if (raiz.querySelectorAll) raiz.querySelectorAll(selector).forEach(revisar);
    }

    function iniciar() {
        document.querySelectorAll(selector).forEach(revisar);
        new MutationObserver(cambios => {
            const vistos = new Set();
            cambios.forEach(c => {
                let nodo = c.target.nodeType === 1 ? c.target : c.target.parentElement;
                const el = nodo && nodo.closest ? nodo.closest(selector) : null;
                if (el) vistos.add(el);
                c.addedNodes.forEach(n => { if (n.nodeType === 1) revisarTodo(n); });
            });
            vistos.forEach(revisar);
        }).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ["hidden", "class"] });
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", iniciar);
    else iniciar();
})();

// Dialogos propios del sistema (reemplazan confirm/alert/prompt): JBM.confirmar, JBM.avisar, JBM.pedir.
(() => {
    const CSS = `
dialog.jbm-dialogo{border:0;border-radius:20px;box-shadow:0 24px 70px rgba(16,37,45,.28),0 4px 14px rgba(16,37,45,.12);color:#182b33;background:#fff;inset:0;margin:auto;max-width:440px;padding:0;position:fixed;width:calc(100% - 32px)}
dialog.jbm-dialogo::backdrop{background:rgba(16,37,45,.35);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}
dialog.jbm-dialogo[open]{animation:jbm-dlg-in .24s cubic-bezier(.2,.9,.3,1.1) both}
dialog.jbm-dialogo[open]::backdrop{animation:jbm-fondo-in .24s ease-out both}
dialog.jbm-dialogo.saliendo{animation:jbm-dlg-out .18s ease-in both;pointer-events:none}
dialog.jbm-dialogo.saliendo::backdrop{animation:jbm-fondo-out .18s ease-in both}
@keyframes jbm-dlg-in{from{opacity:0;transform:translateY(14px) scale(.94)}to{opacity:1;transform:none}}
@keyframes jbm-dlg-out{from{opacity:1;transform:none}to{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes jbm-fondo-in{from{opacity:0}to{opacity:1}}
@keyframes jbm-fondo-out{from{opacity:1}to{opacity:0}}
@media(prefers-reduced-motion:reduce){dialog.jbm-dialogo,dialog.jbm-dialogo::backdrop{animation-duration:.01ms!important}}
.jbm-dialogo form{display:grid;gap:16px;padding:28px}
.jbm-dialogo h2{font-size:20px;letter-spacing:-.01em;margin:0}
.jbm-dialogo p{color:#4d626b;line-height:1.5;margin:0;white-space:pre-line}
.jbm-dialogo label{display:grid;font-size:14px;font-weight:600;gap:6px}
.jbm-dialogo input{background:#f4f7f7;border:1px solid transparent;border-radius:10px;color:inherit;font:inherit;padding:11px 12px;transition:border-color .15s,box-shadow .15s}
.jbm-dialogo input:focus{background:#fff;border-color:#16723b;box-shadow:0 0 0 3px rgba(22,114,59,.18);outline:0}
.jbm-dialogo .jbm-error{color:#b3261e;font-size:14px}
.jbm-dialogo .jbm-acciones{display:flex;gap:10px;justify-content:flex-end;margin-top:6px}
.jbm-dialogo button{border:1px solid transparent;border-radius:10px;cursor:pointer;font:inherit;font-weight:600;padding:10px 18px;transition:transform .12s,box-shadow .15s,background-color .15s}
.jbm-dialogo button:active{transform:scale(.97)}
.jbm-dialogo .jbm-ok{background:#16723b;box-shadow:0 4px 12px rgba(22,114,59,.3);color:#fff}
.jbm-dialogo .jbm-ok:hover{box-shadow:0 6px 16px rgba(22,114,59,.4)}
.jbm-dialogo .jbm-ok.peligro{background:#c62828;box-shadow:0 4px 12px rgba(198,40,40,.3)}
.jbm-dialogo .jbm-ok.peligro:hover{box-shadow:0 6px 16px rgba(198,40,40,.4)}
.jbm-dialogo .jbm-no{background:#eef2f2;color:#33474f}
.jbm-dialogo .jbm-no:hover{background:#e2e8e8}
body[data-theme="dark"] dialog.jbm-dialogo{background:#1c1f24;box-shadow:0 24px 70px rgba(0,0,0,.6),0 0 0 1px #2f343b;color:#e8edf1}
body[data-theme="dark"] dialog.jbm-dialogo::backdrop{background:rgba(0,0,0,.55)}
body[data-theme="dark"] .jbm-dialogo p{color:#b8c2cc}
body[data-theme="dark"] .jbm-dialogo input{background:#262b32}
body[data-theme="dark"] .jbm-dialogo input:focus{background:#1c1f24}
body[data-theme="dark"] .jbm-dialogo .jbm-no{background:#2a2f36;color:#e8edf1}
body[data-theme="dark"] .jbm-dialogo .jbm-no:hover{background:#343a43}
body[data-theme="dark"] .jbm-dialogo .jbm-error{color:#ffc2c7}`;

    function abrir(op) {
        if (!document.getElementById("jbm-dialogos-css")) {
            const estilo = document.createElement("style");
            estilo.id = "jbm-dialogos-css";
            estilo.textContent = CSS;
            document.head.appendChild(estilo);
        }
        return new Promise(resolve => {
            const dlg = document.createElement("dialog");
            dlg.className = "jbm-dialogo";
            let cerrando = false;
            const cerrar = valor => {
                if (cerrando) return;
                cerrando = true;
                dlg.classList.add("saliendo");
                setTimeout(() => dlg.close(valor), 170);
            };
            dlg.setAttribute("role", op.campos?.length ? "dialog" : "alertdialog");
            const form = document.createElement("form");
            const h = document.createElement("h2");
            h.textContent = op.titulo || "Atención";
            form.appendChild(h);
            if (op.mensaje) {
                const p = document.createElement("p");
                p.textContent = op.mensaje;
                form.appendChild(p);
            }
            const inputs = {};
            (op.campos || []).forEach((c, i) => {
                const label = document.createElement("label");
                label.textContent = c.label;
                const input = document.createElement("input");
                input.type = c.type || "text";
                input.name = c.name;
                input.required = true;
                input.autocomplete = "off";
                if (i === 0) input.autofocus = true;
                label.appendChild(input);
                form.appendChild(label);
                inputs[c.name] = input;
            });
            const error = document.createElement("div");
            error.className = "jbm-error";
            error.hidden = true;
            form.appendChild(error);
            const acciones = document.createElement("div");
            acciones.className = "jbm-acciones";
            if (!op.soloAceptar) {
                const no = document.createElement("button");
                no.type = "button";
                no.className = "jbm-no";
                no.textContent = op.cancelar || "Cancelar";
                no.addEventListener("click", () => cerrar("cancel"));
                acciones.appendChild(no);
            }
            const ok = document.createElement("button");
            ok.type = "submit";
            ok.className = "jbm-ok" + (op.peligro ? " peligro" : "");
            ok.textContent = op.aceptar || "Aceptar";
            acciones.appendChild(ok);
            form.appendChild(acciones);
            form.addEventListener("submit", e => {
                e.preventDefault();
                const valores = {};
                for (const n in inputs) valores[n] = inputs[n].value;
                const msg = op.validar ? op.validar(valores) : "";
                if (msg) { error.textContent = msg; error.hidden = false; return; }
                dlg.returnValue = "ok";
                dlg.dataset.valores = JSON.stringify(valores);
                cerrar("ok");
            });
            dlg.addEventListener("cancel", e => { e.preventDefault(); cerrar("cancel"); });
            dlg.addEventListener("click", e => { if (e.target === dlg && !op.campos?.length) cerrar("cancel"); });
            dlg.appendChild(form);
            dlg.addEventListener("close", () => {
                const acepto = dlg.returnValue === "ok";
                const valores = acepto && dlg.dataset.valores ? JSON.parse(dlg.dataset.valores) : {};
                dlg.remove();
                resolve({ acepto, valores });
            });
            document.body.appendChild(dlg);
            dlg.showModal();
        });
    }

    window.JBM = window.JBM || {};
    JBM.confirmar = (mensaje, op = {}) => abrir({ titulo: "Confirmar", aceptar: "Confirmar", ...op, mensaje }).then(r => r.acepto);
    JBM.avisar = (mensaje, titulo = "Aviso") => abrir({ titulo, mensaje, soloAceptar: true }).then(() => undefined);
    JBM.pedir = op => abrir(op).then(r => (r.acepto ? r.valores : null));
})();
