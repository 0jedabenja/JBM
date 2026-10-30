# Cambios en Estructura de Base de Datos - Modelo de Relación N:N para Pagos

**Fecha:** 2026-10-30  
**Versión:** 1.0  
**Estado:** Implementado  

---

## 📋 Resumen

Se ha eliminado la columna `id_mp` de la tabla `Pedido` y se ha simplificado la tabla `Pago_pedido` para implementar un modelo limpio de relación **N:N entre Pedido y Metodo_pago** sin redundancias.

**Resultado:** Un pedido puede tener múltiples pagos en diferentes métodos sin necesidad de almacenar referencias en la tabla principal.

---

## 🔄 Cambios Realizados

### 1. Tabla `Pedido`

**Antes:**
```sql
CREATE TABLE Pedido (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    -- ... otros campos ...
    id_mp INT,  -- ← ELIMINADO
    id_cliente INT,
    id_descuento INT,
    cobrado BOOLEAN NOT NULL DEFAULT FALSE,
    -- ... etc
    FOREIGN KEY (id_mp) REFERENCES Metodo_pago(id_mp) ON UPDATE CASCADE  -- ← ELIMINADO
);
```

**Ahora:**
```sql
CREATE TABLE Pedido (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
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
```

**Cambios clave:**
- ✅ Eliminada columna `id_mp`
- ✅ Eliminada FK a `Metodo_pago`
- ✅ Mantiene integridad referencial con otras tablas

---

### 2. Tabla `Pago_pedido`

**Antes:**
```sql
CREATE TABLE Pago_pedido (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_mp INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_turno INT NOT NULL,        -- ← ELIMINADO (redundante)
    id_empleado INT NOT NULL,     -- ← ELIMINADO (redundante)
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_mp) REFERENCES Metodo_pago(id_mp) ON UPDATE CASCADE,
    FOREIGN KEY (id_turno) REFERENCES Turno_caja(id_turno) ON UPDATE CASCADE,
    FOREIGN KEY (id_empleado) REFERENCES Empleado(id_empleado) ON UPDATE CASCADE
);
```

**Ahora:**
```sql
CREATE TABLE Pago_pedido (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_mp INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_mp) REFERENCES Metodo_pago(id_mp) ON UPDATE CASCADE
);
```

**Cambios clave:**
- ✅ Eliminadas columnas `id_turno` e `id_empleado` (información redundante, está en Pedido)
- ✅ Tabla más limpia y enfocada en la relación N:N
- ✅ Auditoría de quién cobró se registra en tabla `Auditoria` separadamente

---

## 📝 Modelo Relacional Final

```
Pedido (
    id_pedido PK,
    descripcion,
    fecha_hora_creacion,
    fecha_hora_entrega,
    monto_total,
    cobrado,
    porcentaje_descuento,
    monto_descuento,
    id_empleado FK → Empleado,
    id_turno FK → Turno_caja,
    id_mesa FK → Mesa,
    id_estado FK → Estado_Pedido,
    id_cliente FK → Cliente,
    id_descuento FK → Descuento
)

Pago_pedido (
    id_pago PK,
    id_pedido FK → Pedido,
    id_mp FK → Metodo_pago,
    monto,
    fecha_hora
)

Metodo_pago (
    id_mp PK,
    descripcion
)
```

**Relación:** N:N  
**Interpretación:** Un pedido puede tener múltiples pagos, cada uno con un método de pago distinto.

---

## 🔧 Cambios en API y Frontend

### `apis/api_caja.php`

#### Función `obtener_movimientos_turno()`
```php
// Ahora trae el método de pago desde Pago_pedido, no desde Pedido
$stmt = mysqli_prepare($conexion, "
    SELECT 
        p.id_pedido, 
        -- ... otros campos ...
        COALESCE(mp.descripcion, 'Pendiente de cobro') AS metodo_pago, 
        ep.nombre AS estado
    FROM Pedido p 
    LEFT JOIN (
        SELECT id_pedido, id_mp, ROW_NUMBER() OVER (PARTITION BY id_pedido ORDER BY fecha_hora) as rn 
        FROM Pago_pedido
    ) pp ON pp.id_pedido = p.id_pedido AND pp.rn = 1
    LEFT JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
    -- ... otros JOINs ...
    WHERE p.id_turno = ? AND (LOWER(ep.nombre) = 'listo' OR p.cobrado = TRUE)
");
```

