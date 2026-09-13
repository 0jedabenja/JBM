<?php
include_once("includes/funciones.php");
verificar_sesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Empleados</title>
</head>
<body>

    <h2>Listado de Empleados</h2>

    <div class="controles">
        <input type="text" id="inputFiltro" placeholder="Ingrese empleado a buscar" onkeyup="buscarEmpleados()">
        <a href="altas/alta_empleado.php">+</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tablaEmpleados">
            <tr>
                <td colspan="6" style="text-align:center;">No se encontraron empleados.</td>
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
            cargarEmpleados();
        });

        function cargarEmpleados() {
            const filtro = document.getElementById("inputFiltro").value.trim();
            const tbody = document.getElementById("tablaEmpleados");

            fetch(`apis/api_empleados.php?pagina=${paginaActual}&limite=${limitePorPagina}&filtro=${encodeURIComponent(filtro)}`)
                .then(response => response.json())
                .then(empleados => {
                    tbody.innerHTML = "";

                    if (empleados.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No se encontraron empleados.</td></tr>`;
                        document.getElementById("btnSiguiente").disabled = true;
                        return;
                    }

                    empleados.forEach(empleado => {
                        const tr = document.createElement("tr");

                        tr.innerHTML = `
                            <td>${escapeHTML(empleado.id_empleado)}</td>
                            <td>${escapeHTML(empleado.nombre)}</td>
                            <td>${escapeHTML(empleado.apellido)}</td>
                            <td>${escapeHTML(empleado.usuario)}</td>
                            <td>${escapeHTML(empleado.rol)}</td>
                            <td>
                                <img src="imagenes/acciones/borrar.png" class="accion" title="Eliminar" onclick="eliminarEmpleado(${empleado.id_empleado})">
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    document.getElementById("btnAnterior").disabled = (paginaActual === 1);
                    document.getElementById("btnSiguiente").disabled = (empleados.length < limitePorPagina);
                    document.getElementById("infoPagina").textContent = `Página ${paginaActual}`;
                })
                .catch(error => {
                    console.error("Error al cargar los empleados:", error);
                });
        }

        function cambiarPagina(delta) {
            paginaActual += delta;
            cargarEmpleados();
        }

        function buscarEmpleados() {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                paginaActual = 1;
                cargarEmpleados();
            }, 300);
        }

        function eliminarEmpleado(id) {
            if (confirm("¿Está seguro de que desea eliminar este empleado?")) {
                fetch(`apis/eliminar.php?tipo=empleado&id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("No se pudo desactivar el empleado.");
                        }
                        return response.json();
                    })
                    .then(() => {
                        cargarEmpleados();
                    })
                    .catch(error => {
                        console.error("Error al desactivar el empleado:", error);
                        alert("No se pudo desactivar el empleado.");
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
