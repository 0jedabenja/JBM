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
