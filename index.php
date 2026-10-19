<?php
include_once "includes/funciones.php";
verificar_permiso("inicio");
$rolActual = obtener_rol_sesion();
$insumosBajoStock = array();
$ventasDiarias = array();
$productosMasVendidos = array();
$errorGraficos = "";

if ($rolActual === "administrador") {
    include_once "includes/conexionBD.php";
    $ventasPorFecha = array();
    $fechaHoy = new DateTimeImmutable("today");
    for ($dia = 6; $dia >= 0; $dia--) {
        $fecha = $fechaHoy->modify("-{$dia} days");
        $claveFecha = $fecha->format("Y-m-d");
        $ventasDiarias[] = array(
            "fecha" => $claveFecha,
            "etiqueta" => $fecha->format("d/m"),
            "total" => 0
        );
        $ventasPorFecha[$claveFecha] = count($ventasDiarias) - 1;
    }

    try {
        $stmtVentas = mysqli_prepare($conexion, "SELECT DATE(fecha_hora_creacion) AS fecha, SUM(monto_total) AS total
                                                 FROM Pedido
                                                 WHERE cobrado = TRUE
                                                   AND fecha_hora_creacion >= CURDATE() - INTERVAL 6 DAY
                                                   AND fecha_hora_creacion < CURDATE() + INTERVAL 1 DAY
                                                 GROUP BY DATE(fecha_hora_creacion)
                                                 ORDER BY fecha ASC");
        if ($stmtVentas && mysqli_stmt_execute($stmtVentas)) {
            $resultadoVentas = mysqli_stmt_get_result($stmtVentas);
            while ($venta = mysqli_fetch_assoc($resultadoVentas)) {
                $fechaVenta = $venta["fecha"];
                if (isset($ventasPorFecha[$fechaVenta])) {
                    $ventasDiarias[$ventasPorFecha[$fechaVenta]]["total"] = (float) $venta["total"];
                }
            }
            mysqli_stmt_close($stmtVentas);
        } else {
            $errorGraficos = "No se pudieron cargar las ventas del panel.";
            error_log("No se pudieron cargar las ventas diarias del panel: " . mysqli_error($conexion));
            if ($stmtVentas) mysqli_stmt_close($stmtVentas);
        }
    } catch (mysqli_sql_exception $error) {
        $errorGraficos = "No se pudieron cargar las ventas del panel.";
        error_log("No se pudieron cargar las ventas diarias del panel: " . $error->getMessage());
        if (isset($stmtVentas) && $stmtVentas instanceof mysqli_stmt) mysqli_stmt_close($stmtVentas);
    }

    try {
        $stmtProductos = mysqli_prepare($conexion, "SELECT pr.nombre, SUM(c.cantidad) AS unidades
                                                    FROM Pedido p
                                                    INNER JOIN Contiene c ON c.id_pedido = p.id_pedido
                                                    INNER JOIN Producto pr ON pr.id_producto = c.id_producto
                                                    WHERE p.cobrado = TRUE
                                                      AND p.fecha_hora_creacion >= CURDATE() - INTERVAL 29 DAY
                                                      AND p.fecha_hora_creacion < CURDATE() + INTERVAL 1 DAY
                                                    GROUP BY pr.id_producto, pr.nombre
                                                    ORDER BY unidades DESC, pr.nombre ASC
                                                    LIMIT 5");
        if ($stmtProductos && mysqli_stmt_execute($stmtProductos)) {
            $resultadoProductos = mysqli_stmt_get_result($stmtProductos);
            while ($producto = mysqli_fetch_assoc($resultadoProductos)) {
                $productosMasVendidos[] = array(
                    "nombre" => $producto["nombre"],
                    "unidades" => (int) $producto["unidades"]
                );
            }
            mysqli_stmt_close($stmtProductos);
        } else {
            $errorGraficos = "No se pudieron cargar los productos más vendidos.";
            error_log("No se pudieron cargar los productos más vendidos: " . mysqli_error($conexion));
            if ($stmtProductos) mysqli_stmt_close($stmtProductos);
        }
    } catch (mysqli_sql_exception $error) {
        $errorGraficos = "No se pudieron cargar los productos más vendidos.";
        error_log("No se pudieron cargar los productos más vendidos: " . $error->getMessage());
        if (isset($stmtProductos) && $stmtProductos instanceof mysqli_stmt) mysqli_stmt_close($stmtProductos);
    }

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

    .graficos-inicio {
        display: grid;
        gap: 20px;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr));
        margin: 28px 0;
        max-width: 1200px;
    }

    .grafico-card {
        background: #fff;
        border: 1px solid #e1e7ef;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(0, 31, 71, 0.06);
        min-width: 0;
        padding: 20px;
    }

    .grafico-card h2 {
        color: #001f47;
        font-size: 1.1rem;
        margin: 0 0 5px;
    }

    .grafico-card > p {
        color: #65758b;
        font-size: 0.9rem;
        margin: 0 0 12px;
    }

    .grafico-svg {
        display: block;
        height: auto;
        overflow: visible;
        width: 100%;
    }

    .grafico-vacio,
    .graficos-error {
        border-radius: 8px;
        margin-top: 15px;
        padding: 14px;
    }

    .grafico-vacio {
        background: #f3f6fa;
        color: #53657a;
    }

    .graficos-error {
        background: #fff1f0;
        border: 1px solid #e9a7a3;
        color: #8d211b;
        max-width: 1200px;
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
                    <section class="graficos-inicio" aria-label="Indicadores del negocio">
                        <article class="grafico-card">
                            <h2>Ventas cobradas</h2>
                            <p>Ingresos por día · últimos 7 días</p>
                            <svg id="graficoVentas" class="grafico-svg" viewBox="0 0 720 300" role="img" aria-label="Gráfico de ventas cobradas por día"></svg>
                            <p id="vacioVentas" class="grafico-vacio" hidden>No hay ventas cobradas en los últimos 7 días.</p>
                        </article>
                        <article class="grafico-card">
                            <h2>Productos más vendidos</h2>
                            <p>Unidades en pedidos cobrados · últimos 30 días</p>
                            <svg id="graficoProductos" class="grafico-svg" viewBox="0 0 720 300" role="img" aria-label="Gráfico de los cinco productos más vendidos"></svg>
                            <p id="vacioProductos" class="grafico-vacio" hidden>No hay productos vendidos en los últimos 30 días.</p>
                        </article>
                    </section>
                    <?php if ($errorGraficos !== ""): ?>
                        <p class="graficos-error" role="alert"><?php echo htmlspecialchars($errorGraficos, ENT_QUOTES, "UTF-8"); ?></p>
                    <?php endif; ?>
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
    const ventasDiarias = <?php echo json_encode($ventasDiarias, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const productosMasVendidos = <?php echo json_encode($productosMasVendidos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const nsSvg = "http://www.w3.org/2000/svg";

    function crearSvgElemento(nombre, atributos, texto) {
        const elemento = document.createElementNS(nsSvg, nombre);
        Object.entries(atributos).forEach(([clave, valor]) => elemento.setAttribute(clave, valor));
        if (texto !== undefined) elemento.textContent = texto;
        return elemento;
    }

    function dibujarVentas() {
        const svg = document.getElementById("graficoVentas");
        const hayVentas = ventasDiarias.some((dia) => dia.total > 0);
        if (!hayVentas) {
            svg.hidden = true;
            document.getElementById("vacioVentas").hidden = false;
            return;
        }

        const valores = ventasDiarias.map((dia) => dia.total);
        const maximo = Math.max(...valores) * 1.15 || 1;
        const formatoMoneda = new Intl.NumberFormat("es-UY", { maximumFractionDigits: 0 });
        const xInicio = 70;
        const xFin = 690;
        const yInicio = 24;
        const yFin = 230;
        const puntos = valores.map((valor, indice) => ({
            x: xInicio + (xFin - xInicio) * indice / (valores.length - 1),
            y: yFin - (valor / maximo) * (yFin - yInicio),
            valor
        }));

        for (let marca = 0; marca <= 4; marca++) {
            const y = yFin - (yFin - yInicio) * marca / 4;
            const valor = maximo * marca / 4;
            svg.appendChild(crearSvgElemento("line", { x1: xInicio, y1: y, x2: xFin, y2: y, stroke: "#e7edf4", "stroke-width": 1 }));
            svg.appendChild(crearSvgElemento("text", { x: xInicio - 10, y: y + 4, fill: "#65758b", "font-size": 11, "text-anchor": "end" }, "$" + formatoMoneda.format(valor)));
        }

        svg.appendChild(crearSvgElemento("polyline", {
            points: puntos.map((punto) => `${punto.x},${punto.y}`).join(" "),
            fill: "none",
            stroke: "#1677c8",
            "stroke-width": 4,
            "stroke-linecap": "round",
            "stroke-linejoin": "round"
        }));

        puntos.forEach((punto, indice) => {
            const fecha = ventasDiarias[indice].fecha.split("-").map(Number);
            const fechaLocal = new Date(fecha[0], fecha[1] - 1, fecha[2]);
            const etiqueta = new Intl.DateTimeFormat("es-UY", { weekday: "short" }).format(fechaLocal);
            const circulo = crearSvgElemento("circle", { cx: punto.x, cy: punto.y, r: 6, fill: "#fff", stroke: "#1677c8", "stroke-width": 3 });
            const titulo = crearSvgElemento("title", {}, `${fechaLocal.toLocaleDateString("es-UY")}: $${formatoMoneda.format(punto.valor)}`);
            circulo.appendChild(titulo);
            svg.appendChild(circulo);
            svg.appendChild(crearSvgElemento("text", { x: punto.x, y: 258, fill: "#53657a", "font-size": 11, "text-anchor": "middle" }, etiqueta + " " + ventasDiarias[indice].etiqueta));
        });
    }

    function dibujarProductos() {
        const svg = document.getElementById("graficoProductos");
        if (productosMasVendidos.length === 0) {
            svg.hidden = true;
            document.getElementById("vacioProductos").hidden = false;
            return;
        }

        const maximo = Math.max(...productosMasVendidos.map((producto) => producto.unidades), 1);
        const anchoBarraMaximo = 440;
        const altoFila = 52;
        productosMasVendidos.forEach((producto, indice) => {
            const y = 30 + indice * altoFila;
            const nombre = producto.nombre.length > 25 ? producto.nombre.slice(0, 22) + "..." : producto.nombre;
            const ancho = anchoBarraMaximo * producto.unidades / maximo;
            const barra = crearSvgElemento("rect", { x: 210, y, width: ancho, height: 24, rx: 6, fill: indice === 0 ? "#1677c8" : "#73acd9" });
            barra.appendChild(crearSvgElemento("title", {}, producto.nombre));
            svg.appendChild(crearSvgElemento("text", { x: 198, y: y + 17, fill: "#35465b", "font-size": 13, "text-anchor": "end" }, nombre));
            svg.appendChild(barra);
            svg.appendChild(crearSvgElemento("text", { x: 220 + ancho, y: y + 17, fill: "#35465b", "font-size": 13, "font-weight": 600 }, String(producto.unidades)));
        });
    }

    dibujarVentas();
    dibujarProductos();

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