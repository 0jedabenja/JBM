const toggle = document.querySelector(".toggle");
const navigation = document.querySelector(".navigation");
const main = document.querySelector(".main");
const claveSidebar = "jbm-sidebar-colapsado";
const claveSidebarMovil = "jbm-sidebar-movil-cerrado";
const esMovil = window.matchMedia("(max-width: 768px)");

function guardarEstadoSidebar(colapsado) {
    const valor = String(colapsado);
    try { localStorage.setItem(claveSidebar, valor); } catch (error) {}
    try { sessionStorage.setItem(claveSidebar, valor); } catch (error) {}
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
    } catch (error) {}

    try {
        const valorSesion = sessionStorage.getItem(claveSidebar);
        if (valorSesion === "true" || valorSesion === "false") return valorSesion === "true";
    } catch (error) {}

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