CREATE DATABASE lodetorres;
USE lodetorres;

CREATE TABLE Medida (
    id_medida INT AUTO_INCREMENT,
    unidad VARCHAR(50) NOT NULL,
    simbolo VARCHAR(10) NOT NULL,
    PRIMARY KEY (id_medida)
);

CREATE TABLE Categoria (
    id_categoria INT AUTO_INCREMENT,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    PRIMARY KEY (id_categoria)
);

CREATE TABLE Rol (
    id_rol INT AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_rol)
);

CREATE TABLE Empleado (
    id_empleado INT AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(255) NOT NULL UNIQUE,
    contraseña VARCHAR(255) NOT NULL,
    token_recuperacion VARCHAR(64) DEFAULT NULL,
    token_expiracion DATETIME DEFAULT NULL,
    id_rol INT NOT NULL,
    PRIMARY KEY (id_empleado),
    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol) ON UPDATE CASCADE
);

CREATE TABLE Turno_caja (
    id_turno INT AUTO_INCREMENT,
    fecha_hora_apertura DATETIME NOT NULL,
    fecha_hora_cierre DATETIME,
    monto_inicial DECIMAL(10,2) NOT NULL,
    monto_final DECIMAL(10,2),
    id_empleado INT NOT NULL,
    PRIMARY KEY (id_turno),
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON UPDATE CASCADE
);

CREATE TABLE Cliente (
    id_cliente INT AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    telefono VARCHAR(20),
    direccion VARCHAR(255),
    PRIMARY KEY (id_cliente)
);

CREATE TABLE Estado_Mesa (
    id_estado INT AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_estado)
);

CREATE TABLE Estado_Pedido (
    id_estado INT AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_estado)
);

CREATE TABLE Descuento (
    id_descuento INT AUTO_INCREMENT,
    tipo VARCHAR(50) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_descuento)
);

CREATE TABLE Metodo_pago (
    id_mp INT AUTO_INCREMENT,
    descripcion VARCHAR(50) NOT NULL,
    PRIMARY KEY (id_mp)
);

CREATE TABLE Insumo (
    id_insumo INT AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    cantidad_actual DECIMAL(10,2) NOT NULL,
    stock_minimo DECIMAL(10,2) NOT NULL,
    costo DECIMAL(10,2) NOT NULL,
    id_medida INT,
    id_categoria INT,
    PRIMARY KEY (id_insumo),
    FOREIGN KEY (id_medida) REFERENCES Medida(id_medida) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE Conteo_Insumo (
    id_conteo INT AUTO_INCREMENT,
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME,
    estado ENUM('En curso', 'Finalizado', 'Cancelado') NOT NULL DEFAULT 'En curso',
    tipo_categoria ENUM('todas', 'categoria', 'sin_categoria') NOT NULL DEFAULT 'todas',
    id_categoria INT,
    id_empleado INT NOT NULL,
    PRIMARY KEY (id_conteo),
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON UPDATE CASCADE
);

CREATE TABLE Detalle_Conteo_Insumo (
    id_conteo INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad_sistema DECIMAL(10,2) NOT NULL,
    cantidad_contada DECIMAL(10,2),
    diferencia DECIMAL(10,2),
    UNIQUE KEY uq_conteo_insumo (id_conteo, id_insumo),
    FOREIGN KEY (id_conteo) REFERENCES Conteo_Insumo(id_conteo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES Insumo(id_insumo) ON UPDATE CASCADE
);

CREATE TABLE Producto (
    id_producto INT AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    precio DECIMAL(10,2) NOT NULL,
    id_categoria INT,
    PRIMARY KEY (id_producto),
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE Mesa (
    id_mesa INT AUTO_INCREMENT,
    numero INT NOT NULL,
    pos_x INT,
    pos_y INT,
    ancho INT,
    id_estado INT NOT NULL,
    PRIMARY KEY (id_mesa),
    UNIQUE KEY uq_mesa_numero (numero),
    FOREIGN KEY (id_estado) REFERENCES Estado_Mesa(id_estado) ON UPDATE CASCADE
);

CREATE TABLE Compra (
    id_compra INT AUTO_INCREMENT,
    costo_total DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    observacion TEXT,
    id_empleado INT NOT NULL,
    PRIMARY KEY (id_compra),
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON UPDATE CASCADE
);

CREATE TABLE Pedido (
    id_pedido INT AUTO_INCREMENT,
    descripcion TEXT,
    fecha_hora_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_hora_entrega DATETIME,
    monto_total DECIMAL(10,2) NOT NULL,
    id_empleado INT NOT NULL,
    id_turno INT NOT NULL,
    id_mesa INT,
    id_estado INT NOT NULL,
    id_cliente INT,
    id_descuento INT,
    cobrado BOOLEAN NOT NULL DEFAULT FALSE,
    porcentaje_descuento DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    monto_descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_pedido),
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON UPDATE CASCADE,
    FOREIGN KEY (id_turno) REFERENCES Turno_caja(id_turno) ON UPDATE CASCADE,
    FOREIGN KEY (id_mesa) REFERENCES Mesa(id_mesa) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_estado) REFERENCES Estado_Pedido(id_estado) ON UPDATE CASCADE,
    FOREIGN KEY (id_cliente) REFERENCES Cliente(id_cliente) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_descuento) REFERENCES Descuento(id_descuento) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE Pago_pedido (
    id_pago INT AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_mp INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pago),
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_mp) REFERENCES Metodo_pago(id_mp) ON UPDATE CASCADE
);

