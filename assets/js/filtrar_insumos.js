let numeroFiltroInsumos = 0;

function iniciarFiltroInsumos(contenedor) {
    const selector = contenedor.querySelector("select");
    const opciones = Array.from(selector.options).filter(opcion => opcion.value);
    const buscador = document.createElement("input");
    const resultados = document.createElement("div");
    const idResultados = `resultados-insumos-${++numeroFiltroInsumos}`;
    let indiceActivo = -1;

    buscador.type = "search";
    buscador.placeholder = "Seleccionar insumo...";
    buscador.autocomplete = "off";
    buscador.required = selector.required;
    buscador.setAttribute("aria-label", "Insumo");
    buscador.setAttribute("role", "combobox");
    buscador.setAttribute("aria-autocomplete", "list");
    buscador.setAttribute("aria-expanded", "false");
    buscador.setAttribute("aria-controls", idResultados);
    buscador.value = selector.value ? selector.selectedOptions[0].textContent : "";
    resultados.id = idResultados;
    resultados.className = "insumo-resultados";
    resultados.setAttribute("role", "listbox");
    resultados.hidden = true;
    contenedor.prepend(buscador);
    contenedor.append(resultados);
    selector.hidden = true;
    selector.required = false;

    function validarSeleccion() {
        buscador.setCustomValidity(selector.value ? "" : "Seleccioná un insumo de la lista.");
    }

    function posicionarResultados() {
        const rect = buscador.getBoundingClientRect();
        const espacioAbajo = window.innerHeight - rect.bottom;
        resultados.style.left = `${rect.left}px`;
        resultados.style.width = `${rect.width}px`;
        if (espacioAbajo < 120 && rect.top > espacioAbajo) {
            resultados.style.top = "auto";
            resultados.style.bottom = `${window.innerHeight - rect.top}px`;
            resultados.style.maxHeight = `${Math.max(0, Math.min(220, rect.top - 4))}px`;
        } else {
            resultados.style.top = `${rect.bottom}px`;
            resultados.style.bottom = "auto";
            resultados.style.maxHeight = `${Math.max(0, Math.min(220, espacioAbajo - 4))}px`;
        }
    }

    function cerrarResultados() {
        resultados.hidden = true;
        buscador.setAttribute("aria-expanded", "false");
        buscador.removeAttribute("aria-activedescendant");
        window.removeEventListener("scroll", posicionarResultados, true);
        window.removeEventListener("resize", posicionarResultados);
    }

    function actualizarActivo() {
        const botones = resultados.querySelectorAll("button");
        botones.forEach((boton, indice) => boton.setAttribute("aria-selected", String(indice === indiceActivo)));
        if (indiceActivo >= 0 && botones[indiceActivo]) {
            buscador.setAttribute("aria-activedescendant", botones[indiceActivo].id);
            botones[indiceActivo].scrollIntoView({ block: "nearest" });
        } else {
            buscador.removeAttribute("aria-activedescendant");
        }
    }

    function mostrarResultados(termino) {
        const normalizar = texto => texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
        const consulta = normalizar(termino.trim());
        const coincidencias = opciones.filter(opcion => normalizar(opcion.textContent).includes(consulta));
        resultados.replaceChildren();
        indiceActivo = -1;
        coincidencias.forEach((opcion, indice) => {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.id = `${idResultados}-${indice}`;
            boton.dataset.valor = opcion.value;
            boton.tabIndex = -1;
            boton.setAttribute("role", "option");
            boton.setAttribute("aria-selected", "false");
            boton.textContent = opcion.textContent;
            resultados.append(boton);
        });
        if (!coincidencias.length) {
            const mensaje = document.createElement("p");
            mensaje.textContent = "No se encontraron insumos.";
            resultados.append(mensaje);
        }
        resultados.hidden = false;
        buscador.setAttribute("aria-expanded", "true");
        buscador.removeAttribute("aria-activedescendant");
        posicionarResultados();
        window.addEventListener("scroll", posicionarResultados, true);
        window.addEventListener("resize", posicionarResultados);
    }

    function seleccionar(boton) {
        selector.value = boton.dataset.valor;
        buscador.value = selector.selectedOptions[0].textContent;
        validarSeleccion();
        cerrarResultados();
        selector.dispatchEvent(new Event("change", { bubbles: true }));
    }

    buscador.addEventListener("focus", () => mostrarResultados(selector.value ? "" : buscador.value));
    buscador.addEventListener("click", () => {
        if (resultados.hidden) mostrarResultados(selector.value ? "" : buscador.value);
    });
    buscador.addEventListener("input", () => {
        if (selector.value) {
            selector.value = "";
            selector.dispatchEvent(new Event("change", { bubbles: true }));
        }
        validarSeleccion();
        mostrarResultados(buscador.value);
    });
    buscador.addEventListener("keydown", evento => {
        const botones = resultados.querySelectorAll("button");
        if (evento.key === "ArrowDown" || evento.key === "ArrowUp") {
            evento.preventDefault();
            if (resultados.hidden) {
                mostrarResultados(selector.value ? "" : buscador.value);
                return;
            }
            if (!botones.length) return;
            indiceActivo = evento.key === "ArrowDown"
                ? Math.min(indiceActivo + 1, botones.length - 1)
                : indiceActivo < 0 ? botones.length - 1 : Math.max(indiceActivo - 1, 0);
            actualizarActivo();
        } else if (evento.key === "Enter") {
            evento.preventDefault();
            if (resultados.hidden) mostrarResultados(selector.value ? "" : buscador.value);
            else if (indiceActivo >= 0 && botones[indiceActivo]) seleccionar(botones[indiceActivo]);
        } else if (evento.key === "Escape") {
            cerrarResultados();
        }
    });
    buscador.addEventListener("blur", cerrarResultados);
    resultados.addEventListener("pointerdown", evento => evento.preventDefault());
    resultados.addEventListener("click", evento => {
        const boton = evento.target.closest("button[data-valor]");
        if (boton) seleccionar(boton);
    });
    validarSeleccion();
}
