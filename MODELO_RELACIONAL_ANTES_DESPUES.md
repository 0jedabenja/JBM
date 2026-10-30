# Diagrama del Modelo Relacional - Antes y Después

## ❌ ANTES (Con Redundancia)

```
┌─────────────────────────────────┐
│         Pedido                  │
├─────────────────────────────────┤
│ PK: id_pedido                   │
│ FK: id_empleado → Empleado      │
│ FK: id_turno → Turno_caja       │
│ FK: id_mesa → Mesa              │
│ FK: id_estado → Estado_Pedido   │
│ FK: id_cliente → Cliente        │
│ FK: id_descuento → Descuento    │
│ FK: id_mp → Metodo_pago ⚠️      │  ← REDUNDANTE: Método de pago único
│                                 │
│ monto_total                     │
│ cobrado                         │
│ porcentaje_descuento            │
│ monto_descuento                 │
└─────────────────────────────────┘
          ▼
┌─────────────────────────────────┐
│      Pago_pedido (Anterior)     │  ← Tabla intermedia (no usa id_mp)
├─────────────────────────────────┤
│ PK: id_pago                     │
│ FK: id_pedido → Pedido          │
│ FK: id_mp → Metodo_pago         │
│ FK: id_turno → Turno_caja ⚠️    │  ← REDUNDANTE: Ya está en Pedido
│ FK: id_empleado → Empleado ⚠️   │  ← REDUNDANTE: Ya está en Pedido
│                                 │
│ monto                           │
│ fecha_hora                      │
└─────────────────────────────────┘

PROBLEMAS:
  ⚠️ Pedido.id_mp limita a UN SOLO método de pago
  ⚠️ Pago_pedido duplica id_turno e id_empleado (ya están en Pedido)
  ⚠️ Inconsistencia: ¿Qué pasa si Pago_pedido.id_mp ≠ Pedido.id_mp?
  ⚠️ Mayor tamaño de BD
```

---

## ✅ AHORA (Modelo Limpio)

```
┌─────────────────────────────────┐
│         Pedido                  │
├─────────────────────────────────┤
│ PK: id_pedido                   │
│ FK: id_empleado → Empleado      │
│ FK: id_turno → Turno_caja       │
│ FK: id_mesa → Mesa              │
│ FK: id_estado → Estado_Pedido   │
│ FK: id_cliente → Cliente        │
│ FK: id_descuento → Descuento    │
│                                 │
│ monto_total                     │
│ cobrado                         │
│ porcentaje_descuento            │
│ monto_descuento                 │
└─────────────────────────────────┘
          ▼ (1:N)
┌─────────────────────────────────┐
│      Pago_pedido (Limpia)       │  ← Relación N:N PURA
├─────────────────────────────────┤
│ PK: id_pago                     │
│ FK: id_pedido → Pedido          │
│ FK: id_mp → Metodo_pago         │
│                                 │
│ monto                           │
│ fecha_hora                      │
└─────────────────────────────────┘
          ▼ (N:1)
┌─────────────────────────────────┐
│      Metodo_pago                │
├─────────────────────────────────┤
│ PK: id_mp                       │
│                                 │
│ descripcion (Efectivo, Tarjeta) │
└─────────────────────────────────┘

BENEFICIOS:
  ✅ Pedido sin id_mp: Relación delegada a Pago_pedido
  ✅ Pago_pedido PURA: Solo tiene PKs y FKs que le corresponden
  ✅ Múltiples métodos: Un pedido puede pagarse con N métodos
  ✅ Una fuente de verdad: Pago_pedido es la única autoridad
  ✅ Menor tamaño: Sin datos redundantes
  ✅ Auditoría limpia: Tabla Auditoria registra quién cobró
```

---

## 🔄 Flujo de Datos - Cobro

### Antes
```
Usuario crea pedido
        ↓
    Pedido.id_mp = 1 (Efectivo fijo)
        ↓
Usuario cobra
        ↓
    UPDATE Pedido SET cobrado = TRUE, id_mp = 1
    INSERT Pago_pedido (id_pedido, id_mp, monto, ...)
        ↓
¿Qué pasa si quería cobrar con 2 métodos?
    ❌ Imposible: Pedido.id_mp solo almacena UN método
```

