<?php
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
require_once __DIR__ . "/../lib/fpdf19/fpdf.php";
verificar_permiso("conteos");

function responder_error_pdf($mensaje, $codigo, $detalle = "") {
    if ($detalle !== "") {
        error_log("PDF de conteo: " . $detalle);
    }
    http_response_code($codigo);
    header("Content-Type: text/plain; charset=utf-8");
    exit($mensaje);
}

function texto_pdf($texto) {
    $convertido = iconv("UTF-8", "ISO-8859-1//TRANSLIT", (string)$texto);
    if ($convertido === false) {
        throw new RuntimeException("No se pudo convertir texto a la codificación del PDF.");
    }
    return $convertido;
}

class PDFConteoInsumos extends FPDF {
    public $numeroConteo;
    public $fechaInicio;
    public $alcance;
    public $responsable;
    public $tipo;

    function Header() {
        $this->SetTextColor(0, 31, 71);
        $this->SetFont("Arial", "B", 15);
        $titulo = $this->tipo === "diferencias" ? "Diferencias del conteo de insumos" : "Hoja de conteo de insumos";
        $this->Cell(0, 8, texto_pdf($titulo), 0, 1);

        $this->SetFont("Arial", "", 10);
        $this->Cell(95, 6, texto_pdf("Conteo #" . $this->numeroConteo), 0, 0);
        $this->Cell(0, 6, texto_pdf("Iniciado: " . $this->fechaInicio), 0, 1);
        $this->Cell(0, 6, texto_pdf("Alcance: " . $this->alcance), 0, 1);
        $this->Cell(0, 6, texto_pdf("Responsable: " . $this->responsable), 0, 1);
        $this->Ln(2);

        $this->SetFillColor(0, 31, 71);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(0, 31, 71);
        $this->SetFont("Arial", "B", 9);
        if ($this->tipo === "diferencias") {
            $columnas = array(
                array(18, "Código", "C"), array(63, "Insumo", "L"),
                array(31, "Teórico", "C"), array(31, "Físico", "C"),
                array(40, "Diferencia", "C"), array(35, "Costo unitario", "C"),
                array(59, "Diferencia valorizada", "C")
            );
        } else {
            $columnas = array(
                array(22, "Código", "C"), array(118, "Descripción / insumo", "L"),
                array(55, "Saldo teórico", "C"), array(82, "Saldo físico", "C")
            );
        }
        foreach ($columnas as $indice => $columna) {
            $this->Cell($columna[0], 8, texto_pdf($columna[1]), 1, $indice === count($columnas) - 1 ? 1 : 0, $columna[2], true);
        }
        $this->SetTextColor(0, 0, 0);
        $this->SetFont("Arial", "", 9);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont("Arial", "", 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, texto_pdf("Página " . $this->PageNo()), 0, 0, "C");
    }

    function Fila($codigo, $descripcion, $saldoTeorico) {
        $this->EscribirFila(
            array(22, 118, 55, 82),
            array($codigo, $descripcion, $saldoTeorico, ""),
            array("C", "L", "C", "C")
        );
    }

    function FilaDiferencia($item) {
        $diferencia = (float)$item["cantidad_contada"] - (float)$item["cantidad_sistema"];
        if ($diferencia === 0.0) return;
        $diferenciaValorizada = round($diferencia * (float)$item["costo"], 2);
        $unidad = $item["unidad"] === "-" ? "" : " " . $item["unidad"];
        $this->EscribirFila(
            array(18, 63, 31, 31, 40, 35, 59),
            array(
                (string)$item["id_insumo"],
                texto_pdf($item["nombre"]),
                texto_pdf($this->FormatearCantidad($item["cantidad_sistema"]) . $unidad),
                texto_pdf($this->FormatearCantidad($item["cantidad_contada"]) . $unidad),
                texto_pdf($this->FormatearCantidadConSigno($diferencia) . $unidad),
                texto_pdf("$ " . $this->FormatearCantidad($item["costo"])),
                texto_pdf("$ " . $this->FormatearCantidadConSigno($diferenciaValorizada))
            ),
            array("C", "L", "C", "C", "C", "R", "R")
        );
    }

