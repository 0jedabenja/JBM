<?php
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
require_once __DIR__ . "/../librerias/fpdf19/fpdf.php";
verificar_sesion();

function responder_error_boleta($mensaje, $codigo, $detalle = "") {
    if ($detalle !== "") {
        error_log("Comprobante: " . $detalle);
    }
    http_response_code($codigo);
    header("Content-Type: text/plain; charset=utf-8");
    exit($mensaje);
}

$tipoComprobante = obtener_parametro_get("tipo", "pedido");
if (!in_array($tipoComprobante, array("pedido", "compra"), true)) {
    responder_error_boleta("El tipo de comprobante no es válido.", 400);
}
if (
    ($tipoComprobante === "compra" && !usuario_tiene_permiso("compras"))
    || ($tipoComprobante === "pedido" && !usuario_tiene_permiso("pedidos") && !usuario_tiene_permiso("caja"))
) {
    responder_error_boleta("No tiene permiso para imprimir este comprobante.", 403);
}

function texto_boleta($texto) {
    $convertido = iconv("UTF-8", "ISO-8859-1//TRANSLIT", (string)$texto);
    if ($convertido === false) {
        throw new RuntimeException("No se pudo convertir texto a la codificación de la boleta.");
    }
    return $convertido;
}

class PDFBoletaPedido extends FPDF {
    public $tipoComprobante;
    public $idComprobante;

    function ContarLineas($ancho, $texto) {
        $texto = str_replace("\r", "", (string)$texto);
        $cantidadBytes = strlen($texto);
        if ($cantidadBytes > 0 && $texto[$cantidadBytes - 1] === "\n") {
            $cantidadBytes--;
        }
        $anchoMaximo = ($ancho - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $lineas = 1;
        $indice = 0;
        $indiceInicio = 0;
        $anchoLinea = 0;
        $ultimoEspacio = -1;
        while ($indice < $cantidadBytes) {
            $caracter = $texto[$indice];
            if ($caracter === "\n") {
                $lineas++;
                $indice++;
                $indiceInicio = $indice;
                $anchoLinea = 0;
                $ultimoEspacio = -1;
                continue;
            }
            if ($caracter === " ") {
                $ultimoEspacio = $indice;
            }
            $anchoLinea += $this->CurrentFont["cw"][$caracter];
            if ($anchoLinea > $anchoMaximo) {
                if ($ultimoEspacio < $indiceInicio) {
                    if ($indice === $indiceInicio) {
                        $indice++;
                    }
                    $indiceInicio = $indice;
                } else {
                    $indice = $ultimoEspacio + 1;
                    $indiceInicio = $ultimoEspacio + 1;
                }
                $lineas++;
                $anchoLinea = 0;
                $ultimoEspacio = -1;
                continue;
            }
            $indice++;
        }
        return $lineas;
    }

    function Header() {
        $this->SetFont("Arial", "B", 12);
        $this->Cell(0, 7, texto_boleta("Lo de Torres"), 0, 1, "C");
        $this->SetFont("Arial", "", 9);
        $this->Cell(0, 5, texto_boleta("RUT: 160290160014"), 0, 1, "C");
        $this->SetFont("Arial", "", 8);
        $this->Cell(0, 5, texto_boleta("Av. Paysandú esq. Ibirapitá"), 0, 1, "C");
        $this->Cell(0, 5, texto_boleta("Termas del Daymán · Salto"), 0, 1, "C");
        $this->SetFont("Arial", "", 9);
        $etiqueta = $this->tipoComprobante === "compra" ? "COMPRA · Compra #" : "VENTA · Pedido #";
        $this->Cell(0, 5, texto_boleta("COMPROBANTE DE " . $etiqueta . $this->idComprobante), 0, 1, "C");
        $this->SetFont("Arial", "I", 8);
        $this->Cell(0, 4, texto_boleta("Comprobante sin valor fiscal"), 0, 1, "C");
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-13);
        $this->SetFont("Arial", "", 8);
        $this->MultiCell(68, 4, texto_boleta("Gracias por visitarnos. ¡Esperamos volver a verte pronto!"), 0, "C");
    }

