(() => {
    const storageKey = "jbm-theme";
    const toggle = document.querySelector("[data-theme-toggle]");

    function applyTheme(theme) {
        const isDark = theme === "dark";
        document.body.dataset.theme = isDark ? "dark" : "light";
        if (toggle) {
            toggle.textContent = isDark ? "Tema: oscuro" : "Tema: claro";
            toggle.setAttribute("aria-pressed", String(isDark));
        }
    }

    let savedTheme = "light";
    try {
        savedTheme = localStorage.getItem(storageKey) === "dark" ? "dark" : "light";
    } catch (error) {
        savedTheme = "light";
    }
    applyTheme(savedTheme);

    if (toggle) {
        toggle.addEventListener("click", () => {
            const nextTheme = document.body.dataset.theme === "dark" ? "light" : "dark";
            applyTheme(nextTheme);
            try {
                localStorage.setItem(storageKey, nextTheme);
            } catch (error) {
            }
        });
    }
})();
