const toggle = document.querySelector(".toggle");
const navigation = document.querySelector(".navigation");
const main = document.querySelector(".main");
const claveSidebar = "jbm-sidebar-colapsado";
const claveSidebarMovil = "jbm-sidebar-movil-cerrado";
const esMovil = window.matchMedia("(max-width: 768px)");

function guardarEstadoSidebar(colapsado) {
    const valor = String(colapsado);
    try {
        localStorage.setItem(claveSidebar, valor);
    } catch (error) {
        console.warn("No se pudo guardar la preferencia del menú lateral en localStorage.", error);
    }
    try {
        document.cookie = `${claveSidebar}=${valor}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === "https:" ? "; Secure" : ""}`;
    } catch (error) {
        console.warn("No se pudo guardar la preferencia del menú lateral en una cookie.", error);
    }
    try {
        sessionStorage.setItem(claveSidebar, valor);
    } catch (error) {
        console.warn("No se pudo guardar la preferencia del menú lateral en sessionStorage.", error);
    }
}

function aplicarEstadoSidebar(colapsado) {
    navigation?.classList.toggle("active", colapsado);
    main?.classList.toggle("active", colapsado);
    toggle?.setAttribute("aria-expanded", String(!colapsado));
}

function obtenerEstadoSidebar() {
    try {
        const valorLocal = localStorage.getItem(claveSidebar);
        if (valorLocal === "true" || valorLocal === "false") return valorLocal === "true";
    } catch (error) {
        console.warn("No se pudo leer la preferencia del menú lateral de localStorage.", error);
    }

    const cookie = document.cookie
        .split("; ")
        .find(parte => parte.startsWith(`${claveSidebar}=`))
        ?.split("=")[1];
    if (cookie === "true" || cookie === "false") return cookie === "true";

    try {
        const valorSesion = sessionStorage.getItem(claveSidebar);
        if (valorSesion === "true" || valorSesion === "false") return valorSesion === "true";
    } catch (error) {
        console.warn("No se pudo leer la preferencia del menú lateral de sessionStorage.", error);
    }

    return false;
}

let colapsado;
if (esMovil.matches) {
    let cerradoMovil = true;
    try {
        cerradoMovil = sessionStorage.getItem(claveSidebarMovil) !== "false";
    } catch (error) {}
    colapsado = cerradoMovil;
} else {
    colapsado = obtenerEstadoSidebar();
    guardarEstadoSidebar(colapsado);
}
aplicarEstadoSidebar(colapsado);

if (toggle && navigation && main) {
    toggle.addEventListener("click", () => {
        colapsado = !navigation.classList.contains("active");
        aplicarEstadoSidebar(colapsado);
        if (esMovil.matches) {
            try { sessionStorage.setItem(claveSidebarMovil, String(colapsado)); } catch (error) {}
        } else {
            guardarEstadoSidebar(colapsado);
        }
    });
}

esMovil.addEventListener?.("change", evento => {
    if (!evento.matches) {
        aplicarEstadoSidebar(obtenerEstadoSidebar());
    } else {
        aplicarEstadoSidebar(true);
    }
});