# Referencia Rápida - Modelo N:N de Pagos

## 🔑 Cambio Principal

**ANTES:** `Pedido.id_mp` (un método de pago por pedido)  
**AHORA:** `Pago_pedido` (múltiples métodos por pedido, relación N:N)

---

## 📝 MER Final

```sql
Pedido (sin id_mp) ←→ Pago_pedido ←→ Metodo_pago
                          ↑
                   (id_pago, id_pedido FK, id_mp FK, monto, fecha_hora)
```

---

## 🗂️ Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `BD/creacionbd.sql` | Elimina `id_mp` de Pedido; simplifica Pago_pedido |
| `apis/api_caja.php` | SELECT/INSERT/UPDATE usan Pago_pedido |
| `apis/boleta_pedido.php` | SELECT trae método desde Pago_pedido |
| `caja.php` | Elimina `data-metodo-pago` innecesario |

---

## 🆕 Archivos Nuevos

| Archivo | Propósito |
|---------|-----------|
| `BD/migracion_eliminar_id_mp_pedido.sql` | Script para migrar BD existentes |
| `CAMBIOS_ESTRUCTURA_PEDIDO.md` | Documentación técnica completa |
| `MODELO_RELACIONAL_ANTES_DESPUES.md` | Diagramas y comparativas |
| `IMPLEMENTACION_COMPLETADA.md` | Resumen ejecutivo |
| `CHANGELOG_OCTUBRE_2026.md` | Historial de cambios |

---

## ✅ Validación Rápida

```bash
# Ver estructura de Pedido (NO debe tener id_mp)
SHOW COLUMNS FROM Pedido;

# Ver estructura de Pago_pedido (NO debe tener id_turno ni id_empleado)
SHOW COLUMNS FROM Pago_pedido;

# Contar pagos por pedido
SELECT id_pedido, COUNT(*) as num_pagos 
FROM Pago_pedido 
GROUP BY id_pedido;
```

---

## 🚀 Cómo Usar el Sistema

### Crear Pedido
```sql
INSERT INTO Pedido (monto_total, id_empleado, id_turno, id_estado, cobrado)
VALUES (800, 1, 1, 1, FALSE);
-- NOTA: No hay id_mp aquí
```

### Cobrar con Múltiples Métodos
```sql
-- Efectivo $500
INSERT INTO Pago_pedido (id_pedido, id_mp, monto, fecha_hora)
VALUES (1, 1, 500, NOW());

-- Tarjeta $300
INSERT INTO Pago_pedido (id_pedido, id_mp, monto, fecha_hora)
VALUES (1, 2, 300, NOW());

-- Marcar como cobrado
UPDATE Pedido SET cobrado = TRUE WHERE id_pedido = 1;
```

### Obtener Método de Pago de un Pedido
```sql
-- Todos los métodos usados
SELECT mp.descripcion, pp.monto
FROM Pago_pedido pp
JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
WHERE pp.id_pedido = 1;

-- Primer método (para compatibilidad)
SELECT mp.descripcion
FROM Pago_pedido pp
JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
WHERE pp.id_pedido = 1
ORDER BY pp.fecha_hora
LIMIT 1;
```

---

## 🔧 Flujo en el Código

### Frontend (caja.php)
1. Usuario hace clic en "Cobrar"
2. Se abre diálogo con campos para múltiples métodos
3. Usuario selecciona: Efectivo $500 + Tarjeta $300
4. Se envía JSON: `{accion: "cobrar", id_pedido: 1, pagos: [{id_mp: 1, monto: 500}, {id_mp: 2, monto: 300}]}`

### Backend (api_caja.php)
1. Valida que suma de pagos = monto_total
2. Abre transacción
3. Inserta N filas en Pago_pedido
4. Actualiza Pedido.cobrado = TRUE
5. Commit transacción

---

## ⚠️ Migración de BD Existente

```bash
# 1. BACKUP (IMPORTANTE!)
mysqldump -u root -p lodetorres > backup_$(date +%Y%m%d).sql

# 2. Ejecutar migración
mysql -u root -p lodetorres < BD/migracion_eliminar_id_mp_pedido.sql

# 3. Validar
mysql -u root -p lodetorres -e "SHOW COLUMNS FROM Pedido;" | grep id_mp
# Resultado: (vacío = correcto)
```

---

## 🐛 Debugging

### Si ves error "Unknown column id_mp in Pedido"
- ✅ Migración ejecutada correctamente
- Verifica que las queries ya usen Pago_pedido

### Si ves error en Pago_pedido "Unknown column id_turno"
- ✅ Migración ejecutada correctamente
- Verifica que api_caja.php use INSERT sin id_turno

### Si ves pedidos sin pagos registrados
```sql
-- Detectar
SELECT id_pedido FROM Pedido p
WHERE p.cobrado = TRUE
AND NOT EXISTS (SELECT 1 FROM Pago_pedido pp WHERE pp.id_pedido = p.id_pedido);

-- Verificar antes de migrar
-- Estos pedidos necesitan registros en Pago_pedido manualmente
```

---

## 📊 Queries Útiles

### Resumen de pagos por pedido
```sql
SELECT 
    p.id_pedido,
    p.monto_total,
    COUNT(pp.id_pago) as num_pagos,
    GROUP_CONCAT(CONCAT(mp.descripcion, ': $', pp.monto)) as desglose
FROM Pedido p
LEFT JOIN Pago_pedido pp ON pp.id_pedido = p.id_pedido
LEFT JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
WHERE p.id_pedido = ?
GROUP BY p.id_pedido;
```

### Pagos por turno
```sql
SELECT 
    pp.id_pago,
    pp.id_pedido,
    mp.descripcion as metodo,
    pp.monto,
    pp.fecha_hora
FROM Pago_pedido pp
JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
JOIN Pedido p ON p.id_pedido = pp.id_pedido
WHERE p.id_turno = ?
ORDER BY pp.fecha_hora DESC;
```

---

## 🔐 Verificación de Integridad

```sql
-- Pedidos cobrados sin pagos
SELECT COUNT(*) as problemas
FROM Pedido p
WHERE p.cobrado = TRUE
AND NOT EXISTS (SELECT 1 FROM Pago_pedido pp WHERE pp.id_pedido = p.id_pedido);
-- Resultado: 0 (si está bien)

-- Sumas de pagos que no coinciden
SELECT p.id_pedido, p.monto_total, SUM(pp.monto) as suma
FROM Pedido p
LEFT JOIN Pago_pedido pp ON pp.id_pedido = p.id_pedido
GROUP BY p.id_pedido
HAVING p.monto_total != COALESCE(SUM(pp.monto), 0);
-- Resultado: (vacío si está bien)
```

---

## 📞 Contacto / Preguntas

- **¿Por qué eliminar id_mp de Pedido?** → Permite múltiples métodos (pago dividido)
- **¿Cómo se registra auditoría?** → En tabla Auditoria (separado)
- **¿Es compatible con reportes?** → Sí, usa Pago_pedido en lugar de Pedido.id_mp
- **¿Necesito cambiar código?** → Solo si usabas directamente Pedido.id_mp (ya actualizado)

---

**Versión:** 1.0  
**Última actualización:** 2026-10-30  
**Estado:** ✅ PRODUCCIÓN
