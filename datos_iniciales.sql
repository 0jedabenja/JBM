USE lodetorres;

INSERT INTO Rol (nombre) VALUES
('Administrador'),
('Cajero'),
('Cocina'),
('Mozo');

INSERT INTO Medida (unidad, simbolo) VALUES
('Unidad', 'u'),
('Kilogramo', 'kg'),
('Gramo', 'g'),
('Litro', 'l'),
('Mililitro', 'ml');

INSERT INTO Categoria (titulo, descripcion) VALUES
('Comidas', 'Platos y comidas principales.'),
('Bebidas', 'Bebidas con y sin alcohol.'),
('Postres', 'Postres y opciones dulces.');

INSERT INTO Estado_Mesa (nombre) VALUES
('Libre'),
('Ocupada'),
('Para limpiar');

INSERT INTO Estado_Pedido (nombre) VALUES
('Pendiente'),
('En preparación'),
('Listo'),
('Entregado'),
('Cancelado');

INSERT INTO Descuento (tipo, valor) VALUES
('porcentaje', 0.00),
('porcentaje', 10.00),
('porcentaje', 20.00);

INSERT INTO Metodo_pago (descripcion) VALUES
('Efectivo'),
('Tarjeta de débito'),
('Tarjeta de crédito'),
('Transferencia bancaria');
