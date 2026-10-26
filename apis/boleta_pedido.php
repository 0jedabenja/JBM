<?php
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
require_once __DIR__ . "/../lib/fpdf19/fpdf.php";
verificar_sesion();
if (!usuario_tiene_permiso("pedidos") && !usuario_tiene_permiso("caja")) {
    responder_error_boleta("No tiene permiso para imprimir este comprobante.", 403);
}

function responder_error_boleta($mensaje, $codigo, $detalle = "") {
    if ($detalle !== "") {
        error_log("Boleta de pedido: " . $detalle);
    }
    http_response_code($codigo);
    header("Content-Type: text/plain; charset=utf-8");
    exit($mensaje);
}

function texto_boleta($texto) {
    $convertido = iconv("UTF-8", "ISO-8859-1//TRANSLIT", (string)$texto);
    if ($convertido === false) {
        throw new RuntimeException("No se pudo convertir texto a la codificación de la boleta.");
    }
    return $convertido;
}

class PDFBoletaPedido extends FPDF {
    public $idPedido;

    function Header() {
        $this->SetFont("Arial", "B", 12);
        $this->Cell(0, 7, texto_boleta("Lo de Torres"), 0, 1, "C");
        $this->SetFont("Arial", "", 9);
        $this->Cell(0, 5, texto_boleta("RUT: 00.000.000-0 (provisorio)"), 0, 1, "C");
        $this->Cell(0, 5, texto_boleta("Dirección: Dirección pendiente (provisoria)"), 0, 1, "C");
        $this->Cell(0, 5, texto_boleta("COMPROBANTE DE VENTA · Pedido #" . $this->idPedido), 0, 1, "C");
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-10);
        $this->SetFont("Arial", "", 8);
        $this->Cell(0, 5, texto_boleta("Página " . $this->PageNo()), 0, 0, "C");
    }

    function ImprimirProducto($nombre, $cantidad, $precio, $total) {
        $this->SetFont("Arial", "B", 8);
        $this->MultiCell(68, 4, texto_boleta($nombre), 0, "L");
        $this->SetFont("Arial", "", 8);
        $this->Cell(39, 5, texto_boleta($cantidad . " x $ " . number_format((float)$precio, 2, ",", ".")), 0, 0, "L");
        $this->Cell(29, 5, texto_boleta("$ " . number_format((float)$total, 2, ",", ".")), 0, 1, "R");
    }
}

$idParametro = obtener_parametro_get("id_pedido");
$idPedido = filter_var($idParametro, FILTER_VALIDATE_INT);
if ($idPedido === false || $idPedido <= 0) {
    responder_error_boleta("El número de pedido no es válido.", 400);
}

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
mysqli_stmt_bind_param($stmt, "i", $idPedido);
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
$efectivoRecibido = null;
$vuelto = null;
$entradaEfectivo = $_GET["efectivo_recibido"] ?? null;
if ($entradaEfectivo !== null) {
    if (
        !is_string($entradaEfectivo)
        || !is_numeric($entradaEfectivo)
        || !is_finite((float)$entradaEfectivo)
        || (float)$entradaEfectivo < 0
        || strtolower($pedido["metodo_pago"]) !== "efectivo"
    ) {
        responder_error_boleta("Los datos del pago en efectivo no son válidos.", 400);
    }
    $totalCentavos = (int)round((float)$pedido["monto_total"] * 100);
    $recibidoCentavos = (int)round((float)$entradaEfectivo * 100);
    if ($recibidoCentavos < $totalCentavos) {
        responder_error_boleta("El efectivo recibido no puede ser menor que el total.", 400);
    }
    $efectivoRecibido = $recibidoCentavos / 100;
    $vuelto = ($recibidoCentavos - $totalCentavos) / 100;
}

$stmt = mysqli_prepare(
    $conexion,
    "SELECT p.nombre, c.cantidad, c.precio, c.total
     FROM Contiene c
     INNER JOIN Producto p ON p.id_producto = c.id_producto
     WHERE c.id_pedido = ?
     ORDER BY p.nombre"
);
if (!$stmt) {
    responder_error_boleta("No se pudieron consultar los productos del pedido.", 500, mysqli_error($conexion));
}
mysqli_stmt_bind_param($stmt, "i", $idPedido);
if (!mysqli_stmt_execute($stmt)) {
    $detalleError = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    responder_error_boleta("No se pudieron consultar los productos del pedido.", 500, $detalleError);
}
$resultado = mysqli_stmt_get_result($stmt);
$productos = array();
while ($producto = mysqli_fetch_assoc($resultado)) {
    $productos[] = $producto;
}
mysqli_stmt_close($stmt);
if (!$productos) {
    responder_error_boleta("El pedido no tiene productos para imprimir.", 422);
}

