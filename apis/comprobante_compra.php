<?php
include_once __DIR__ . "/../includes/conexionBD.php";
include_once __DIR__ . "/../includes/funciones.php";
verificar_permiso("compras");

$idCompra = filter_var(obtener_parametro_get("id"), FILTER_VALIDATE_INT);
if ($idCompra === false || $idCompra <= 0) mostrar_pagina_error(400);

$stmt = mysqli_prepare($conexion, "SELECT c.id_compra, c.fecha_hora, c.costo_total, c.observacion, CONCAT(e.nombre, ' ', e.apellido) AS responsable FROM Compra c INNER JOIN Empleado e ON e.id_empleado = c.id_empleado WHERE c.id_compra = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $idCompra);
mysqli_stmt_execute($stmt);
$compra = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$compra) mostrar_pagina_error(404);

$stmt = mysqli_prepare($conexion, "SELECT i.nombre, x.cantidad, x.costo, x.total, COALESCE(m.simbolo, m.unidad, '-') AS unidad FROM Incluye x INNER JOIN Insumo i ON i.id_insumo = x.id_insumo LEFT JOIN Medida m ON m.id_medida = i.id_medida WHERE x.id_compra = ? ORDER BY i.nombre");
mysqli_stmt_bind_param($stmt, "i", $idCompra);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$items = array();
while ($item = mysqli_fetch_assoc($resultado)) $items[] = $item;
mysqli_stmt_close($stmt);

function h($valor) { return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8"); }
function dinero($valor) { return '$ ' . number_format((float)$valor, 2, ',', '.'); }
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Comprobante compra #<?php echo (int)$idCompra; ?></title>
<style>
@page { size: 80mm auto; margin: 0; }
html, body { margin: 0; padding: 0; background: #fff; }
.ticket { width: 80mm; box-sizing: border-box; padding: 4mm 3mm 5mm; font-family: "Courier New", monospace; font-size: 12px; color: #000; }
.ticket h1 { font-size: 18px; text-align: center; margin: 0 0 3mm; }
.centro { text-align: center; }.linea { border-top: 1px dashed #000; margin: 2mm 0; }.fila { display: flex; justify-content: space-between; gap: 3mm; }.item { margin: 1.5mm 0; }.item-nombre { font-weight: bold; overflow-wrap: anywhere; }.total { font-size: 16px; font-weight: bold; margin-top: 3mm; }.nota { overflow-wrap: anywhere; }
@media print { .no-print { display: none !important; } }
@media screen { body { background: #ddd; }.ticket { margin: 10px auto; background: white; box-shadow: 0 2px 12px #999; }.no-print { text-align: center; padding: 10px; } button { font-size: 16px; padding: 8px 16px; } }
</style></head><body><div class="no-print"><button onclick="window.print()">Imprimir comprobante</button></div><article class="ticket">
<h1>JBM - COMPRA</h1><div class="centro">Comprobante #<?php echo (int)$idCompra; ?><br><?php echo h($compra["fecha_hora"]); ?></div><div class="linea"></div>
<p>Responsable: <?php echo h($compra["responsable"]); ?></p>
<?php foreach ($items as $item): ?><div class="item"><div class="item-nombre"><?php echo h($item["nombre"]); ?></div><div class="fila"><span><?php echo h($item["cantidad"]); ?> <?php echo h($item["unidad"]); ?> x <?php echo dinero($item["costo"]); ?></span><strong><?php echo dinero($item["total"]); ?></strong></div></div><?php endforeach; ?>
<div class="linea"></div><div class="fila total"><span>TOTAL</span><span><?php echo dinero($compra["costo_total"]); ?></span></div>
<?php if (trim((string)$compra["observacion"]) !== ""): ?><div class="linea"></div><div class="nota">Nota: <?php echo h($compra["observacion"]); ?></div><?php endif; ?>
<div class="linea"></div><div class="centro">Gracias</div></article><script>window.addEventListener("load", () => setTimeout(() => window.print(), 300));</script></body></html>
