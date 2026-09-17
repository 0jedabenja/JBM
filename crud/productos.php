<?php
include_once("includes/funciones.php");
verificar_sesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Productos</title>
</head>
<body>

    <h2>Listado de Productos</h2>

    <div class="controles">
        <input type="text" id="inputFiltro" placeholder="Ingrese producto a buscar" onkeyup="buscarProductos()">
        <a href="altas/alta_producto.php">+</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Precio</th>
                <th>Categoría</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tablaProductos">
            <tr>
                <td colspan="5" style="text-align:center;">No se encontraron productos.</td>
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
            cargarProductos();
        });

        function cargarProductos() {
            const filtro = document.getElementById("inputFiltro").value.trim();
            const tbody = document.getElementById("tablaProductos");

            fetch(`apis/api_productos.php?pagina=${paginaActual}&limite=${limitePorPagina}&filtro=${encodeURIComponent(filtro)}`)
                .then(response => response.json())
                .then(productos => {
                    tbody.innerHTML = "";

                    if (productos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No se encontraron productos.</td></tr>`;
                        document.getElementById("btnSiguiente").disabled = true;
                        return;
                    }

                    productos.forEach(producto => {
                        const tr = document.createElement("tr");

                        tr.innerHTML = `
                            <td>${escapeHTML(producto.id_producto)}</td>
                            <td>${escapeHTML(producto.nombre)}</td>
                            <td>$ ${escapeHTML(producto.precio)}</td>
                            <td>${escapeHTML(producto.categoria)}</td>
                            <td>
                                <a href="modificar_productos.php?id=${producto.id_producto}" title="Editar">
                                    <img src="imagenes/acciones/editar.png" class="accion" alt="Editar">
                                </a>
                                <img src="imagenes/acciones/borrar.png" class="accion" title="Eliminar" onclick="eliminarProducto(${producto.id_producto})">
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    document.getElementById("btnAnterior").disabled = (paginaActual === 1);
                    document.getElementById("btnSiguiente").disabled = (productos.length < limitePorPagina);
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                })
                .catch(error => {
                    console.error("Error al cargar los productos:", error);
                });
        }

        function cambiarPagina(delta) {
            paginaActual += delta;
            cargarProductos();
        }

        function buscarProductos() {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                paginaActual = 1;
                cargarProductos();
            }, 300);
        }

        function eliminarProducto(id) {
            if (confirm("¿Está seguro de que desea eliminar este producto?")) {
                fetch(`apis/eliminar.php?tipo=producto&id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("No se pudo desactivar el producto.");
                        }
                        return response.json();
                    })
                    .then(() => {
                        cargarProductos();
                    })
                    .catch(error => {
                        console.error("Error al desactivar el producto:", error);
                        alert("No se pudo desactivar el producto.");
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