(() => {
    const sonidos = {
        pedido: "assets/sounds/pedido-creado.mp3",
        cocina: "assets/sounds/pedido-cocina.mp3",
        listo: "assets/sounds/pedido-listo.mp3",
        cobro: "assets/sounds/pedido-cobrar.mp3",
        cobrado: "assets/sounds/pedido-cobrado.mp3"
    };
    const estadoKey = "jbm:notificaciones-silenciadas";
    let audio = null;
    let intentandoArchivo = new Set();

    function activadas() {
        return localStorage.getItem(estadoKey) !== "1";
    }

    function desbloquear() {
        if (!audio) audio = new Audio();
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission().catch(() => {});
        }
    }

    function sintetizar() {
        const contextoClase = window.AudioContext || window.webkitAudioContext;
        if (!contextoClase) return;
        const contexto = audioContext(contextoClase);
        if (contexto.state !== "running") return;
        const inicio = contexto.currentTime;
        [659, 880].forEach((frecuencia, indice) => {
            const oscilador = contexto.createOscillator();
            const ganancia = contexto.createGain();
            const momento = inicio + indice * 0.18;
            oscilador.frequency.value = frecuencia;
            ganancia.gain.setValueAtTime(0.001, momento);
            ganancia.gain.exponentialRampToValueAtTime(0.16, momento + 0.02);
            ganancia.gain.exponentialRampToValueAtTime(0.001, momento + 0.22);
            oscilador.connect(ganancia).connect(contexto.destination);
            oscilador.start(momento);
            oscilador.stop(momento + 0.24);
        });
    }

    let contextoCreado = null;
    function audioContext(contextoClase) {
        contextoCreado ||= new contextoClase();
        contextoCreado.resume().catch(() => {});
        return contextoCreado;
    }

    async function reproducir(tipo) {
        if (!activadas()) return;
        const archivo = sonidos[tipo];
        if (!archivo || intentandoArchivo.has(tipo)) {
            sintetizar();
            return;
        }
        intentandoArchivo.add(tipo);
        try {
            const sonido = new Audio(archivo);
            sonido.volume = 1;
            await sonido.play();
        } catch (error) {
            sintetizar();
        } finally {
            intentandoArchivo.delete(tipo);
        }
    }

    function navegador(titulo, cuerpo) {
        if (!activadas() || !("Notification" in window)) return;
        if (Notification.permission !== "granted") return;
        new Notification(titulo, { body: cuerpo, icon: "assets/img/favicon.svg" });
    }

    function actualizarBoton() {
        const boton = document.getElementById("botonAlertasJBM");
        if (!boton) return;
        const silenciadas = !activadas();
        boton.textContent = silenciadas ? "🔕 Alertas silenciadas" : "🔔 Alertas activas";
        boton.setAttribute("aria-label", silenciadas ? "Activar notificaciones" : "Silenciar notificaciones");
        boton.setAttribute("aria-pressed", String(silenciadas));
    }

    function crearBoton() {
        if (document.getElementById("botonAlertasJBM")) return;
        const boton = document.createElement("button");
        boton.id = "botonAlertasJBM";
        boton.type = "button";
        boton.className = "jbm-alertas-control";
        boton.addEventListener("click", async () => {
            desbloquear();
            if (activadas()) {
                if (!window.confirm("¿Estás seguro que quieres silenciar las notificaciones?")) return;
                localStorage.setItem(estadoKey, "1");
            } else {
                localStorage.removeItem(estadoKey);
                if ("Notification" in window && Notification.permission === "default") await Notification.requestPermission();
                reproducir("pedido");
            }
            actualizarBoton();
        });
        document.body.appendChild(boton);
        actualizarBoton();
    }

    function instalarEstilos() {
        if (document.getElementById("jbm-alertas-estilos")) return;
        const estilos = document.createElement("style");
        estilos.id = "jbm-alertas-estilos";
        estilos.textContent = `.jbm-alertas-control{position:fixed;right:18px;bottom:18px;z-index:9999;border:0;border-radius:999px;padding:11px 16px;background:#1f2937;color:#fff;font-weight:700;box-shadow:0 4px 18px rgba(0,0,0,.25);cursor:pointer}`;
        document.head.appendChild(estilos);
    }

    document.addEventListener("DOMContentLoaded", () => {
        instalarEstilos();
        crearBoton();
    });
    document.addEventListener("pointerdown", desbloquear, { once: true, capture: true });
    document.addEventListener("keydown", desbloquear, { once: true, capture: true });

    window.AlertasJBM = { reproducir, navegador, desbloquear, activadas };
})();
