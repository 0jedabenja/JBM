<?php
include_once("includes/funciones.php");
verificar_sesion();
verificar_permiso("clientes");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes</title>
    <script src="assets/js/sidebar.js" defer></script>
    <style>
    .navigation ul li:nth-child(5) {
        background-color: #fff;
    }

    .navigation ul li:nth-child(5) a {
        color: #001f47;
    }

    .navigation ul li:nth-child(5) a::before {
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

    .navigation ul li:nth-child(5) a::after {
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

    .navigation ul li:nth-child(5) a .icon img {
        content: url('assets/img/sidebar/theme-person.svg');
    }

    .clientes-content {
        padding: 20px 30px;
    }

    .clientes-content h2 {
        color: #001f47;
        margin-bottom: 20px;
    }

    .clientes-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 20px;
    }

    .clientes-actions a {
        color: #fff;
        background-color: #001f47;
        border-radius: 50%;
        font-size: 1.5rem;
        line-height: 35px;
        text-align: center;
        text-decoration: none;
        width: 35px;
        height: 35px;
    }

    .clientes-table {
        border-collapse: collapse;
        width: 100%;
    }

    .clientes-table th,
    .clientes-table td {
        border-bottom: 1px solid #ddd;
        padding: 12px;
        text-align: left;
    }

    .clientes-table th {
        color: #001f47;
    }

    .clientes-table .accion {
        background: none;
        border: 0;
        color: #001f47;
        cursor: pointer;
        font-weight: bold;
        padding: 0;
    }

    .paginacion {
        align-items: center;
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 20px;
    }

    .paginacion button {
        background-color: #001f47;
        border: 0;
        border-radius: 4px;
        color: #fff;
        cursor: pointer;
        padding: 8px 14px;
    }

    .paginacion button:disabled {
        cursor: not-allowed;
        opacity: 0.5;
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
                    <input type="text" placeholder="Buscar aquí" id="inputFiltro">
                    <img src="assets/img/sidebar/dark-search.svg">
                </label>
            </div>
            <div class="filter">
                <label>
                    <img src="assets/img/sidebar/dark-filter.svg">
                    <select class="filter-select" id="atributoFiltro">
                        <option value="todos">Todos los atributos</option>
                        <option value="id_cliente">ID</option>
                        <option value="nombre">Nombre</option>
                        <option value="apellido">Apellido</option>
                        <option value="telefono">Teléfono</option>
                        <option value="direccion">Dirección</option>
                    </select>
                </label>
            </div>
            </div>

            <?php include_once 'includes/profile.php'; ?>
        </div>

        <main class="clientes-content">
            <h2>Listado de Clientes</h2>

            <div class="clientes-actions">
                <a href="altas/alta_cliente.php" title="Cargar cliente">+</a>
            </div>

            <table class="clientes-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Teléfono</th>
                        <th>Dirección</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaClientes">
                    <tr>
                        <td colspan="6" style="text-align:center;">No se encontraron clientes.</td>
                    </tr>
                </tbody>
            </table>

            <div class="paginacion">
                <button type="button" id="btnAnterior">Anterior</button>
                <span id="infoPagina">Página 1</span>
                <button type="button" id="btnSiguiente">Siguiente</button>
            </div>
        </main>
    </div>

    <script>
        let paginaActual = 1;
        const limitePorPagina = 15;
        let timeoutBusqueda = null;

        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("inputFiltro").addEventListener("input", buscarClientes);
            document.getElementById("atributoFiltro").addEventListener("change", buscarClientes);
            document.getElementById("btnAnterior").addEventListener("click", () => cambiarPagina(-1));
            document.getElementById("btnSiguiente").addEventListener("click", () => cambiarPagina(1));
            cargarClientes();
        });

        function cargarClientes() {
            const filtro = document.getElementById("inputFiltro").value.trim();
            const atributo = document.getElementById("atributoFiltro").value;
            const tbody = document.getElementById("tablaClientes");
            const url = `apis/api_clientes.php?pagina=${paginaActual}&limite=${limitePorPagina}&campo=${encodeURIComponent(atributo)}&filtro=${encodeURIComponent(filtro)}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("No se pudieron cargar los clientes.");
                    }
                    return response.json();
                })
                .then(clientes => {
                    tbody.innerHTML = "";

                    if (clientes.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No se encontraron clientes.</td></tr>`;
                    } else {
                        clientes.forEach(cliente => {
                            const fila = document.createElement("tr");
                            fila.innerHTML = `
                                <td>${escapeHTML(cliente.id_cliente)}</td>
                                <td>${escapeHTML(cliente.nombre)}</td>
                                <td>${escapeHTML(cliente.apellido)}</td>
                                <td>${escapeHTML(cliente.telefono)}</td>
                                <td>${escapeHTML(cliente.direccion)}</td>
                                <td>
                                    <a href="modificar_cliente.php?id=${encodeURIComponent(cliente.id_cliente)}">Editar</a>
                                    <button type="button" class="accion" title="Desactivar" onclick="eliminarCliente(${cliente.id_cliente})">Desactivar</button>
                                </td>`;
                            tbody.appendChild(fila);
                        });
                    }

                    document.getElementById("btnAnterior").disabled = paginaActual === 1;
                    document.getElementById("btnSiguiente").disabled = clientes.length < limitePorPagina;
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                })
                .catch(error => {
                    console.error("Error al cargar los clientes:", error);
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No se pudieron cargar los clientes.</td></tr>`;
                });
        }

        function cambiarPagina(delta) {
            paginaActual = Math.max(1, paginaActual + delta);
            cargarClientes();
        }

        function buscarClientes() {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                paginaActual = 1;
                cargarClientes();
            }, 300);
        }

        function eliminarCliente(id) {
            if (!confirm("¿Está seguro de que desea desactivar este cliente?")) {
                return;
            }

            fetch(`apis/eliminar.php?tipo=cliente&id=${encodeURIComponent(id)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("No se pudo desactivar el cliente.");
                    }
                    return response.json();
                })
                .then(() => cargarClientes())
                .catch(error => {
                    console.error("Error al desactivar el cliente:", error);
                    alert("No se pudo desactivar el cliente.");
                });
        }

        function escapeHTML(valor) {
            if (valor === null || valor === undefined) {
                return "";
            }

            return String(valor)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/\"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>

</html>