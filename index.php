<?php
include_once "includes/funciones.php";
verificar_permiso("inicio");
$rolActual = obtener_rol_sesion();
$insumosBajoStock = array();

if ($rolActual === "administrador") {
    include_once "includes/conexionBD.php";
    $stmtStock = mysqli_prepare($conexion, "SELECT i.id_insumo, i.nombre, i.cantidad_actual, i.stock_minimo, m.unidad
                                            FROM Insumo i
                                            LEFT JOIN Medida m ON m.id_medida = i.id_medida
                                            WHERE i.activo = TRUE AND i.cantidad_actual < i.stock_minimo
                                            ORDER BY (i.stock_minimo - i.cantidad_actual) DESC, i.nombre ASC");
    if ($stmtStock) {
        mysqli_stmt_execute($stmtStock);
        $resultadoStock = mysqli_stmt_get_result($stmtStock);
        while ($insumo = mysqli_fetch_assoc($resultadoStock)) {
            $insumosBajoStock[] = $insumo;
        }
        mysqli_stmt_close($stmtStock);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="assets/js/sidebar.js" defer></script>
    <style>
    .inicio-content {
        padding: 30px;
    }

    .inicio-content h1 {
        color: #001f47;
        margin-bottom: 8px;
    }

    .stock-alerta {
        background: #fff8e1;
        border: 1px solid #e0ad35;
        border-left: 5px solid #d99018;
        border-radius: 8px;
        margin-top: 25px;
        max-width: 900px;
        padding: 20px;
    }

    .stock-alerta h2 {
        color: #765400;
        margin-top: 0;
    }

    .stock-tabla {
        border-collapse: collapse;
        margin-top: 15px;
        width: 100%;
    }

    .stock-tabla th,
    .stock-tabla td {
        border-bottom: 1px solid #ead9a8;
        padding: 10px;
        text-align: left;
    }

    .stock-tabla th {
        color: #765400;
    }

    .conteo-inicio {
        background: #001f47;
        border-radius: 6px;
        color: #fff;
        display: inline-block;
        margin-top: 16px;
        padding: 10px 16px;
        text-decoration: none;
    }

    .stock-vacio {
        background: #eef8f0;
        border: 1px solid #8cc799;
        border-radius: 8px;
        color: #216b2e;
        margin-top: 25px;
        max-width: 900px;
        padding: 18px;
    }

    .navigation ul li:nth-child(1) {
        background-color: #fff;
    }

    .navigation ul li:nth-child(1) a {
        color: #001f47;
    }

    .navigation ul li:nth-child(1) a::before {
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

    .navigation ul li:nth-child(1) a::after {
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

    .navigation ul li:nth-child(1) a .icon img {
        content: url('assets/img/sidebar/theme-home.svg');
    }
    </style>

<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle">
                <img src="assets/img/sidebar/dark-menu.svg">
            </div>

            <?php include_once 'includes/profile.php'; ?>
        </div>

            <main class="inicio-content">
                <h1>Inicio</h1>
                <?php if ($rolActual === "administrador"): ?>
                    <a class="conteo-inicio" href="conteos.php">Iniciar conteo de insumos</a>
                    <?php if (count($insumosBajoStock) > 0): ?>
                        <section class="stock-alerta" aria-labelledby="titulo-stock">
                            <h2 id="titulo-stock">Insumos con stock bajo</h2>
                            <p>Estos insumos están por debajo del stock de aviso.</p>
                            <table class="stock-tabla">
                                <thead>
                                    <tr>
                                        <th>Insumo</th>
                                        <th>Stock actual</th>
                                        <th>Stock de aviso</th>
                                        <th>Unidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($insumosBajoStock as $insumo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($insumo["nombre"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($insumo["cantidad_actual"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($insumo["stock_minimo"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($insumo["unidad"] ?? "-", ENT_QUOTES, "UTF-8"); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </section>
                    <?php else: ?>
                        <p class="stock-vacio">No hay insumos por debajo del stock de aviso.</p>
                    <?php endif; ?>
                    <details>
                        <summary>Auditoría del sistema</summary>
                        <p>Registro de operaciones importantes. Se muestran 100 eventos por página.</p>
                        <table>
                            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr></thead>
                            <tbody id="filasAuditoria"><tr><td colspan="6">Abrí esta sección para cargar los registros.</td></tr></tbody>
                        </table>
                        <p id="mensajeAuditoria" role="status"></p>
                        <button id="auditoriaAnterior" type="button" disabled>Anterior</button>
                        <span id="paginaAuditoria">Página 1</span>
                        <button id="auditoriaSiguiente" type="button" disabled>Siguiente</button>
                    </details>
                <?php endif; ?>
            </main>
    </div>
    <?php if ($rolActual === "administrador"): ?>
    <script>
    let paginaAuditoriaActual = 1;
    let auditoriaCargada = false;
    const seccionAuditoria = document.querySelector("main details");
    seccionAuditoria.addEventListener("toggle", () => {
        if (seccionAuditoria.open && !auditoriaCargada) cargarAuditoria();
    });
    document.getElementById("auditoriaAnterior").addEventListener("click", () => {
        if (paginaAuditoriaActual > 1) { paginaAuditoriaActual--; cargarAuditoria(); }
    });
    document.getElementById("auditoriaSiguiente").addEventListener("click", () => {
        if (document.getElementById("auditoriaSiguiente").dataset.hayMas === "true") { paginaAuditoriaActual++; cargarAuditoria(); }
    });
    async function cargarAuditoria() {
        try {
            const respuesta = await fetch(`apis/api_auditoria.php?pagina=${paginaAuditoriaActual}`, { cache: "no-store" });
            const datos = await respuesta.json();
            if (!respuesta.ok) throw new Error(datos.error || "No se pudo cargar la auditoría.");
            document.getElementById("filasAuditoria").innerHTML = datos.registros.length
                ? datos.registros.map((fila) => `<tr>
                    <td>${escaparAuditoria(fila.fecha_hora)}</td><td>${escaparAuditoria(fila.responsable)} (${escaparAuditoria(fila.usuario)})</td>
                    <td>${escaparAuditoria(fila.accion)}</td><td>${escaparAuditoria(fila.entidad)} #${escaparAuditoria(fila.id_entidad)}</td>
                    <td>${escaparAuditoria(JSON.stringify(fila.detalle || {}))}</td><td>${escaparAuditoria(fila.ip)}</td></tr>`).join("")
                : '<tr><td colspan="6">Todavía no hay eventos registrados.</td></tr>';
            document.getElementById("mensajeAuditoria").textContent = `Total: ${datos.total} eventos`;
            document.getElementById("paginaAuditoria").textContent = `Página ${datos.pagina}`;
            document.getElementById("auditoriaAnterior").disabled = !datos.hay_anterior;
            document.getElementById("auditoriaSiguiente").disabled = !datos.hay_siguiente;
            document.getElementById("auditoriaSiguiente").dataset.hayMas = datos.hay_siguiente ? "true" : "false";
            auditoriaCargada = true;
        } catch (error) {
            document.getElementById("mensajeAuditoria").textContent = error.message;
            auditoriaCargada = false;
        }
    }
    function escaparAuditoria(valor) {
        return String(valor ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    </script>
    <?php endif; ?>
</body>

</html>