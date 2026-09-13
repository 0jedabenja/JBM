<?php
include_once("includes/funciones.php");
verificar_sesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Clientes</title>
</head>
<body>

    <h2>Listado de Clientes</h2>

    <div class="controles">
        <input type="text" id="inputFiltro" placeholder="Ingrese cliente a buscar" onkeyup="buscarClientes()">
        <a href="altas/alta_cliente.php">+</a>
    </div>

    <table>
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
        <button id="btnAnterior" onclick="cambiarPagina(-1)">Anterior</button>
        <span id="infoPagina">Página 1</span>
        <button id="btnSiguiente" onclick="cambiarPagina(1)">Siguiente</button>
    </div>

    <script>
        let paginaActual = 1;
        const limitePorPagina = 15;
        let timeoutBusqueda = null;

        document.addEventListener("DOMContentLoaded", () => {
            cargarClientes();
        });

        function cargarClientes() {
            const filtro = document.getElementById("inputFiltro").value.trim();
            const tbody = document.getElementById("tablaClientes");

            fetch(`apis/api_clientes.php?pagina=${paginaActual}&limite=${limitePorPagina}&filtro=${encodeURIComponent(filtro)}`)
                .then(response => response.json())
                .then(clientes => {
                    tbody.innerHTML = "";

                    if (clientes.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No se encontraron clientes.</td></tr>`;
                        document.getElementById("btnSiguiente").disabled = true;
                        return;
                    }

                    clientes.forEach(cliente => {
                        const tr = document.createElement("tr");

                        tr.innerHTML = `
                            <td>${escapeHTML(cliente.id_cliente)}</td>
                            <td>${escapeHTML(cliente.nombre)}</td>
                            <td>${escapeHTML(cliente.apellido)}</td>
                            <td>${escapeHTML(cliente.telefono)}</td>
                            <td>${escapeHTML(cliente.direccion)}</td>
                            <td>
                                <img src="imagenes/acciones/borrar.png" class="accion" title="Eliminar" onclick="eliminarCliente(${cliente.id_cliente})">
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    document.getElementById("btnAnterior").disabled = (paginaActual === 1);
                    document.getElementById("btnSiguiente").disabled = (clientes.length < limitePorPagina);
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                })
                .catch(error => {
                    console.error("Error al cargar los clientes:", error);
                });
        }

        function cambiarPagina(delta) {
            paginaActual += delta;
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
            if (confirm("¿Está seguro de que desea eliminar este cliente?")) {
                fetch(`apis/eliminar.php?tipo=cliente&id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("No se pudo desactivar el cliente.");
                        }
                        return response.json();
                    })
                    .then(() => {
                        cargarClientes();
                    })
                    .catch(error => {
                        console.error("Error al desactivar el cliente:", error);
                        alert("No se pudo desactivar el cliente.");
                    });
            }
        }

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/\"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
