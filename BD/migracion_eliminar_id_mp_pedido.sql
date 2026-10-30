-- Migración para BD existente (hacer backup antes).
-- 1) Crea Pago_pedido si no existe
-- 2) Copia los pagos históricos desde Pedido.id_mp
-- 3) Elimina Pedido.id_mp (y su FK) y las columnas sobrantes de Pago_pedido
USE lodetorres;

CREATE TABLE IF NOT EXISTS Pago_pedido (
    id_pago INT AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_mp INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pago),
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_mp) REFERENCES Metodo_pago(id_mp) ON UPDATE CASCADE
);

-- Copia los pagos históricos (requiere que Pedido todavía tenga id_mp)
INSERT INTO Pago_pedido (id_pedido, id_mp, monto, fecha_hora)
SELECT p.id_pedido, p.id_mp, p.monto_total, p.fecha_hora_creacion
FROM Pedido p
WHERE p.cobrado = TRUE AND p.id_mp IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM Pago_pedido pp WHERE pp.id_pedido = p.id_pedido);

-- Eliminar la FK de Pedido.id_mp sea cual sea su nombre
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Pedido'
              AND COLUMN_NAME = 'id_mp' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql := IF(@fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE Pedido DROP FOREIGN KEY ', @fk));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
ALTER TABLE Pedido DROP COLUMN id_mp;

-- Quitar id_turno / id_empleado de Pago_pedido si existen de una versión anterior
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Pago_pedido'
              AND COLUMN_NAME = 'id_turno' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql := IF(@fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE Pago_pedido DROP FOREIGN KEY ', @fk));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Pago_pedido'
              AND COLUMN_NAME = 'id_empleado' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @sql := IF(@fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE Pago_pedido DROP FOREIGN KEY ', @fk));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
ALTER TABLE Pago_pedido DROP COLUMN IF EXISTS id_turno, DROP COLUMN IF EXISTS id_empleado;
