CREATE TABLE IF NOT EXISTS Auditoria (
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

CREATE TABLE IF NOT EXISTS Movimiento_Insumo (
    id_movimiento BIGINT AUTO_INCREMENT,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_insumo INT NOT NULL,
    id_empleado INT,
    tipo VARCHAR(30) NOT NULL,
    cantidad DECIMAL(12,2) NOT NULL,
    costo_unitario DECIMAL(10,2) NOT NULL,
    referencia_id BIGINT,
    PRIMARY KEY (id_movimiento),
    KEY idx_movimiento_insumo_fecha (id_insumo, fecha_hora),
    KEY idx_movimiento_tipo_fecha (tipo, fecha_hora),
    FOREIGN KEY (id_insumo) REFERENCES Insumo(id_insumo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON DELETE SET NULL ON UPDATE CASCADE
);
