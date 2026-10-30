-- ============================================================================
-- Migración: Eliminar columna id_mp de tabla Pedido
-- ============================================================================
-- Fecha: 2026-10-30
-- Descripción:
--   Elimina la columna id_mp de la tabla Pedido, ya que ahora se gestiona
--   completamente a través de la tabla Pago_pedido (relación N:N).
--
-- Cambios:
--   1. Modifica tabla Pedido: elimina columna id_mp y su FK
--   2. Modifica tabla Pago_pedido: simplifica eliminando id_turno e id_empleado
--
-- Precondiciones:
--   - La tabla Pedido existe
--   - La tabla Pago_pedido existe con datos (opcional, se conservan)
--   - Ya existe tabla Metodo_pago
--
-- Postmigracion:
--   - Todos los pedidos pagarán únicamente a través de Pago_pedido
--   - El sistema no acededará más a Pedido.id_mp
-- ============================================================================

-- Primero, eliminamos la FK de Pedido.id_mp si existe
ALTER TABLE Pedido DROP FOREIGN KEY Pedido_ibfk_6;

-- Eliminamos la columna id_mp de Pedido
ALTER TABLE Pedido DROP COLUMN id_mp;

-- Ahora simplificamos Pago_pedido si tiene las columnas id_turno e id_empleado
-- (En caso de que se haya ejecutado la versión anterior)
ALTER TABLE Pago_pedido DROP FOREIGN KEY Pago_pedido_ibfk_3;
ALTER TABLE Pago_pedido DROP FOREIGN KEY Pago_pedido_ibfk_4;

-- Si las columnas existen, las eliminamos
ALTER TABLE Pago_pedido 
  DROP COLUMN IF EXISTS id_turno,
  DROP COLUMN IF EXISTS id_empleado;

-- Verificamos la estructura final de Pago_pedido
-- Debe tener: id_pago (PK), id_pedido (FK), id_mp (FK), monto, fecha_hora
SHOW CREATE TABLE Pago_pedido;

-- Validación: Contar registros para asegurar integridad
SELECT 
  'Pedidos totales' AS item, COUNT(*) as cantidad FROM Pedido
UNION ALL
SELECT 'Pedidos sin pago registrado' AS item, COUNT(*) FROM Pedido p
  WHERE NOT EXISTS (SELECT 1 FROM Pago_pedido pp WHERE pp.id_pedido = p.id_pedido)
UNION ALL
SELECT 'Pagos totales registrados' AS item, COUNT(*) FROM Pago_pedido;