    function EscribirFila($anchos, $valores, $alineaciones) {
        $anchoDescripcion = $anchos[1];
        $lineas = $this->CantidadLineas($anchoDescripcion, $valores[1]);
        $altoLinea = 6;
        $alto = $altoLinea * $lineas;
        $this->ComprobarSalto($alto);

        $x = $this->GetX();
        $y = $this->GetY();
        $this->Cell($anchos[0], $alto, $valores[0], 1, 0, $alineaciones[0]);
        $this->SetXY($x + $anchos[0], $y);
        $this->MultiCell($anchoDescripcion, $altoLinea, $valores[1], 1, $alineaciones[1]);
        $this->SetXY($x + $anchos[0] + $anchoDescripcion, $y);
        for ($i = 2; $i < count($anchos); $i++) {
            $this->Cell($anchos[$i], $alto, $valores[$i], 1, $i === count($anchos) - 1 ? 1 : 0, $alineaciones[$i]);
        }
    }

    function FormatearCantidad($cantidad) {
        return number_format((float)$cantidad, 2, ",", ".");
    }

    function FormatearCantidadConSigno($cantidad) {
        $cantidad = round((float)$cantidad, 2);
        return ($cantidad > 0 ? "+" : "") . $this->FormatearCantidad($cantidad);
    }