**Cambios:**
- ✅ Usa JOIN con subquery en `Pago_pedido` en lugar de `Pedido.id_mp`
- ✅ Selecciona el primer pago (`ROW_NUMBER()`) como método principal
- ✅ Sigue funcionando para mostrar "Pendiente de cobro" si no hay pagos

#### Acción "cobrar" - INSERT a `Pago_pedido`
```php
$stmtInsertPago = mysqli_prepare(
    $conexion,
    "INSERT INTO Pago_pedido (id_pedido, id_mp, monto, fecha_hora)
     VALUES (?, ?, ?, NOW())"
);
// Bind: (i, i, d) - ya no es (i, i, d, i, i)
```

**Cambios:**
- ✅ INSERT ya no intenta añadir `id_turno` ni `id_empleado`
- ✅ Tabla `Pago_pedido` más limpia y sin datos redundantes

#### UPDATE a `Pedido` - Acción "cobrar"
```php
$stmt = mysqli_prepare(
    $conexion,
    "UPDATE Pedido
     SET cobrado = TRUE, id_descuento = ?, monto_total = ?,
        porcentaje_descuento = ?, monto_descuento = ?
     WHERE id_pedido = ? AND cobrado = FALSE
       AND id_turno IN (SELECT id_turno FROM Turno_caja WHERE fecha_hora_cierre IS NULL)
       AND id_estado = (SELECT id_estado FROM Estado_Pedido WHERE LOWER(nombre) = 'listo' LIMIT 1)"
);
// Ya no setea id_mp (fue eliminado de la tabla)
```

**Cambios:**
- ✅ No intenta actualizar `Pedido.id_mp` (no existe)
- ✅ Mantiene sincronización con `Pago_pedido`

### `apis/boleta_pedido.php`

```php
// SELECT para obtener datos del pedido - Ahora trae método de pago desde Pago_pedido
$stmt = mysqli_prepare(
    $conexion,
    "SELECT 
        p.id_pedido, 
        p.fecha_hora_creacion, 
        p.monto_total, 
        -- ... otros campos ...
        COALESCE(mp.descripcion, 'Pendiente de cobro') AS metodo_pago
     FROM Pedido p 
     LEFT JOIN (
        SELECT id_pedido, id_mp, ROW_NUMBER() OVER (PARTITION BY id_pedido ORDER BY fecha_hora) as rn 
        FROM Pago_pedido
     ) pp ON pp.id_pedido = p.id_pedido AND pp.rn = 1
     LEFT JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
     -- ... otros JOINs ...
     WHERE p.id_pedido = ?"
);
```

**Cambios:**
- ✅ Mismo patrón: JOIN a través de `Pago_pedido`
- ✅ Comprobantes siguen mostrando el primer método de pago

### `caja.php` (Frontend)

```javascript
// Línea ~135: Eliminado data-metodo-pago
const accion = permitirCobro && listo && !cobrado
    ? `<button class="caja-button secondary" type="button" 
       data-cobrar="${Number(movimiento.id_pedido)}" 
       data-total="${Number(movimiento.monto_total)}" 
       data-cliente="${Number(movimiento.id_cliente || 0)}" 
       data-mesa="${escapeHTML(movimiento.numero_mesa ?? "")}">
       Cobrar</button>`  // ← Ya no intenta pasar data-metodo-pago
    : -- ...

// Línea ~500: Ignora data-metodo-pago si existe
const idMetodoPago = Number(
    botonCobrar.dataset.metodoPago ||  // ← Ignorado (puede no existir)
    metodosPago[0]?.id_mp || 1         // ← Usa primer método de la lista
);
```

**Cambios:**
- ✅ No intenta leer atributo `data-metodo-pago` que ya no existe
- ✅ Usa primer método de la lista como predeterminado
- ✅ Usuario puede cambiar método en el diálogo

---

## 📋 Checklist de Validación

