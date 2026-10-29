USE lodetorres;

-- Empleados (Con el hash bcrypt para '12345678')
INSERT INTO Empleado (nombre, apellido, activo, usuario, correo, contraseña, id_rol) VALUES
('Administrador', 'Sistema', TRUE, 'Administrador', 'administrador@lodetorres.local', '$2y$10$FK0MwH70h6MNOP03tIdqRuTrGCTbtepzpB24tuhvqGAR3dzLhG7ca', 1),
('Cajero', 'Caja', TRUE, 'Cajero', 'cajero@lodetorres.local', '$2y$10$FK0MwH70h6MNOP03tIdqRuTrGCTbtepzpB24tuhvqGAR3dzLhG7ca', 2),
('Cocina', 'Cocina', TRUE, 'Cocina', 'cocina@lodetorres.local', '$2y$10$FK0MwH70h6MNOP03tIdqRuTrGCTbtepzpB24tuhvqGAR3dzLhG7ca', 3),
('Mozo', 'Salón', TRUE, 'Mozo', 'mozo@lodetorres.local', '$2y$10$FK0MwH70h6MNOP03tIdqRuTrGCTbtepzpB24tuhvqGAR3dzLhG7ca', 4);

INSERT INTO Insumo (nombre, activo, cantidad_actual, stock_minimo, costo, id_medida, id_categoria) VALUES
('Medallón de Carne 150g', TRUE, 150.00, 30.00, 45.00, 1, 1),
('Pan de Hamburguesa Artesanal', TRUE, 150.00, 30.00, 25.00, 1, 1),
('Queso Cheddar (Láminas)', TRUE, 300.00, 60.00, 12.00, 1, 1),
('Bacon ahumado (Fetas)', TRUE, 200.00, 40.00, 18.00, 1, 1),
('Lechuga capuchina', TRUE, 10.00, 2.00, 80.00, 2, 1),
('Tomate redondo', TRUE, 15.00, 3.00, 95.00, 2, 1),
('Papa Frita Bastón (Congelada)', TRUE, 80.00, 20.00, 90.00, 2, 1),
('Pan de Lomo / Baguette', TRUE, 80.00, 20.00, 45.00, 1, 1),
('Jamón cocido en fetas', TRUE, 100.00, 20.00, 380.00, 2, 1),
('Huevo', TRUE, 120.00, 30.00, 7.00, 1, 1),
('Cerveza Rubia Tirada', TRUE, 100.00, 20.00, 140.00, 4, 2),
('Gaseosa Línea Coca-Cola (Lata 354ml)', TRUE, 80.00, 20.00, 45.00, 1, 2),
('Agua Sin Gas (600ml)', TRUE, 60.00, 15.00, 30.00, 1, 2),
('Helado Artesanal Dulce de Leche', TRUE, 25.00, 5.00, 320.00, 2, 3),
('Brownie de Chocolate (Porción)', TRUE, 30.00, 8.00, 75.00, 1, 3),
('Salsa de Chocolate', TRUE, 5.00, 1.00, 250.00, 4, 3);

INSERT INTO Movimiento_Insumo (id_insumo, id_empleado, tipo, cantidad, costo_unitario, observacion)
SELECT i.id_insumo, e.id_empleado, 'saldo_inicial', i.cantidad_actual, i.costo,
       'Saldo inicial cargado con datos de prueba'
FROM Insumo i
CROSS JOIN Empleado e
WHERE e.usuario = 'Administrador'
  AND i.nombre IN (
      'Medallón de Carne 150g',
      'Pan de Hamburguesa Artesanal',
      'Queso Cheddar (Láminas)',
      'Bacon ahumado (Fetas)',
      'Lechuga capuchina',
      'Tomate redondo',
      'Papa Frita Bastón (Congelada)',
      'Pan de Lomo / Baguette',
      'Jamón cocido en fetas',
      'Huevo',
      'Cerveza Rubia Tirada',
      'Gaseosa Línea Coca-Cola (Lata 354ml)',
      'Agua Sin Gas (600ml)',
      'Helado Artesanal Dulce de Leche',
      'Brownie de Chocolate (Porción)',
      'Salsa de Chocolate'
  );

INSERT INTO Producto (nombre, activo, precio, id_categoria) VALUES
('Hamburguesa Completa con Papas', TRUE, 390.00, 1),
('Hamburguesa Doble Bacon Cheddar', TRUE, 480.00, 1),
('Lomo Completo al Plato con Papas', TRUE, 550.00, 1),
('Papas Fritas Grandes con Cheddar y Bacon', TRUE, 320.00, 1),
('Cerveza Artesanal 1L', TRUE, 280.00, 2),
('Gaseosa Lata 354ml', TRUE, 90.00, 2),
('Agua Mineral 600ml', TRUE, 70.00, 2),
('Brownie con Helado', TRUE, 240.00, 3);

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(1, 2, 1.00), -- 1 Pan
(1, 1, 1.00), -- 1 Medallón
(1, 3, 1.00), -- 1 Cheddar
(1, 5, 0.04), -- 40g Lechuga
(1, 6, 0.06), -- 60g Tomate
(1, 7, 0.20); -- 200g Papas

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(2, 2, 1.00), -- 1 Pan
(2, 1, 2.00), -- 2 Medallones
(2, 3, 2.00), -- 2 Cheddar
(2, 4, 3.00), -- 3 Fetas de bacon
(2, 7, 0.20); -- 200g Papas

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(3, 8, 1.00), -- 1 Pan de lomo
(3, 9, 0.07), -- 70g Jamón
(3, 3, 2.00), -- 2 Queso
(3, 10, 1.00), -- 1 Huevo
(3, 5, 0.04), -- Lechuga
(3, 6, 0.06), -- Tomate
(3, 7, 0.25); -- 250g Papas

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(4, 7, 0.40), -- 400g Papas
(4, 3, 3.00), -- 3 Cheddar
(4, 4, 4.00); -- 4 Bacon

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(5, 11, 1.00); -- 1 Litro

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(6, 12, 1.00); -- 1 Lata

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(7, 13, 1.00); -- 1 Botella

INSERT INTO Compone (id_producto, id_insumo, cantidad) VALUES
(8, 15, 1.00), -- 1 Brownie
(8, 14, 0.12), -- 120g Helado
(8, 16, 0.02); -- 20ml Salsa