    function ComprobarSalto($alto) {
        if ($this->GetY() + $alto > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    function CantidadLineas($ancho, $texto) {
        $anchoMaximo = ($ancho - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $texto = str_replace("\r", "", $texto);
        $longitud = strlen($texto);
        if ($longitud > 0 && $texto[$longitud - 1] === "\n") $longitud--;

        $anchosCaracteres = $this->CurrentFont["cw"];
        $separador = -1;
        $anchoLinea = 0;
        $inicioLinea = 0;
        $lineas = 1;
        $i = 0;
        while ($i < $longitud) {
            $caracter = $texto[$i];
            if ($caracter === "\n") {
                $i++;
                $lineas++;
                $separador = -1;
                $inicioLinea = $i;
                $anchoLinea = 0;
                continue;
            }
            if ($caracter === " ") $separador = $i;
            $anchoLinea += $anchosCaracteres[$caracter];
            if ($anchoLinea > $anchoMaximo) {
                if ($separador === -1) {
                    if ($i === $inicioLinea) $i++;
                } else {
                    $i = $separador + 1;
                }
                $lineas++;
                $inicioLinea = $i;
                $separador = -1;
                $anchoLinea = 0;
            } else {
                $i++;
            }
        }
        return $lineas;
    }
}

$idParametro = obtener_parametro_get("id_conteo");
$idConteo = filter_var($idParametro, FILTER_VALIDATE_INT);
$tipo = obtener_parametro_get("tipo", "base");
if (!in_array($tipo, array("base", "diferencias"), true)) {
    responder_error_pdf("El tipo de hoja solicitado no es válido.", 400);
}
if ($idConteo === false || $idConteo <= 0) {
    responder_error_pdf("El número de conteo no es válido.", 400);
}

$stmt = mysqli_prepare(
    $conexion,
    "SELECT c.id_conteo, c.fecha_inicio, c.tipo_categoria, cat.titulo AS categoria,
            e.nombre, e.apellido, c.estado
     FROM Conteo_Insumo c
     LEFT JOIN Categoria cat ON cat.id_categoria = c.id_categoria
     INNER JOIN Empleado e ON e.id_empleado = c.id_empleado
     WHERE c.id_conteo = ?"
);
if (!$stmt) {
    responder_error_pdf("No se pudo consultar el conteo.", 500, mysqli_error($conexion));
}
mysqli_stmt_bind_param($stmt, "i", $idConteo);
if (!mysqli_stmt_execute($stmt)) {
    $detalleError = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    responder_error_pdf("No se pudo consultar el conteo.", 500, $detalleError);
}
$conteo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$conteo) {
    responder_error_pdf("El conteo solicitado no existe.", 404);
}
if ($tipo === "base" && $conteo["estado"] !== "En curso") {
    responder_error_pdf("La hoja base solo está disponible para conteos en curso.", 409);
}
if ($tipo === "diferencias" && !in_array($conteo["estado"], array("En curso", "Finalizado"), true)) {
    responder_error_pdf("Las diferencias solo están disponibles mientras el conteo está en curso o una vez finalizado.", 409);
}

$stmt = mysqli_prepare(
    $conexion,
    "SELECT d.id_insumo, i.nombre, i.costo, COALESCE(m.simbolo, m.unidad, '-') AS unidad,
            d.cantidad_sistema, d.cantidad_contada,
            CASE
                WHEN d.cantidad_contada IS NOT NULL THEN d.cantidad_contada - d.cantidad_sistema
                ELSE d.diferencia
            END AS diferencia
     FROM Detalle_Conteo_Insumo d
     INNER JOIN Insumo i ON i.id_insumo = d.id_insumo
     LEFT JOIN Medida m ON m.id_medida = i.id_medida
     WHERE d.id_conteo = ?
     ORDER BY i.nombre"
);
if (!$stmt) {
    responder_error_pdf("No se pudo consultar el detalle del conteo.", 500, mysqli_error($conexion));
}
mysqli_stmt_bind_param($stmt, "i", $idConteo);
if (!mysqli_stmt_execute($stmt)) {
    $detalleError = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    responder_error_pdf("No se pudo consultar el detalle del conteo.", 500, $detalleError);
}
$resultado = mysqli_stmt_get_result($stmt);
$items = array();
while ($item = mysqli_fetch_assoc($resultado)) {
    $items[] = $item;
}
mysqli_stmt_close($stmt);
if (!$items) {
    responder_error_pdf("El conteo no tiene insumos para imprimir.", 422);
}
if ($tipo === "diferencias") {
    $items = array_values(array_filter($items, function ($item) {
        return $item["diferencia"] !== null && (float)$item["diferencia"] !== 0.0;
    }));
    if (!$items) {
        responder_error_pdf("Todavía no hay diferencias contadas para imprimir.", 422);
    }
    usort($items, function ($a, $b) {
        $diferenciaA = (float)$a["diferencia"];
        $diferenciaB = (float)$b["diferencia"];
        $grupoA = $diferenciaA < 0 ? 0 : 1;
        $grupoB = $diferenciaB < 0 ? 0 : 1;
        return $grupoA <=> $grupoB
            ?: abs($diferenciaB) <=> abs($diferenciaA)
            ?: strcasecmp($a["nombre"], $b["nombre"]);
    });
}

try {
    $fecha = DateTime::createFromFormat("Y-m-d H:i:s", $conteo["fecha_inicio"]);
    $fechaInicio = $fecha ? $fecha->format("d/m/Y H:i") : $conteo["fecha_inicio"];
    if ($conteo["tipo_categoria"] === "sin_categoria") {
        $alcance = "Insumos sin categoría";
    } elseif ($conteo["tipo_categoria"] === "categoria") {
        $alcance = $conteo["categoria"] ?: "Categoría eliminada";
    } else {
        $alcance = "Todas las categorías";
    }

    $pdf = new PDFConteoInsumos("L", "mm", "A4");
    $pdf->numeroConteo = $conteo["id_conteo"];
    $pdf->fechaInicio = $fechaInicio;
    $pdf->alcance = $alcance;
    $pdf->responsable = trim($conteo["nombre"] . " " . $conteo["apellido"]);
    $pdf->tipo = $tipo;
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $diferenciaValorizadaTotal = 0;
    foreach ($items as $item) {
        if ($tipo === "diferencias") {
            $diferenciaValorizadaTotal += round((float)$item["diferencia"] * (float)$item["costo"], 2);
            $pdf->FilaDiferencia($item);
        } else {
            $saldo = number_format((float)$item["cantidad_sistema"], 2, ",", ".");
            $unidad = $item["unidad"] === "-" ? "" : " " . $item["unidad"];
            $pdf->Fila(
                (string)$item["id_insumo"],
                texto_pdf($item["nombre"]),
                texto_pdf($saldo . $unidad)
            );
        }
    }
    if ($tipo === "diferencias") {
        $pdf->Ln(3);
        $pdf->SetFont("Arial", "B", 10);
        $pdf->Cell(218, 7, texto_pdf("Diferencia valorizada neta"), 1, 0, "R");
        $pdf->Cell(59, 7, texto_pdf("$ " . $pdf->FormatearCantidadConSigno($diferenciaValorizadaTotal)), 1, 1, "R");
    }

    header("Cache-Control: private, no-store, no-cache, must-revalidate");
    $pdf->Output("I", ($tipo === "base" ? "conteo-" : "diferencias-conteo-") . $idConteo . ".pdf");
} catch (Throwable $error) {
    responder_error_pdf("No se pudo generar la hoja de conteo.", 500, $error->getMessage());
}
