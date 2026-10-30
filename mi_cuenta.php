<?php
require_once 'includes/funciones.php';
verificar_sesion();
$rutaBase = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi cuenta</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <script src="assets/js/sidebar.js" defer></script>
    <link rel="stylesheet" href="assets/css/alta.css">
    <link rel="stylesheet" href="assets/css/mi_cuenta.css">
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle"><img src="assets/img/sidebar/dark-menu.svg" alt="Abrir menú"></div>
            <?php include_once 'includes/profile.php'; ?>
        </div>

        <main class="alta-content">
            <header class="page-heading">
                <h1>Mi cuenta</h1>
                <p class="mi-cuenta-descripcion">Modificá los datos asociados a tu usuario.</p>
            </header>
            <section class="alta-panel mi-cuenta-panel">
                <p id="mensajeCuenta" class="alta-success" role="status" aria-live="polite" data-testid="mensaje-cuenta"></p>

                <form id="formCuenta" class="alta-form mi-cuenta-form" data-testid="form-cuenta">
                    <label for="nombreCuenta">Nombre</label>
                    <input id="nombreCuenta" name="nombre" type="text" maxlength="100" required autocomplete="given-name">

                    <label for="apellidoCuenta">Apellido</label>
                    <input id="apellidoCuenta" name="apellido" type="text" maxlength="100" required autocomplete="family-name">

                    <label for="usuarioCuenta">Usuario</label>
                    <input id="usuarioCuenta" name="usuario" type="text" maxlength="50" required autocomplete="username">

                    <label for="correoCuenta">Correo electrónico</label>
                    <input id="correoCuenta" name="correo" type="email" maxlength="255" required autocomplete="email">

                    <div class="alta-actions">
                        <button id="guardarCuenta" type="submit" data-testid="guardar-cuenta">Guardar cambios</button>
                        <a href="index.php" class="alta-button-secondary">Volver al inicio</a>
                    </div>
                </form>
            </section>
        </main>
    </div>

<script>
const mensajeCuenta = document.getElementById('mensajeCuenta');
const formularioCuenta = document.getElementById('formCuenta');
const botonGuardarCuenta = document.getElementById('guardarCuenta');

async function leerRespuestaCuenta(respuesta) {
    let datos;
    try {
        datos = await respuesta.json();
    } catch (error) {
        throw new Error('No se pudo leer la respuesta del servidor.');
    }
    if (!respuesta.ok) throw new Error(datos.error || 'No se pudo completar la operación.');
    return datos;
}

function mostrarMensajeCuenta(texto, esError = false) {
    mensajeCuenta.textContent = texto;
    mensajeCuenta.classList.toggle('alta-error', esError);
    mensajeCuenta.classList.toggle('alta-success', !esError);
}

async function cargarCuenta() {
    try {
        const respuesta = await fetch('apis/api_cuenta.php', { cache: 'no-store' });
        const usuario = await leerRespuestaCuenta(respuesta);
        ['nombre', 'apellido', 'usuario', 'correo'].forEach(campo => {
            formularioCuenta.elements[campo].value = usuario[campo] || '';
        });
    } catch (error) {
        mostrarMensajeCuenta(error.message, true);
        botonGuardarCuenta.disabled = true;
    }
}

formularioCuenta.addEventListener('submit', async evento => {
    evento.preventDefault();
    if (!formularioCuenta.reportValidity()) return;

    botonGuardarCuenta.disabled = true;
    botonGuardarCuenta.textContent = 'Guardando...';
    try {
        const respuesta = await fetch('apis/api_cuenta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.fromEntries(new FormData(formularioCuenta)))
        });
        await leerRespuestaCuenta(respuesta);
        mostrarMensajeCuenta('Datos actualizados correctamente.');
    } catch (error) {
        mostrarMensajeCuenta(error.message, true);
    } finally {
        botonGuardarCuenta.disabled = false;
        botonGuardarCuenta.textContent = 'Guardar cambios';
    }
});

cargarCuenta();
</script>
</body>
</html>