- [x] BD/creacionbd.sql actualizado
- [x] Tabla `Pedido` sin columna `id_mp`
- [x] Tabla `Pago_pedido` simplificada (sin `id_turno`, sin `id_empleado`)
- [x] apis/api_caja.php actualizado (SELECT, INSERT, UPDATE)
- [x] apis/boleta_pedido.php actualizado (SELECT)
- [x] caja.php actualizado (eliminar `data-metodo-pago`)
- [x] Script de migración creado: `BD/migracion_eliminar_id_mp_pedido.sql`
- [x] Sin referencias a `Pedido.id_mp` en el código

---

## 🚀 Próximos Pasos

### Para ambientes nuevos:
1. Ejecutar `BD/creacionbd.sql` (ya tiene la estructura correcta)
2. Sistema listo para usar

### Para ambientes existentes:
1. **Backup:** Crear backup completo de la BD antes de migrar
2. **Migración:** Ejecutar `BD/migracion_eliminar_id_mp_pedido.sql`
3. **Validación:** 
   ```sql
   -- Verificar estructura
   SHOW CREATE TABLE Pedido;
   SHOW CREATE TABLE Pago_pedido;
   
   -- Verificar datos
   SELECT COUNT(*) FROM Pedido;
   SELECT COUNT(*) FROM Pago_pedido;
   ```
4. **Testing:** Probar workflow completo de cobro (crear pedido → cobrar → ver comprobante)

---

## 🔍 Consultas Útiles

### Ver método de pago de un pedido (después de migración):
```sql
SELECT 
    p.id_pedido,
    p.monto_total,
    p.cobrado,
    STRING_AGG(CONCAT(mp.descripcion, ': $', pp.monto), ', ') AS metodos_y_montos,
    SUM(pp.monto) AS total_cobrado
FROM Pedido p
LEFT JOIN Pago_pedido pp ON pp.id_pedido = p.id_pedido
LEFT JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
WHERE p.id_pedido = ?
GROUP BY p.id_pedido;
```

### Ver todos los pagos de un turno:
```sql
SELECT 
    pp.id_pago,
    pp.id_pedido,
    mp.descripcion AS metodo,
    pp.monto,
    pp.fecha_hora
FROM Pago_pedido pp
INNER JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
INNER JOIN Pedido p ON p.id_pedido = pp.id_pedido
WHERE p.id_turno = ?
ORDER BY pp.fecha_hora DESC;
```

### Verificar integridad de datos:
```sql
-- Pedidos sin pagos registrados
SELECT p.id_pedido, p.monto_total, p.cobrado
FROM Pedido p
WHERE p.cobrado = TRUE
  AND NOT EXISTS (SELECT 1 FROM Pago_pedido pp WHERE pp.id_pedido = p.id_pedido);

-- Pagos con montos que no coinciden
SELECT 
    p.id_pedido,
    p.monto_total,
    SUM(pp.monto) as suma_pagos
FROM Pedido p
LEFT JOIN Pago_pedido pp ON pp.id_pedido = p.id_pedido
GROUP BY p.id_pedido
HAVING p.monto_total != SUM(pp.monto);
```

---

## 📚 Documentación Relacionada

- [PAGO_DIVIDIDO.md](./PAGO_DIVIDIDO.md) - Detalles de la implementación de pago dividido
- [TESTING_PAGO_DIVIDIDO.md](./TESTING_PAGO_DIVIDIDO.md) - Guía de pruebas
- [RESUMEN_IMPLEMENTACION.md](./RESUMEN_IMPLEMENTACION.md) - Resumen ejecutivo

---

## 🆘 Preguntas Frecuentes

**¿Puedo seguir usando `Pedido.id_mp`?**  
No. Esa columna se ha eliminado. Los datos de método de pago se leen exclusivamente desde `Pago_pedido`.

**¿Qué pasa con pagos históricos?**  
Se recomienda ejecutar el script de migración que conserva todos los datos históricos.

**¿Puedo tener un pedido cobrado sin registros en `Pago_pedido`?**  
Sí, hasta ejecutar la migración. Después, deberías validar y corregir esos casos.

**¿Cómo se registra auditoría de quién cobró?**  
En la tabla `Auditoria`, no en `Pago_pedido`. Esta es más limpia y enfocada en la relación de pagos.

---

**Versión:** 1.0  
**Última actualización:** 2026-10-30
