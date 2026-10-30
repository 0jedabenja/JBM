# 🎯 Implementación: Pago Dividido en Múltiples Métodos

## ✨ Resumen de Cambios

```
┌─────────────────────────────────────────────────────────────┐
│  ANTES: Pago único por pedido                               │
│  ─────────────────────────────────────────────────────────  │
│  Pedido $800                                                 │
│  ↓                                                           │
│  Método de pago: Efectivo                                   │
│  ↓                                                           │
│  Cobrado: SI ✓                                              │
│                                                              │
│  Tabla: Pedido.id_mp = 1                                    │
└─────────────────────────────────────────────────────────────┘

                            →→→ CAMBIO →→→

┌─────────────────────────────────────────────────────────────┐
│  AHORA: Pago dividido en múltiples métodos                 │
│  ──────────────────────────────────────────────────────     │
│  Pedido $800                                                │
│  ↓                                                           │
│  ┌──────────────────────────────────────┐                   │
│  │ Pago 1: Efectivo       $500          │                   │
│  │ Pago 2: Tarjeta       $300          │                   │
│  └──────────────────────────────────────┘                   │
│  ↓                                                           │
│  Cobrado: SI ✓                                              │
│                                                              │
│  Tablas:                                                    │
│  - Pedido.id_mp = 1 (método primario)                       │
│  - Pago_pedido[1]: id_mp=1, monto=500                       │
│  - Pago_pedido[2]: id_mp=2, monto=300                       │
└─────────────────────────────────────────────────────────────┘
```

---

## 📦 Archivos Modificados

### 1. **BD/creacionbd.sql** (+17 líneas)
   - Nueva tabla `Pago_pedido` con relación N:N

### 2. **BD/migracion_pago_pedido.sql** (creado)
   - Script para poblar `Pago_pedido` con pagos históricos
   - Migración segura (no duplica)

### 3. **apis/api_caja.php** (~110 líneas modificadas)
   - Acción "cobrar" ahora acepta `pagos[]` array
   - Valida suma = total (tolerancia $0.01)
   - Inserta N filas en `Pago_pedido` en transacción atómica
   - Recalcula "efectivo esperado" desde `Pago_pedido`

### 4. **caja.php** (~180 líneas modificadas)
   - Rediseño completo del diálogo de cobro
   - UI dinámica para múltiples métodos
   - Funciones: `construirFilaPago()`, `actualizarPagosUI()`, `obtenerPagosActuales()`
   - Validación en tiempo real

### 5. **PAGO_DIVIDIDO.md** (creado)
   - Documentación técnica completa
   - Queries SQL de consulta
   - Notas de implementación

### 6. **TESTING_PAGO_DIVIDIDO.md** (creado)
   - Guía de pruebas manuales
   - Checklist de validación
   - Casos de error

---

## 🔧 Cambios Técnicos Principales

### Base de Datos
```sql
-- Nueva tabla como relación N:N
CREATE TABLE Pago_pedido (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,        -- FK → Pedido
    id_mp INT NOT NULL,             -- FK → Metodo_pago
    monto DECIMAL(10,2) NOT NULL,   -- Monto del pago
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_turno INT NOT NULL,          -- FK → Turno_caja
    id_empleado INT NOT NULL        -- FK → Empleado
);
```

### API: Nuevo Formato de Cobro
```javascript
// Frontend → Backend
{
    "accion": "cobrar",
    "id_pedido": 123,
    "pagos": [                      // ← Array de pagos
        { "id_mp": 1, "monto": 500 },  // Efectivo
        { "id_mp": 2, "monto": 300 }   // Tarjeta
    ],
    "porcentaje_descuento": 0,
    "monto_descuento": 0
}
```

### Frontend: Interfaz Dinámica
```
┌─────────────────────────────────────────────┐
│ Cobrar pedido                               │
├─────────────────────────────────────────────┤
│ Subtotal: $800                              │
│ Descuento: Sin                              │
│                                             │
│ MÉTODOS DE PAGO:                            │
│ ┌──────────────┬──────────┬────┐            │
│ │ Efectivo     │ $500     │ −  │            │
│ │ Recibido: $500                           │
│ │ Vuelto: $0                               │
│ └──────────────────────────────────────────┘
│ ┌──────────────┬──────────┬────┐            │
│ │ Tarjeta      │ $300     │ −  │            │
│ └──────────────────────────────────────────┘
│                                             │
│ + Agregar otro método                      │
│                                             │
│ RESUMEN:                                    │
│ Total a cobrar: $800                        │
│ Total ingresado: $800                       │
│ ✅ Listo para confirmar                     │
│                                             │
│ [Cancelar]  [Confirmar cobro]              │
└─────────────────────────────────────────────┘
```

---

## 💡 Características Principales

### ✅ Pago Dividido
- Múltiples métodos en una sola transacción
- Validación en tiempo real
- Suma debe ser exacta (tolerancia $0.01)

### ✅ Efectivo Inteligente
- Cada método en efectivo muestra campo "Recibido"
- Calcula vuelto independiente por fila
- Métodos no-efectivo no muestran ese campo

### ✅ Transacciones Atómicas
- Todos los pagos se guardan o ninguno
- Pedido se marca cobrado al confirmar
- Auditoría registra todos los métodos

### ✅ Validaciones Robustas
- Montos deben ser > 0
- Métodos deben existir en BD
- Suma = total (no parciales)
- Cliente debe estar en turno abierto

### ✅ Reportes Mejorados
- Consulta `Pago_pedido` para desglose completo
- Efectivo esperado = suma de pagos en efectivo
- Auditoría lista todos los métodos usados

---

## 📊 Relación E-R Actualizada