CREATE TABLE Incluye (
    id_compra INT,
    id_insumo INT,
    cantidad DECIMAL(10,2) NOT NULL,
    costo DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_compra, id_insumo),
    FOREIGN KEY (id_compra) REFERENCES Compra(id_compra) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES Insumo(id_insumo) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Compone (
    id_producto INT,
    id_insumo INT,
    cantidad DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_producto, id_insumo),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES Insumo(id_insumo) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Contiene (
    id_pedido INT,
    id_producto INT,
    cantidad INT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    completado BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id_pedido, id_producto),
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Auditoria (
    id_auditoria BIGINT AUTO_INCREMENT,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_empleado INT,
    accion VARCHAR(60) NOT NULL,
    entidad VARCHAR(60) NOT NULL,
    id_entidad BIGINT,
    detalle JSON,
    ip VARCHAR(45),
    PRIMARY KEY (id_auditoria),
    KEY idx_auditoria_fecha (fecha_hora),
    KEY idx_auditoria_empleado_fecha (id_empleado, fecha_hora),
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE Movimiento_Insumo (
    id_movimiento BIGINT AUTO_INCREMENT,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_insumo INT NOT NULL,
    id_empleado INT,
    tipo VARCHAR(30) NOT NULL,
    cantidad DECIMAL(12,2) NOT NULL,
    costo_unitario DECIMAL(10,2) NOT NULL,
    referencia_id BIGINT,
    observacion VARCHAR(255),
    PRIMARY KEY (id_movimiento),
    KEY idx_movimiento_insumo_fecha (id_insumo, fecha_hora),
    KEY idx_movimiento_tipo_fecha (tipo, fecha_hora),
    FOREIGN KEY (id_insumo) REFERENCES Insumo(id_insumo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON DELETE SET NULL ON UPDATE CASCADE
);
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

INSERT INTO Cliente (id_cliente, nombre, apellido, activo) VALUES
(1, 'Consumidor', 'Final', TRUE);

INSERT INTO Estado_Mesa (nombre) VALUES
('Libre'),
('Ocupada'),
('Para limpiar');

INSERT INTO Estado_Pedido (nombre) VALUES
('Pendiente'),
('En preparación'),
('Comiendo'),
('Listo'),
('Entregado'),
('Cancelado');

INSERT INTO Descuento (tipo, valor) VALUES
('porcentaje', 0.00),
('porcentaje', 10.00),
('porcentaje', 20.00),
('porcentaje', 100.00);

INSERT INTO Metodo_pago (descripcion) VALUES
('Efectivo'),
('Tarjeta de débito'),
('Tarjeta de crédito'),
('Transferencia bancaria');

INSERT INTO Empleado (nombre, apellido, activo, usuario, contraseña, id_rol) VALUES
('Luis', 'Torres', TRUE, 'Luistorres', '$2y$10$ygA54RXXYRe0oHhRJaxGPOkYN3JHXPsrySdJ7ztzY155nTHUiQhDS', 1)

-- Objetos reutilizados por las APIs del sistema.
DELIMITER $$
CREATE PROCEDURE sp_listar_empleados(IN p_filtro VARCHAR(200), IN p_campo VARCHAR(30), IN p_limite INT, IN p_offset INT)
BEGIN
  SET @q = CONVERT(CONCAT('%', COALESCE(p_filtro, ''), '%') USING utf8mb4) COLLATE utf8mb4_general_ci;
  IF COALESCE(p_filtro, '') = '' THEN
    SELECT id_empleado, nombre, apellido, usuario, correo, id_rol, rol
    FROM v_empleados WHERE activo = TRUE ORDER BY apellido, nombre LIMIT p_limite OFFSET p_offset;
  ELSEIF p_campo IN ('id_empleado','nombre','apellido','usuario','correo','rol') THEN
    SELECT id_empleado, nombre, apellido, usuario, correo, id_rol, rol FROM v_empleados WHERE activo = TRUE
    AND CASE p_campo WHEN 'id_empleado' THEN CAST(id_empleado AS CHAR) WHEN 'nombre' THEN nombre WHEN 'apellido' THEN apellido WHEN 'usuario' THEN usuario WHEN 'correo' THEN correo ELSE rol END LIKE @q
    ORDER BY apellido, nombre LIMIT p_limite OFFSET p_offset;
  ELSE
    SELECT id_empleado, nombre, apellido, usuario, correo, id_rol, rol FROM v_empleados WHERE activo = TRUE
    AND (CAST(id_empleado AS CHAR) LIKE @q OR nombre LIKE @q OR apellido LIKE @q OR usuario LIKE @q OR correo LIKE @q OR rol LIKE @q)
    ORDER BY apellido, nombre LIMIT p_limite OFFSET p_offset;
  END IF;
END$$

CREATE PROCEDURE sp_listar_clientes(IN p_filtro VARCHAR(200), IN p_campo VARCHAR(30), IN p_limite INT, IN p_offset INT)
BEGIN
  SET @q = CONVERT(CONCAT('%', COALESCE(p_filtro, ''), '%') USING utf8mb4) COLLATE utf8mb4_general_ci;
  SELECT id_cliente, nombre, apellido, telefono, direccion FROM v_clientes WHERE activo = TRUE
  AND (COALESCE(p_filtro,'') = '' OR CASE p_campo WHEN 'id_cliente' THEN CAST(id_cliente AS CHAR) WHEN 'nombre' THEN nombre WHEN 'apellido' THEN apellido WHEN 'telefono' THEN telefono WHEN 'direccion' THEN direccion ELSE nombre END LIKE @q
       OR (p_campo='todos' AND (CAST(id_cliente AS CHAR) LIKE @q OR nombre LIKE @q OR apellido LIKE @q OR telefono LIKE @q OR direccion LIKE @q))
       OR (p_campo='nombre_apellido' AND CONCAT(nombre,' ',apellido) LIKE @q))
  ORDER BY apellido, nombre LIMIT p_limite OFFSET p_offset;
END$$

CREATE PROCEDURE sp_listar_productos(IN p_filtro VARCHAR(200), IN p_campo VARCHAR(30), IN p_limite INT, IN p_offset INT)
BEGIN
  SET @q = CONVERT(CONCAT('%', COALESCE(p_filtro, ''), '%') USING utf8mb4) COLLATE utf8mb4_general_ci;
  SELECT id_producto, nombre, precio, id_categoria, categoria, disponible FROM v_productos WHERE activo = TRUE
  AND (COALESCE(p_filtro,'')='' OR CASE p_campo WHEN 'id_producto' THEN CAST(id_producto AS CHAR) WHEN 'nombre' THEN nombre WHEN 'precio' THEN CAST(precio AS CHAR) WHEN 'categoria' THEN categoria ELSE nombre END LIKE @q
       OR (p_campo='todos' AND (nombre LIKE @q OR CAST(id_producto AS CHAR) LIKE @q OR CAST(precio AS CHAR) LIKE @q OR categoria LIKE @q)))
  ORDER BY nombre LIMIT p_limite OFFSET p_offset;
END$$

CREATE PROCEDURE sp_listar_insumos(IN p_filtro VARCHAR(200), IN p_campo VARCHAR(30), IN p_limite INT, IN p_offset INT)
BEGIN
  SET @q = CONVERT(CONCAT('%', COALESCE(p_filtro, ''), '%') USING utf8mb4) COLLATE utf8mb4_general_ci;
  SELECT id_insumo, nombre, cantidad_actual, stock_minimo, costo, id_medida, unidad FROM v_insumos WHERE activo = TRUE
  AND (COALESCE(p_filtro,'')='' OR CASE p_campo WHEN 'id_insumo' THEN CAST(id_insumo AS CHAR) WHEN 'nombre' THEN nombre WHEN 'unidad' THEN unidad WHEN 'cantidad_actual' THEN CAST(cantidad_actual AS CHAR) WHEN 'stock_minimo' THEN CAST(stock_minimo AS CHAR) WHEN 'costo' THEN CAST(costo AS CHAR) ELSE nombre END LIKE @q
       OR (p_campo='todos' AND (nombre LIKE @q OR CAST(id_insumo AS CHAR) LIKE @q OR unidad LIKE @q OR CAST(cantidad_actual AS CHAR) LIKE @q OR CAST(stock_minimo AS CHAR) LIKE @q OR CAST(costo AS CHAR) LIKE @q)))
  ORDER BY nombre LIMIT p_limite OFFSET p_offset;
END$$

CREATE PROCEDURE sp_desactivar(IN p_tipo VARCHAR(20), IN p_id INT)
BEGIN
  CASE p_tipo
    WHEN 'empleado' THEN UPDATE Empleado SET activo=0 WHERE id_empleado=p_id;
    WHEN 'cliente' THEN UPDATE Cliente SET activo=0 WHERE id_cliente=p_id;
    WHEN 'producto' THEN UPDATE Producto SET activo=0 WHERE id_producto=p_id;
    WHEN 'insumo' THEN UPDATE Insumo SET activo=0 WHERE id_insumo=p_id;
    ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Entidad no valida';
  END CASE;
END$$

CREATE PROCEDURE sp_password_hash(IN p_id INT)
BEGIN SELECT contraseña FROM Empleado WHERE id_empleado=p_id AND activo=TRUE; END$$

CREATE PROCEDURE sp_actualizar_perfil(IN p_id INT, IN p_nombre VARCHAR(100), IN p_apellido VARCHAR(100), IN p_usuario VARCHAR(50), IN p_correo VARCHAR(255))
BEGIN
  UPDATE Empleado SET nombre=p_nombre, apellido=p_apellido, usuario=p_usuario, correo=p_correo WHERE id_empleado=p_id AND activo=TRUE;
END$$

CREATE PROCEDURE sp_datos_usuario(IN p_id INT)
BEGIN SELECT id_empleado,nombre,apellido,usuario,correo,id_rol,rol FROM v_empleados WHERE id_empleado=p_id AND activo=TRUE; END$$

CREATE PROCEDURE sp_reestablecer_password_admin(IN p_id INT, IN p_hash VARCHAR(255))
BEGIN
  UPDATE Empleado SET contraseña=p_hash, token_recuperacion=NULL, token_expiracion=NULL WHERE id_empleado=p_id AND activo=TRUE;
  SELECT ROW_COUNT() AS filas_afectadas;
END$$
DELIMITER ;

-- Vista v_empleados: reúne datos de empleados junto con el nombre de su rol.
CREATE VIEW v_empleados AS
SELECT e.id_empleado,e.nombre,e.apellido,e.activo,e.usuario,e.correo,e.id_rol,r.nombre rol
FROM Empleado e JOIN Rol r ON r.id_rol=e.id_rol;

-- Vista v_clientes: expone únicamente los clientes activos y sus datos básicos.
CREATE VIEW v_clientes AS SELECT id_cliente,nombre,apellido,activo,telefono,direccion FROM Cliente WHERE activo=TRUE;

-- Vista v_insumos: muestra los insumos activos junto con su unidad de medida.
CREATE VIEW v_insumos AS SELECT i.*,m.unidad FROM Insumo i LEFT JOIN Medida m ON m.id_medida=i.id_medida WHERE i.activo=TRUE;

-- Vista v_productos: muestra productos activos, categoría y si tienen insumos suficientes.
CREATE VIEW v_productos AS
SELECT p.id_producto,p.nombre,p.activo,p.precio,p.id_categoria,c.titulo categoria,
       CASE WHEN EXISTS (SELECT 1 FROM Compone co LEFT JOIN Insumo i ON i.id_insumo=co.id_insumo
                         WHERE co.id_producto=p.id_producto AND (i.id_insumo IS NULL OR i.activo<>TRUE OR co.cantidad<=0 OR i.cantidad_actual<co.cantidad)) THEN 0 ELSE 1 END disponible
FROM Producto p LEFT JOIN Categoria c ON c.id_categoria=p.id_categoria WHERE p.activo=TRUE;

-- Trigger trg_empleado_correo_bi: al insertar un empleado, elimina espacios del correo y lo convierte a minúsculas.
DELIMITER $$
CREATE TRIGGER trg_empleado_correo_bi BEFORE INSERT ON Empleado FOR EACH ROW
BEGIN
  IF NEW.correo IS NOT NULL THEN SET NEW.correo=LOWER(TRIM(NEW.correo)); END IF;
END$$

-- Trigger trg_empleado_correo_bu: al modificar un empleado, elimina espacios del correo y lo convierte a minúsculas.
CREATE TRIGGER trg_empleado_correo_bu BEFORE UPDATE ON Empleado FOR EACH ROW
BEGIN
  IF NEW.correo IS NOT NULL THEN SET NEW.correo=LOWER(TRIM(NEW.correo)); END IF;
END$$
DELIMITER ;