    function ImprimirProducto($nombre, $cantidad, $precio, $total, $unidad = "") {
        $this->SetFont("Arial", "B", 8);
        $this->MultiCell(68, 4, texto_boleta($nombre), 0, "L");
        $this->SetFont("Arial", "", 8);
        $detalleCantidad = trim((string)$cantidad . " " . (string)$unidad);
        $this->Cell(39, 5, texto_boleta($detalleCantidad . " x $ " . number_format((float)$precio, 2, ",", ".")), 0, 0, "L");
        $this->Cell(29, 5, texto_boleta("$ " . number_format((float)$total, 2, ",", ".")), 0, 1, "R");
    }
}

$esCompra = $tipoComprobante === "compra";
$idParametro = obtener_parametro_get($esCompra ? "id_compra" : "id_pedido");
$idComprobante = filter_var($idParametro, FILTER_VALIDATE_INT);
if ($idComprobante === false || $idComprobante <= 0) {
    responder_error_boleta("El número de comprobante no es válido.", 400);
}

$comprobante = array();
if ($esCompra) {
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT c.id_compra, c.fecha_hora, c.costo_total, c.observacion,
                CONCAT(e.nombre, ' ', e.apellido) AS responsable
         FROM Compra c
         INNER JOIN Empleado e ON e.id_empleado = c.id_empleado
         WHERE c.id_compra = ? LIMIT 1"
    );
    if (!$stmt) {
        responder_error_boleta("No se pudo consultar la compra.", 500, mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, "i", $idComprobante);
    if (!mysqli_stmt_execute($stmt)) {
        $detalleError = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        responder_error_boleta("No se pudo consultar la compra.", 500, $detalleError);
    }
    $compra = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$compra) {
        responder_error_boleta("La compra solicitada no existe.", 404);
    }
    $comprobante = array(
        "fecha" => $compra["fecha_hora"],
        "total" => $compra["costo_total"],
        "responsable" => $compra["responsable"],
        "observacion" => $compra["observacion"]
    );
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT i.nombre, x.cantidad, x.costo AS precio, x.total,
                COALESCE(m.simbolo, m.unidad, '-') AS unidad
         FROM Incluye x
         INNER JOIN Insumo i ON i.id_insumo = x.id_insumo
         LEFT JOIN Medida m ON m.id_medida = i.id_medida
         WHERE x.id_compra = ?
         ORDER BY i.nombre"
    );
} else {
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT p.id_pedido, p.fecha_hora_creacion, p.monto_total, p.cobrado,
                p.porcentaje_descuento, p.monto_descuento,
                COALESCE(CONCAT(c.nombre, ' ', c.apellido), 'Consumidor final') AS cliente,
                COALESCE(CAST(m.numero AS CHAR), '-') AS mesa,
                COALESCE(mp.descripcion, 'Pendiente de cobro') AS metodo_pago,
                CONCAT(e.nombre, ' ', e.apellido) AS empleado,
                ep.nombre AS estado
         FROM Pedido p
         LEFT JOIN Cliente c ON c.id_cliente = p.id_cliente
         LEFT JOIN Mesa m ON m.id_mesa = p.id_mesa
         LEFT JOIN Metodo_pago mp ON mp.id_mp = p.id_mp
         INNER JOIN Empleado e ON e.id_empleado = p.id_empleado
         INNER JOIN Estado_Pedido ep ON ep.id_estado = p.id_estado
         WHERE p.id_pedido = ?"
    );
    if (!$stmt) {
        responder_error_boleta("No se pudo consultar el pedido.", 500, mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, "i", $idComprobante);
    if (!mysqli_stmt_execute($stmt)) {
        $detalleError = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        responder_error_boleta("No se pudo consultar el pedido.", 500, $detalleError);
    }
    $pedido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$pedido || strtolower($pedido["estado"]) !== "listo" || (int)$pedido["cobrado"] !== 1) {
        responder_error_boleta("El comprobante estará disponible cuando el pedido esté listo y cobrado.", 409);
    }
    $comprobante = array(
        "fecha" => $pedido["fecha_hora_creacion"],
        "total" => $pedido["monto_total"],
        "cliente" => $pedido["cliente"],
        "mesa" => $pedido["mesa"],
        "empleado" => $pedido["empleado"],
        "metodo_pago" => $pedido["metodo_pago"],
        "porcentaje_descuento" => $pedido["porcentaje_descuento"],
        "monto_descuento" => $pedido["monto_descuento"],
        "observacion" => ""
    );
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT p.nombre, c.cantidad, c.precio, c.total, '' AS unidad
         FROM Contiene c
         INNER JOIN Producto p ON p.id_producto = c.id_producto
         WHERE c.id_pedido = ?
         ORDER BY p.nombre"
    );
}
if (!$stmt) {
    responder_error_boleta(
        $esCompra ? "No se pudieron consultar los insumos de la compra." : "No se pudieron consultar los productos del pedido.",
        500,
        mysqli_error($conexion)
    );
}
mysqli_stmt_bind_param($stmt, "i", $idComprobante);
if (!mysqli_stmt_execute($stmt)) {
    $detalleError = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    responder_error_boleta(
        $esCompra ? "No se pudieron consultar los insumos de la compra." : "No se pudieron consultar los productos del pedido.",
        500,
        $detalleError
    );
}
$resultado = mysqli_stmt_get_result($stmt);
$productos = array();
while ($producto = mysqli_fetch_assoc($resultado)) {
    $productos[] = $producto;
}
mysqli_stmt_close($stmt);
if (!$productos) {
    responder_error_boleta(
        $esCompra ? "La compra no tiene insumos para imprimir." : "El pedido no tiene productos para imprimir.",
        422
    );
}