```
        ┌──────────────┐
        │   Pedido     │
        ├──────────────┤
        │ id_pedido    │ (PK)
        │ monto_total  │
        │ cobrado      │
        │ id_mp        │ ← método primario (histórico)
        └──────┬───────┘
               │ 1:N
               │
        ┌──────▼───────────────┐
        │  Pago_pedido (N:N)   │
        ├──────────────────────┤
        │ id_pago (PK)         │
        │ id_pedido (FK)       │ ← múltiples filas por pedido
        │ id_mp (FK)           │ ← múltiples métodos
        │ monto                │
        │ fecha_hora           │
        │ id_turno (FK)        │
        │ id_empleado (FK)     │
        └──────┬───────────────┘
               │ N:1
               │
        ┌──────▼──────────────┐
        │  Metodo_pago        │
        ├─────────────────────┤
        │ id_mp (PK)          │
        │ descripcion         │
        └─────────────────────┘
```

---

## 🚀 Flujo Completo de Pago Dividido

```
1. Usuario en CAJA ve pedido "Listo"
   ↓
2. Click "Cobrar"
   ↓
3. Diálogo abre con:
   - 1 fila: Método preseleccionado, Monto = total
   - Botón "+ Agregar otro método"
   ↓
4. Usuario modifica:
   - Fila 1: Efectivo $500
   - Agregar Fila 2: Tarjeta $300
   ↓
5. Frontend valida:
   - Suma = $800 (coincide con total)
   - Efectivo recibido ≥ $500
   - Botón habilitado ✓
   ↓
6. Usuario confirma
   ↓
7. Frontend envía:
   {
     "accion": "cobrar",
     "id_pedido": 123,
     "pagos": [
       {"id_mp": 1, "monto": 500},
       {"id_mp": 2, "monto": 300}
     ]
   }
   ↓
8. Backend (api_caja.php):
   - Inicia transacción
   - Valida suma = $800
   - Inserta Pago_pedido #1: (123, 1, 500)
   - Inserta Pago_pedido #2: (123, 2, 300)
   - Marca Pedido.cobrado = TRUE
   - Registra auditoría: "Efectivo, Tarjeta"
   - COMMIT
   ↓
9. Frontend:
   - Cierra diálogo
   - Muestra: "Pedido cobrado con 2 método(s)"
   - Abre comprobante
   ↓
10. En CAJA:
    - Pedido ahora dice "Cobrado"
    - "Efectivo esperado" incluye los $500
    - Al cerrar turno, suma el efectivo correctamente
```

---

## 🔍 Consultas Útiles Post-Implementación

### Ver todos los pagos de un pedido
```sql
SELECT pp.*, mp.descripcion 
FROM Pago_pedido pp
JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
WHERE pp.id_pedido = 123;

-- Resultado para pedido dividido:
-- id_pago | id_pedido | id_mp | monto | descripcion
-- 45      | 123       | 1     | 500   | Efectivo
-- 46      | 123       | 2     | 300   | Tarjeta
```

### Efectivo esperado por turno
```sql
SELECT SUM(pp.monto) as efectivo_esperado
FROM Pago_pedido pp
JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
WHERE pp.id_turno = 5 AND LOWER(mp.descripcion) = 'efectivo';
```

### Resumen de métodos usados
```sql
SELECT mp.descripcion, COUNT(*) as cantidad, SUM(pp.monto) as total
FROM Pago_pedido pp
JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
WHERE pp.id_turno = 5
GROUP BY pp.id_mp;
```

---

## ⚠️ Notas Importantes

1. **Compatibilidad hacia atrás:** El campo `Pedido.id_mp` se mantiene pero es solo referencia. La fuente de verdad es `Pago_pedido`.

2. **Migración:** Ejecutar `BD/migracion_pago_pedido.sql` para poblar tabla con pagos históricos.

3. **Performance:** Los índices en `Pago_pedido` (FK) son automáticos. Considerar agregar índice en `id_turno` si reportes son lentos.

4. **Cambios Futuros:** 
   - Actualizar `boleta_pedido.php` para mostrar desglose de pagos
   - Revisar otros módulos que lean `Pedido.id_mp`
   - Actualizar reportes de auditoría

---

## ✅ Estado de Implementación

| Componente | Estado |
|-----------|--------|
| BD: Tabla Pago_pedido | ✅ Implementado |
| BD: Migración datos | ✅ Script creado |
| API: Acción "cobrar" | ✅ Implementado |
| API: Cálculo efectivo | ✅ Actualizado |
| Frontend: Diálogo UI | ✅ Implementado |
| Frontend: Validación | ✅ Implementado |
| Testing: Guía manual | ✅ Completa |
| Documentación | ✅ Completa |
| Comprobante: Desglose | ⏳ Pendiente |
| Reportes: Auditoría | ⏳ Pendiente |

---

## 🎓 Cómo Probar

Consulta el archivo **TESTING_PAGO_DIVIDIDO.md** para:
- ✅ Paso a paso de pruebas
- ✅ Validaciones en BD
- ✅ Casos de error esperados
- ✅ Checklist final

**Resumen rápido:**
1. Abrir caja
2. Crear pedido $800
3. Cobrar con Efectivo $500 + Tarjeta $300
4. Verificar que ambos pagos se guardaron en `Pago_pedido`
5. Verificar que "Efectivo esperado" incluye $500

---

## 📝 Commit History

```
705518b docs: agregar guía de testing para pago dividido
d790b7b fix: actualizar cálculo de efectivo esperado desde Pago_pedido
19276b1 feat: implementar pago dividido en múltiples métodos
```

---

**¡Implementación completa! 🎉**

El sistema ahora soporta pago dividido. Todos los cambios son siguiendo principios de:
- ✨ Transacciones atómicas
- 🔒 Auditoría completa
- 💾 Integridad de datos
- 🎯 Experiencia UX clara
