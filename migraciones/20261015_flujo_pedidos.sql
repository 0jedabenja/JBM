ALTER TABLE Pedido MODIFY id_mp INT NULL;

INSERT INTO Estado_Pedido (nombre)
SELECT 'Comiendo'
WHERE NOT EXISTS (
    SELECT 1 FROM Estado_Pedido WHERE LOWER(nombre) = 'comiendo'
);
