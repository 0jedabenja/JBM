(() => {
    if (window.AlertasJBM) return;
    const urlScript = document.currentScript?.src || "";
    const urlApiPedidos = urlScript ? new URL("../../apis/api_pedidos.php?avisos=levantar", urlScript).href : "apis/api_pedidos.php?avisos=levantar";
    const urlPaginaPedidos = urlScript ? new URL("../../pedidos.php", urlScript).href : "pedidos.php";
    const avisadosKey = "jbm:pedidos-levantar-avisados";
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
                if (!await window.JBM.confirmar("¿Estás seguro que quieres silenciar las notificaciones?", { aceptar: "Silenciar" })) return;
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

    function mostrarAvisoLevantar(texto) {
        const aviso = document.createElement("a");
        aviso.className = "jbm-aviso-levantar";
        aviso.href = urlPaginaPedidos;
        aviso.setAttribute("role", "alert");
        aviso.textContent = texto;
        document.body.appendChild(aviso);
        window.setTimeout(() => aviso.remove(), 15000);
    }

    async function revisarPedidosParaLevantar() {
        if (document.visibilityState !== "visible") return;
        try {
            const respuesta = await fetch(urlApiPedidos, { cache: "no-store" });
            if (!respuesta.ok) return;
            const pedidos = (await respuesta.json()).pedidos || [];
            let avisados = [];
            try { avisados = JSON.parse(localStorage.getItem(avisadosKey) || "[]"); } catch (error) { avisados = []; }
            localStorage.setItem(avisadosKey, JSON.stringify(pedidos.map(p => Number(p.id_pedido))));
            pedidos.filter(p => !avisados.includes(Number(p.id_pedido))).forEach(p => {
                const texto = `Tu pedido #${p.id_pedido}${p.mesa ? ` (mesa ${p.mesa})` : ""} está listo para levantar en cocina.`;
                mostrarAvisoLevantar(texto);
                reproducir("listo");
                navegador("Pedido para levantar", texto);
            });
        } catch (error) {
            console.warn("No se pudo consultar los pedidos para levantar.", error);
        }
    }

    function instalarEstilos() {
        if (document.getElementById("jbm-alertas-estilos")) return;
        const estilos = document.createElement("style");
        estilos.id = "jbm-alertas-estilos";
        estilos.textContent = `.jbm-alertas-control{position:fixed;right:18px;bottom:18px;z-index:9999;border:0;border-radius:999px;padding:11px 16px;background:#1f2937;color:#fff;font-weight:700;box-shadow:0 4px 18px rgba(0,0,0,.25);cursor:pointer}.jbm-aviso-levantar{position:fixed;right:18px;top:18px;z-index:10000;max-width:360px;padding:14px 18px;border-radius:12px;background:#b45309;color:#fff;font-weight:700;text-decoration:none;box-shadow:0 6px 22px rgba(0,0,0,.3)}.jbm-aviso-levantar~.jbm-aviso-levantar{top:90px}`;
        document.head.appendChild(estilos);
    }

    document.addEventListener("DOMContentLoaded", () => {
        instalarEstilos();
        crearBoton();
        if (window.JBM_AVISOS_LEVANTAR) {
            revisarPedidosParaLevantar();
            window.setInterval(revisarPedidosParaLevantar, 4000);
        }
    });
    document.addEventListener("pointerdown", desbloquear, { once: true, capture: true });
    document.addEventListener("keydown", desbloquear, { once: true, capture: true });

    window.AlertasJBM = { reproducir, navegador, desbloquear, activadas };
})();
