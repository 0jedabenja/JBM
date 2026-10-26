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
    id_mp INT,
    cobrado BOOLEAN NOT NULL DEFAULT FALSE,
    porcentaje_descuento DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    monto_descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_pedido),
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON UPDATE CASCADE,
    FOREIGN KEY (id_turno) REFERENCES Turno_caja(id_turno) ON UPDATE CASCADE,
    FOREIGN KEY (id_mesa) REFERENCES Mesa(id_mesa) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_estado) REFERENCES Estado_Pedido(id_estado) ON UPDATE CASCADE,
    FOREIGN KEY (id_cliente) REFERENCES Cliente(id_cliente) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_descuento) REFERENCES Descuento(id_descuento) ON DELETE SET NULL ON UPDATE CASCADE,
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