### Ahora
```
Usuario crea pedido
        ↓
    Pedido.cobrado = FALSE (sin id_mp)
        ↓
Usuario selecciona cobrar
        ↓
    Selecciona: Efectivo $500 + Tarjeta $300
        ↓
    INSERT Pago_pedido (id_pedido=1, id_mp=1, monto=500)
    INSERT Pago_pedido (id_pedido=1, id_mp=2, monto=300)
    UPDATE Pedido SET cobrado = TRUE
        ↓
✅ Sistema registra N pagos sin conflictos
✅ Query se puede hacer: SELECT SUM(monto) FROM Pago_pedido WHERE id_pedido = 1
✅ Comprobante muestra desglose completo
```

---

## 📊 Comparativa de Queries

### Obtener método de pago de un pedido

#### Antes
```sql
SELECT mp.descripcion FROM Pedido p
LEFT JOIN Metodo_pago mp ON mp.id_mp = p.id_mp
WHERE p.id_pedido = 1;
-- Resultado: 1 fila (método único)
```

#### Ahora
```sql
-- Opción 1: Todos los métodos usados
SELECT mp.descripcion, pp.monto FROM Pago_pedido pp
JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
WHERE pp.id_pedido = 1;
-- Resultado: N filas (múltiples métodos)

-- Opción 2: Primer método (compatible con antes)
SELECT mp.descripcion FROM Pago_pedido pp
JOIN Metodo_pago mp ON mp.id_mp = pp.id_mp
WHERE pp.id_pedido = 1
ORDER BY pp.fecha_hora
LIMIT 1;
-- Resultado: 1 fila (primer pago cronológico)
```

---

## 🗂️ Impacto en Archivos

```
BD/
├── creacionbd.sql                    ← Actualizado: Sin id_mp en Pedido
├── migracion_eliminar_id_mp_pedido.sql  ← Nuevo: Para BD existentes

apis/
├── api_caja.php                      ← Actualizado: Queries con Pago_pedido
└── boleta_pedido.php                 ← Actualizado: Trae método desde Pago_pedido

caja.php                              ← Actualizado: Sin data-metodo-pago

DOCUMENTACION/
├── CAMBIOS_ESTRUCTURA_PEDIDO.md      ← Nuevo: Detalles completos
├── IMPLEMENTACION_COMPLETADA.md      ← Nuevo: Resumen técnico
├── CHANGELOG_OCTUBRE_2026.md         ← Nuevo: Historial de cambios
└── MODELO_RELACIONAL_ANTES_DESPUES.md ← Este archivo
```

---

## 🔗 Relaciones: Diagrama MER Simplificado

```
     ┌──────────────┐
     │   Empleado   │
     └──────────────┘
            ▲
            │ (1:N)
            │ id_empleado FK
     ┌──────────────┐
     │   Pedido     │
     └──────────────┘
            ▲  │
     id_turno│  └─────────┐
      FK (1:N)            │ (1:N)
            │             │ id_pedido FK
     ┌──────────────┐      │
     │ Turno_caja   │      │
     └──────────────┘      │
                           │
                    ┌──────▼──────────┐
                    │  Pago_pedido    │
                    │ (Relación N:N)  │
                    └──────┬──────────┘
                           │
                    id_mp FK│ (N:1)
                           │
                    ┌──────▼──────────┐
                    │ Metodo_pago     │
                    │ (Efectivo, etc) │
                    └─────────────────┘
```

---

## 📈 Escalabilidad

| Escenario | Antes | Ahora |
|-----------|-------|-------|
| **1 pedido, 1 pago** | ✅ Simple | ✅ Simple |
| **1 pedido, 2 pagos** | ❌ Imposible | ✅ N filas en Pago_pedido |
| **1 pedido, múltiples cambios** | ⚠️ Complejo (UPDATE Pedido.id_mp) | ✅ INSERT en Pago_pedido |
| **Reportes por método** | ✅ GROUP BY Pedido.id_mp | ✅ GROUP BY Pago_pedido.id_mp |
| **Auditoría de cambios** | ⚠️ Costo alto (auditar Pedido) | ✅ Limpio (tabla Auditoria sola) |
| **Backups** | ✅ Estándar | ✅ Más consistentes |

---

## 🎯 Conclusión

El nuevo modelo:
1. **Elimina redundancia** de `id_turno` e `id_empleado` en Pago_pedido
2. **Permite flexibilidad** con múltiples métodos de pago por pedido
3. **Garantiza consistencia** siendo Pago_pedido la única autoridad
4. **Simplifica auditoría** sin datos duplicados
5. **Es escalable** para futuras mejoras (ej: cambio de pago, devoluciones)

**Estado:** ✅ Implementado y validado
