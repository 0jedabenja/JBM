# Implementación de Pago Dividido - Resumen de Cambios

## 📋 Descripción
Se implementó soporte para pagar un pedido usando múltiples métodos de pago simultáneamente. Por ejemplo: $ 500 en efectivo + $ 300 con tarjeta en la misma transacción.

## 🗄️ Cambios en Base de Datos

### Nueva Tabla: `Pago_pedido`
```sql
CREATE TABLE Pago_pedido (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL (FK → Pedido),
    id_mp INT NOT NULL (FK → Metodo_pago),
    monto DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_turno INT NOT NULL (FK → Turno_caja),
    id_empleado INT NOT NULL (FK → Empleado)
)
```

**Relación E-R:**
- `Pago_pedido` es la tabla de relación N:N entre `Pedido` y `Metodo_pago`
- Un pedido puede tener múltiples registros de pago (uno por método usado)
- Un método de pago puede utilizarse en múltiples pedidos

**Migración:**
- Ejecutar `BD/migracion_pago_pedido.sql` para poblar `Pago_pedido` con pagos existentes
- Los pedidos ya cobrados se migran como un único pago con el método original

### Cambios en Tabla `Pedido`
- El campo `id_mp` ahora almacena el **método de pago primario** (por referencia histórica)
- Ya no es la única fuente de verdad del pago; revisar `Pago_pedido` para la lista completa

## 🔧 Cambios en Backend (`api_caja.php`)

### Acción `cobrar` - Nuevo formato
**Antes:**
```json
{
  "accion": "cobrar",
  "id_pedido": 123,
  "id_mp": 1,
  "porcentaje_descuento": 10
}
```

**Ahora:**
```json
{
  "accion": "cobrar",
  "id_pedido": 123,
  "pagos": [
    { "id_mp": 1, "monto": 500.00 },
    { "id_mp": 2, "monto": 300.00 }
  ],
  "porcentaje_descuento": 10,
  "monto_descuento": 80.00
}
```

### Validaciones
- ✅ Suma de pagos = total del pedido (con tolerancia de $0.01)
- ✅ Cada método de pago existe en la BD
- ✅ Cada monto es > 0
- ✅ Transacción atómica: todos los pagos se insertan o ninguno
- ✅ Respuesta incluye resumen: `{"ok": true, "total": 800, "pagos": [...]}`

### Cambios en Auditoría
- Registra todos los métodos de pago utilizados (separados por coma)
- Mantiene compatibilidad con consultas anteriores

## 🎨 Cambios en Frontend (`caja.php`)

### Diálogo de Cobro Rediseñado
**Componentes nuevos:**
1. **Selector de Métodos Dinámico**
   - Lista de filas, cada una con: Método | Monto | Botón eliminar
   - Botón "+ Agregar otro método" para agregar pagos adicionales

2. **Cálculo de Efectivo por Pago**
   - Si el método es "Efectivo": aparece campo "Efectivo recibido" y cálculo de vuelto
   - El vuelto se calcula de forma independiente por cada pago en efectivo

3. **Resumen en Tiempo Real**
   - Muestra: Total a cobrar | Total ingresado | Diferencia
   - Colores: ⚠️ Rojo (falta) | ✅ Verde (sobra) | ⚪ Neutral

4. **Validación de Envío**
   - Botón "Confirmar cobro" deshabilitado hasta que suma de pagos = total
   - Mensaje de error si hay inconsistencias

### Funciones JavaScript Nuevas
```javascript
construirFilaPago(indice, idMp, monto)    // HTML de una fila de pago
actualizarPagosUI()                        // Renderiza todas las filas
obtenerPagosActuales()                     // Extrae array de pagos del DOM
actualizarResumenCobro()                   // Calcula totales y vueltos
```

### Inicialización de Diálogo
- Cuando el usuario hace click en "Cobrar", se pre-completa con:
  - 1 fila del método de pago seleccionado
  - Monto = total del pedido
  - Usuario puede agregar más métodos o dividir el pago