try {
    $pdf = new PDFBoletaPedido("P", "mm", array(80, 220));
    $pdf->idPedido = $pedido["id_pedido"];
    $pdf->SetMargins(6, 7, 6);
    $pdf->SetAutoPageBreak(true, 13);
    $pdf->AddPage();
    $fecha = DateTime::createFromFormat("Y-m-d H:i:s", $pedido["fecha_hora_creacion"]);
    $fechaTexto = $fecha ? $fecha->format("d/m/Y H:i") : $pedido["fecha_hora_creacion"];
    $pdf->SetFont("Arial", "", 8);
    $pdf->Cell(0, 5, texto_boleta("Fecha y hora del comprobante: " . date("d/m/Y H:i")), 0, 1);
    $pdf->Cell(0, 5, texto_boleta("Pedido creado: " . $fechaTexto), 0, 1);
    $pdf->Cell(0, 5, texto_boleta("Mesa: " . $pedido["mesa"]), 0, 1);
    $pdf->MultiCell(0, 5, texto_boleta("Cliente: " . $pedido["cliente"]), 0, "L");
    $pdf->Cell(0, 5, texto_boleta("Atendió: " . $pedido["empleado"]), 0, 1);
    $pdf->Cell(0, 5, texto_boleta("Pago: " . $pedido["metodo_pago"]), 0, 1);
    $pdf->Ln(2);
    $pdf->SetDrawColor(120, 120, 120);
    $pdf->Line(6, $pdf->GetY(), 74, $pdf->GetY());
    $pdf->Ln(2);

    foreach ($productos as $producto) {
        $pdf->ImprimirProducto($producto["nombre"], $producto["cantidad"], $producto["precio"], $producto["total"]);
    }

    $subtotal = array_sum(array_map(function ($producto) {
        return (float)$producto["total"];
    }, $productos));
    $pdf->Ln(2);
    $pdf->Line(6, $pdf->GetY(), 74, $pdf->GetY());
    $pdf->Ln(2);
    if ((float)$pedido["monto_descuento"] > 0) {
        $pdf->SetFont("Arial", "", 8);
        $pdf->Cell(34, 5, texto_boleta("Subtotal"), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("$ " . number_format($subtotal, 2, ",", ".")), 0, 1, "R");
        $etiquetaDescuento = "Descuento (" . number_format((float)$pedido["porcentaje_descuento"], 2, ",", ".") . "%)";
        $pdf->Cell(34, 5, texto_boleta($etiquetaDescuento), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("- $ " . number_format((float)$pedido["monto_descuento"], 2, ",", ".")), 0, 1, "R");
    }
    $pdf->SetFont("Arial", "B", 10);
    $pdf->Cell(34, 6, texto_boleta("TOTAL"), 0, 0, "L");
    $pdf->Cell(34, 6, texto_boleta("$ " . number_format((float)$pedido["monto_total"], 2, ",", ".")), 0, 1, "R");
    if ($efectivoRecibido !== null) {
        $pdf->Ln(1);
        $pdf->SetFont("Arial", "", 8);
        $pdf->Cell(34, 5, texto_boleta("Efectivo recibido"), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("$ " . number_format($efectivoRecibido, 2, ",", ".")), 0, 1, "R");
        $pdf->Cell(34, 5, texto_boleta("Vuelto"), 0, 0, "L");
        $pdf->Cell(34, 5, texto_boleta("$ " . number_format($vuelto, 2, ",", ".")), 0, 1, "R");
    }
    $pdf->Ln(4);
    $pdf->SetFont("Arial", "", 8);
    $pdf->Cell(0, 5, texto_boleta("Gracias por su compra"), 0, 1, "C");

    header("Cache-Control: private, no-store, no-cache, must-revalidate");
    $pdf->Output("I", "boleta-pedido-" . $idPedido . ".pdf");
} catch (Throwable $error) {
    responder_error_boleta("No se pudo generar la boleta del pedido.", 500, $error->getMessage());
}