$efectivoRecibido = null;
$vuelto = null;
$entradaEfectivo = $esCompra ? null : ($_GET["efectivo_recibido"] ?? null);
if ($entradaEfectivo !== null) {
    if (
        !is_string($entradaEfectivo)
        || !is_numeric($entradaEfectivo)
        || !is_finite((float)$entradaEfectivo)
        || (float)$entradaEfectivo < 0
        || strtolower($comprobante["metodo_pago"]) !== "efectivo"
    ) {
        responder_error_boleta("Los datos del pago en efectivo no son válidos.", 400);
    }
    $totalCentavos = (int)round((float)$comprobante["total"] * 100);
    $recibidoCentavos = (int)round((float)$entradaEfectivo * 100);
    if ($recibidoCentavos < $totalCentavos) {
        responder_error_boleta("El efectivo recibido no puede ser menor que el total.", 400);
    }
    $efectivoRecibido = $recibidoCentavos / 100;
    $vuelto = ($recibidoCentavos - $totalCentavos) / 100;
}

try {
    $pdf = new PDFBoletaPedido("P", "mm", array(80, 160));
    $pdf->tipoComprobante = $tipoComprobante;
    $pdf->idComprobante = $idComprobante;
    $pdf->SetMargins(6, 7, 6);
    $pdf->SetFont("Arial", "", 8);
    $fecha = DateTime::createFromFormat("Y-m-d H:i:s", $comprobante["fecha"]);
    $fechaTexto = $fecha ? $fecha->format("d/m/Y H:i") : $comprobante["fecha"];
    $lineasDetalles = $esCompra
        ? $pdf->ContarLineas(68, texto_boleta("Responsable: " . $comprobante["responsable"]))
            + $pdf->ContarLineas(68, texto_boleta("Fecha y hora: " . $fechaTexto))
        : $pdf->ContarLineas(68, texto_boleta("Cliente: " . $comprobante["cliente"]));
    if ($esCompra && trim((string)$comprobante["observacion"]) !== "") {
        $lineasDetalles += $pdf->ContarLineas(68, texto_boleta("Observación: " . $comprobante["observacion"]));
    }
    $altoProductos = 0;
    $pdf->SetFont("Arial", "B", 8);
    foreach ($productos as $producto) {
        $altoProductos += 5 + 4 * $pdf->ContarLineas(68, texto_boleta($producto["nombre"]));
    }
    $altoDescuento = !$esCompra && (float)$comprobante["monto_descuento"] > 0 ? 10 : 0;
    $altoEfectivo = $efectivoRecibido !== null ? 11 : 0;
    $altoBoleta = max(145, 120 + 5 * $lineasDetalles + $altoProductos + $altoDescuento + $altoEfectivo);
    $pdf->SetAutoPageBreak(true, 13);
    $pdf->AddPage("P", array(80, $altoBoleta));
    $pdf->SetFont("Arial", "", 8);
    if ($esCompra) {
        $pdf->Cell(0, 5, texto_boleta("Fecha y hora: " . $fechaTexto), 0, 1);
        $pdf->MultiCell(0, 5, texto_boleta("Responsable: " . $comprobante["responsable"]), 0, "L");
    } else {
        $pdf->Cell(0, 5, texto_boleta("Fecha y hora del comprobante: " . date("d/m/Y H:i")), 0, 1);
        $pdf->Cell(0, 5, texto_boleta("Pedido creado: " . $fechaTexto), 0, 1);
        $pdf->Cell(0, 5, texto_boleta("Mesa: " . $comprobante["mesa"]), 0, 1);
        $pdf->MultiCell(0, 5, texto_boleta("Cliente: " . $comprobante["cliente"]), 0, "L");
        $pdf->Cell(0, 5, texto_boleta("Atendió: " . $comprobante["empleado"]), 0, 1);
        $pdf->Cell(0, 5, texto_boleta("Pago: " . $comprobante["metodo_pago"]), 0, 1);
    }
    $pdf->Ln(2);
    $pdf->SetDrawColor(120, 120, 120);
    $pdf->Line(6, $pdf->GetY(), 74, $pdf->GetY());
    $pdf->Ln(2);

    foreach ($productos as $producto) {
        $pdf->ImprimirProducto(
            $producto["nombre"],
            $producto["cantidad"],
            $producto["precio"],
            $producto["total"],
            $producto["unidad"] ?? ""
        );
    }

    $subtotal = array_sum(array_map(function ($producto) {
        return (float)$producto["total"];
    }, $productos));
    $pdf->Ln(2);
    $pdf->Line(6, $pdf->GetY(), 74, $pdf->GetY());
    $pdf->Ln(2);
    if (!$esCompra && (float)$comprobante["monto_descuento"] > 0) {
        $pdf->SetFont("Arial", "", 8);
        $pdf->Cell(34, 5, texto_boleta("Subtotal"), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("$ " . number_format($subtotal, 2, ",", ".")), 0, 1, "R");
        $etiquetaDescuento = "Descuento (" . number_format((float)$comprobante["porcentaje_descuento"], 2, ",", ".") . "%)";
        $pdf->Cell(34, 5, texto_boleta($etiquetaDescuento), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("- $ " . number_format((float)$comprobante["monto_descuento"], 2, ",", ".")), 0, 1, "R");
    }
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Cell(34, 6, texto_boleta("TOTAL"), 0, 0, "L");
    $pdf->Cell(34, 6, texto_boleta("$ " . number_format((float)$comprobante["total"], 2, ",", ".")), 0, 1, "R");
    if ($efectivoRecibido !== null) {
        $pdf->Ln(1);
        $pdf->SetFont("Arial", "", 8);
        $pdf->Cell(34, 5, texto_boleta("Efectivo recibido"), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("$ " . number_format($efectivoRecibido, 2, ",", ".")), 0, 1, "R");
        $pdf->Cell(34, 5, texto_boleta("Vuelto"), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("$ " . number_format($vuelto, 2, ",", ".")), 0, 1, "R");
    }
    if ($esCompra && trim((string)$comprobante["observacion"]) !== "") {
        $pdf->Ln(2);
        $pdf->SetFont("Arial", "", 8);
        $pdf->MultiCell(68, 5, texto_boleta("Observación: " . $comprobante["observacion"]), 0, "L");
    }
    $pdf->Ln(4);

    header("Cache-Control: private, no-store, no-cache, must-revalidate");
    $tipoArchivo = $esCompra ? "compra" : "pedido";
    $pdf->Output("I", "comprobante-" . $tipoArchivo . "-" . $idComprobante . ".pdf");
} catch (Throwable $error) {
    responder_error_boleta("No se pudo generar el comprobante.", 500, $error->getMessage());
}
