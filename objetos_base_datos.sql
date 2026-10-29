-- Objetos reutilizados por las APIs del sistema.
-- Ejecutar una vez despues de creacionbd.sql.
USE lodetorres;

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
