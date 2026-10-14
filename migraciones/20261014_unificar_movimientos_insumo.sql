INSERT INTO Movimiento_Insumo
    (fecha_hora, id_insumo, id_empleado, tipo, cantidad, costo_unitario, referencia_id)
SELECT
    COALESCE(c.fecha_fin, c.fecha_inicio),
    d.id_insumo,
    c.id_empleado,
    'inventario',
    d.diferencia,
    i.costo,
    c.id_conteo
FROM Detalle_Conteo_Insumo d
INNER JOIN Conteo_Insumo c ON c.id_conteo = d.id_conteo
INNER JOIN Insumo i ON i.id_insumo = d.id_insumo
WHERE c.estado = 'Finalizado'
  AND d.diferencia IS NOT NULL
  AND d.diferencia <> 0
  AND NOT EXISTS (
      SELECT 1
      FROM Movimiento_Insumo m
      WHERE m.id_insumo = d.id_insumo
        AND m.tipo = 'inventario'
        AND m.referencia_id = c.id_conteo
  );

INSERT INTO Movimiento_Insumo
    (fecha_hora, id_insumo, id_empleado, tipo, cantidad, costo_unitario, referencia_id)
SELECT
    COALESCE(c.fecha_hora, CURRENT_TIMESTAMP),
    i.id_insumo,
    c.id_empleado,
    'compra',
    i.cantidad,
    i.costo,
    c.id_compra
FROM Incluye i
INNER JOIN Compra c ON c.id_compra = i.id_compra
WHERE NOT EXISTS (
    SELECT 1
    FROM Movimiento_Insumo m
    WHERE m.id_insumo = i.id_insumo
      AND m.tipo = 'compra'
      AND m.referencia_id = i.id_compra
);
