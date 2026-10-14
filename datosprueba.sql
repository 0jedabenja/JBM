INSERT INTO Insumo (nombre, activo, cantidad_actual, stock_minimo, costo, id_medida, id_categoria) VALUES
-- Comidas e Ingredientes
('Pan de Hamburguesa', TRUE, 120.00, 30.00, 18.50, 1, 1),
('Medallón de Carne Vacuna (150g)', TRUE, 150.00, 40.00, 45.00, 1, 1),
('Queso Cheddar en Láminas', TRUE, 5.00, 1.00, 320.00, 2, 1),
('Jamón Cocido', TRUE, 4.00, 1.00, 280.00, 2, 1),
('Papas Fritas Congeladas', TRUE, 30.00, 8.00, 95.00, 2, 1),
('Lechuga', TRUE, 3.00, 1.00, 60.00, 2, 1),
('Tomate', TRUE, 4.00, 1.00, 75.00, 2, 1),
('Aceite de Girasol', TRUE, 15.00, 4.00, 110.00, 4, 1),
('Mayonesa (Bidón)', TRUE, 8.00, 2.00, 220.00, 4, 1),
('Pan de Lomo / Chivito', TRUE, 80.00, 20.00, 25.00, 1, 1),
('Bife de Lomo / Vacuno para Chivito', TRUE, 10.00, 3.00, 350.00, 2, 1),
('Huevos', TRUE, 180.00, 30.00, 6.00, 1, 1),
('Panceta Ahumada', TRUE, 3.00, 1.00, 420.00, 2, 1),

-- Bebidas
('Refresco Coca-Cola 1.5L', TRUE, 50.00, 15.00, 68.00, 1, 2),
('Agua Mineral 600ml', TRUE, 40.00, 10.00, 24.00, 1, 2),
('Cerveza Pilsen 1L', TRUE, 60.00, 15.00, 95.00, 1, 2),
('Naranjas (para jugo)', TRUE, 25.00, 5.00, 45.00, 2, 2),

-- Postres / Insumos dulces
('Dulce de Leche', TRUE, 6.00, 2.00, 180.00, 2, 3),
('Harina de Trigo', TRUE, 15.00, 5.00, 45.00, 2, 3),
('Azúcar', TRUE, 10.00, 3.00, 50.00, 2, 3),
('Leche Entera', TRUE, 20.00, 5.00, 42.00, 4, 3);

INSERT INTO Producto (nombre, activo, precio, id_categoria) VALUES
-- Comidas
('Hamburguesa Simple', TRUE, 220.00, 1),
('Hamburguesa Completa', TRUE, 320.00, 1),
('Chivito al Pan', TRUE, 380.00, 1),
('Chivito al Plato', TRUE, 480.00, 1),
('Porción de Papas Fritas', TRUE, 160.00, 1),
('Papas con Cheddar y Panceta', TRUE, 260.00, 1),

-- Bebidas
('Refresco Coca-Cola 1.5L', TRUE, 140.00, 2),
('Agua Mineral 600ml', TRUE, 60.00, 2),
('Cerveza Pilsen 1L', TRUE, 200.00, 2),
('Jugo Natural de Naranja', TRUE, 120.00, 2),

-- Postres
('Flan Casero con Dulce de Leche', TRUE, 130.00, 3),
('Postre Chajá', TRUE, 150.00, 3);