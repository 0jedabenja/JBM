<?php
include_once("includes/funciones.php");
/*verificar_sesion();*/
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="assets/js/sidebar.js" defer></script>
    <style>
    .navigation ul li:nth-child(6) {
        background-color: #fff;
    }

    .navigation ul li:nth-child(6) a {
        color: #001f47;
    }

    .navigation ul li:nth-child(6) a::before {
        content: "";
        position: absolute;
        right: 0;
        top: -50px;
        width: 50px;
        height: 50px;
        background-color: transparent;
        border-radius: 50%;
        box-shadow: 35px 35px 0 10px #fff;
        pointer-events: none;
    }

    .navigation ul li:nth-child(6) a::after {
        content: "";
        position: absolute;
        right: 0;
        bottom: -50px;
        width: 50px;
        height: 50px;
        background-color: transparent;
        border-radius: 50%;
        box-shadow: 35px -35px 0 10px #fff;
        pointer-events: none;
    }

    .navigation ul li:nth-child(6) a .icon img {
        content: url('assets/img/sidebar/theme-box.svg');
    }
    </style>
</head>

<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle">
                <img src="assets/img/sidebar/dark-menu.svg">
            </div>

            <div class="search-group">
                <div class="search">
                    <label>
                        <input type="text" placeholder="Buscar aquí" id="search-input">
                        <img src="assets/img/sidebar/dark-search.svg">
                    </label>
                </div>

                <div class="filter">
                    <label>
                        <img src="assets/img/sidebar/dark-filter.svg">
                        <select id="filter-select">
                            <option value="1">Filtro</option>
                            <option value="2">Opción 2</option>
                        </select>
                    </label>
                </div>
            </div>

            <?php include_once 'includes/profile.php'; ?>
        </div>

        <div class="contenido">
            <div class="titulo">
                <h2>Listado de Insumos</h2>
                <a href="altas/alta_insumo.php" class="btn-agregar">+</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Unidad de Medida</th>
                        <th>Cant. Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Costo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaInsumos">
                    <tr>
                        <td colspan="7" style="text-align:center;">No se encontraron insumos.</td>
                    </tr>
                </tbody>
            </table>

            <div class="paginacion">
                <button id="btnAnterior" onclick="cambiarPagina(-1)">Anterior</button>
                <span id="infoPagina">Página 1</span>
                <button id="btnSiguiente" onclick="cambiarPagina(1)">Siguiente</button>
            </div>
        </div>
    </div>

    <script>
        let paginaActual = 1;
        const limitePorPagina = 15;
        let timeoutBusqueda = null;

        document.addEventListener("DOMContentLoaded", () => {
            cargarInsumos();
            document.getElementById("search-input").addEventListener("keyup", buscarInsumos);
        });

        function cargarInsumos() {
            const filtro = document.getElementById("search-input").value.trim();
            const tbody = document.getElementById("tablaInsumos");

            fetch(`apis/api_insumos.php?pagina=${paginaActual}&limite=${limitePorPagina}&filtro=${encodeURIComponent(filtro)}`)
                .then(response => response.json())
                .then(insumos => {
                    tbody.innerHTML = "";

                    if (insumos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No se encontraron insumos.</td></tr>`;
                        document.getElementById("btnAnterior").disabled = (paginaActual === 1);
                        document.getElementById("btnSiguiente").disabled = true;
                        document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                        return;
                    }

                    insumos.forEach(insumo => {
                        const tr = document.createElement("tr");

                        tr.innerHTML = `
                            <td>${escapeHTML(insumo.id_insumo)}</td>
                            <td>${escapeHTML(insumo.nombre)}</td>
                            <td>${escapeHTML(insumo.unidad)}</td>
                            <td>${escapeHTML(insumo.cantidad_actual)}</td>
                            <td>${escapeHTML(insumo.stock_minimo)}</td>
                            <td>$ ${escapeHTML(insumo.costo)}</td>
                            <td>
                                <a href="modificar_insumo.php?id=${insumo.id_insumo}" title="Editar">
                                    <img src="imagenes/acciones/editar.png" class="accion" alt="Editar">
                                </a>
                                <img src="imagenes/acciones/borrar.png" class="accion" title="Eliminar" alt="Eliminar" onclick="eliminarInsumo(${insumo.id_insumo})">
                            </td>
                        `;

                        tbody.appendChild(tr);
                    });

                    document.getElementById("btnAnterior").disabled = (paginaActual === 1);
                    document.getElementById("btnSiguiente").disabled = (insumos.length < limitePorPagina);
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                })
                .catch(error => {
                    console.error("Error al cargar los insumos:", error);
                });
        }

        function cambiarPagina(delta) {
            paginaActual += delta;

            if (paginaActual < 1) {
                paginaActual = 1;
            }

            cargarInsumos();
        }

        function buscarInsumos() {
            clearTimeout(timeoutBusqueda);

            timeoutBusqueda = setTimeout(() => {
                paginaActual = 1;
                cargarInsumos();
            }, 300);
        }

        function eliminarInsumo(id) {
            if (confirm("¿Está seguro de que desea eliminar este insumo?")) {
                fetch(`apis/eliminar.php?tipo=insumo&id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("No se pudo desactivar el insumo.");
                        }

                        return response.json();
                    })
                    .then(() => {
                        cargarInsumos();
                    })
                    .catch(error => {
                        console.error("Error al desactivar el insumo:", error);
                        alert("No se pudo desactivar el insumo.");
                    });
            }
        }

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';

            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>

</html>
