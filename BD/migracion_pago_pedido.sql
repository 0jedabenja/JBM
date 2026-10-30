-- Migración: Poblar tabla Pago_pedido con pagos existentes
-- Este script copia los pagos registrados en la tabla Pedido a Pago_pedido
-- Solo migra pedidos que fueron cobrados (cobrado = TRUE)

INSERT INTO Pago_pedido (id_pedido, id_mp, monto, fecha_hora, id_turno, id_empleado)
SELECT 
    p.id_pedido,
    COALESCE(p.id_mp, 1) AS id_mp,
    p.monto_total AS monto,
    COALESCE(p.fecha_hora_entrega, p.fecha_hora_creacion) AS fecha_hora,
    p.id_turno,
    p.id_empleado
FROM Pedido p
WHERE p.cobrado = TRUE
  AND NOT EXISTS (
      SELECT 1 FROM Pago_pedido pp 
      WHERE pp.id_pedido = p.id_pedido
  )
ORDER BY p.id_pedido;

-- Verificar que la migración fue exitosa
SELECT 
    COUNT(*) as total_pagos_migrados,
    COUNT(DISTINCT id_pedido) as pedidos_con_pagos
FROM Pago_pedido;

-- Opcional: Verificar concordancia entre Pedido.cobrado y Pago_pedido
SELECT 
    p.id_pedido,
    p.cobrado,
    COUNT(pp.id_pago) as cantidad_pagos,
    SUM(pp.monto) as suma_pagos,
    p.monto_total
FROM Pedido p
LEFT JOIN Pago_pedido pp ON p.id_pedido = pp.id_pedido
WHERE p.cobrado = TRUE
GROUP BY p.id_pedido
HAVING cantidad_pagos = 0
LIMIT 10;