## 📊 Flujo Completo de Pago Dividido

### Escenario: Pedido $800 pagado con efectivo $500 + tarjeta $300

1. **Usuario abre diálogo de cobro**
   - Se muestra: Método 1 (Efectivo), Monto: $800

2. **Usuario modifica los montos**
   - Cambia Monto 1 a $500
   - Agregar otro método
   - Método 2 (Tarjeta), Monto: $300

3. **Efectivo recibido**
   - Para fila 1: ingresa $500 → Vuelto: $0
   - Fila 2 (tarjeta): no muestra campos de efectivo
   
4. **Resumen en tiempo real**
   ```
   Total a cobrar: $800
   Total ingresado: $800
   ✅ Listo para confirmar
   ```

5. **Usuario confirma**
   - Frontend envía:
     ```json
     {
       "pagos": [
         { "id_mp": 1, "monto": 500 },
         { "id_mp": 2, "monto": 300 }
       ]
     }
     ```

6. **Backend procesa**
   - Valida suma = $800
   - Inserta 2 filas en `Pago_pedido`
   - Marca pedido como cobrado
   - Registra auditoría: "Efectivo, Tarjeta"

7. **Frontend finaliza**
   - Cierra diálogo
   - Muestra: "Pedido cobrado"
   - Abre comprobante en nueva ventana

## 🔄 Consultas para Reportes

### Obtener todos los pagos de un pedido
```sql
SELECT pp.*, mp.descripcion 
FROM Pago_pedido pp
JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
WHERE pp.id_pedido = ?
ORDER BY pp.fecha_hora;
```

### Resumen de cobros por método (turno actual)
```sql
SELECT mp.descripcion, SUM(pp.monto) as total
FROM Pago_pedido pp
JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
WHERE pp.id_turno = ? AND pp.fecha_hora >= NOW()
GROUP BY pp.id_mp;
```

### Efectivo esperado en caja (recalculado)
```sql
SELECT SUM(pp.monto) as efectivo_esperado
FROM Pago_pedido pp
JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
WHERE pp.id_turno = ? 
  AND LOWER(mp.descripcion) = 'efectivo';
```

## ⚠️ Notas Importantes

### Compatibilidad Hacia Atrás
- Campo `Pedido.id_mp` se mantiene (almacena primer método)
- Consultas antiguas que leen `id_mp` seguirán funcionando
- Reportes deben actualizar lógica para sumar desde `Pago_pedido`

### Casos Especiales
- **Pedido sin pagar:** `Pago_pedido` vacío, `Pedido.cobrado = FALSE`
- **Pago parcial:** No permitido en esta implementación (suma debe ser exacta)
- **Cambio de pago:** No hay "deshacer", solo cobrar o no cobrar

### Performance
- Índice automático en `id_pedido` (FK)
- Considerar índice en `id_turno` si se consulta frecuentemente por turno

## 📝 Pasos Para Implementar

1. ✅ Agregar tabla `Pago_pedido` a `creacionbd.sql`
2. ✅ Ejecutar migración `migracion_pago_pedido.sql` en BD existente
3. ✅ Actualizar `api_caja.php` acción "cobrar"
4. ✅ Rediseñar diálogo en `caja.php`
5. ⏳ Actualizar `boleta_pedido.php` para mostrar desglose de pagos
6. ⏳ Actualizar módulo de reportes/auditoría
7. ⏳ Actualizar otras APIs que dependan de `Pedido.id_mp`

## 🎯 Beneficios
- ✨ Flexibilidad de pago: efectivo + tarjeta simultáneamente
- 📊 Trazabilidad: cada pago registrado con fecha, empleado y turno
- 🔒 Auditoría: historial completo de quién, cuándo y cómo se cobró
- 💰 Reportes: desglose por método de pago más preciso
