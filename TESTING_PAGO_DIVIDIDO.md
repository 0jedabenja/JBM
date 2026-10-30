# Testing Manual - Pago Dividido

## 📋 Checklist de Pruebas

### 1. ✅ Creación de Tabla en BD
```bash
# Ejecutar migración
mysql -u root -p jbm < BD/migracion_pago_pedido.sql

# Verificar tabla existe
mysql -u root -p jbm -e "SHOW TABLES LIKE 'Pago_pedido';"
mysql -u root -p jbm -e "DESCRIBE Pago_pedido;"
```

**Esperado:**
- Tabla `Pago_pedido` visible
- 7 columnas: id_pago, id_pedido, id_mp, monto, fecha_hora, id_turno, id_empleado
- Claves foráneas correctas

---

### 2. ✅ Funcionalidad Frontend - Diálogo de Cobro

#### Paso 2.1: Abrir Caja
1. Ir a **Caja** → Click en **Abrir caja**
2. Ingresar monto inicial (ej: $1000)
3. Verificar que la caja se abre

**Esperado:**
- Estado cambia a "Caja abierta" (verde)
- Se ve tabla con movimientos

#### Paso 2.2: Crear Pedido
1. Ir a **Pedidos** → Crear un pedido con ej:
   - Mesa 1
   - 2x Milanesa ($300 c/u)
   - Total: $600
2. Cambiar estado a "Listo"
3. Volver a **Caja**

**Esperado:**
- Pedido aparece en tabla de movimientos
- Estado: "Listo para cobrar"
- Botón "Cobrar" disponible

#### Paso 2.3: Pago Simple (Un Método)
1. Click en **Cobrar**
2. Se abre diálogo
3. Verificar contenido:
   - Subtotal: $600
   - Método de pago seleccionado (Efectivo)
   - Monto: $600
   - Una fila de pago visible
   - Botón "+ Agregar otro método"
4. Si es Efectivo:
   - Campo "Efectivo recibido" aparece
   - Ingresa $600 → Vuelto: $0
5. Click "Confirmar cobro"

**Esperado:**
- Pedido marcado como "Cobrado"
- Mensaje: "Pedido cobrado"
- Se abre comprobante en nueva ventana
- Botón "Imprimir comprobante" aparece

---

### 3. ✅ Pago Dividido (Múltiples Métodos)

#### Paso 3.1: Crear Pedido
1. Crear pedido de $800
   - 2x Milanesa ($300 c/u) = $600
   - 1x Cerveza ($200)
   - Total: $800
2. Cambiar estado a "Listo"

#### Paso 3.2: Pago Dividido
1. Click en **Cobrar**
2. Se abre diálogo
   - Subtotal: $800
   - Fila 1: Efectivo | $800
   - Campo "Efectivo recibido": $800
3. **Dividir el pago:**
   - Cambiar Monto Fila 1 a: $500
   - Click "+ Agregar otro método"
   - Nueva fila aparece (Fila 2)
   - Método: Tarjeta
   - Monto: $300

**Esperado:**
- Resumen actualiza en tiempo real:
  ```
  Total a cobrar: $800
  Total ingresado: $800
  ✅ (sin mensaje de error)
  ```
- Vuelto (Fila 1 Efectivo): $0
- Botón "Confirmar cobro" **HABILITADO**

#### Paso 3.3: Validación de Montos
1. Cambiar Monto Fila 1 (Efectivo) a $400
2. Resumen debe mostrar:
   ```
   Total a cobrar: $800
   Total ingresado: $700
   ⚠️ Falta: $100
   ```
3. Botón "Confirmar cobro" **DESHABILITADO** (no puedes hacer click)

**Esperado:**
- No permite cobrar si suma no coincide
- UI clara sobre diferencia

#### Paso 3.4: Confirmación de Pago Dividido
1. Corregir montos a: Efectivo $500 + Tarjeta $300
2. Verificar efectivo recibido ≥ $500
3. Click "Confirmar cobro"

**Esperado:**
- Pedido marcado como "Cobrado"
- Mensaje: "Pedido cobrado con 2 método(s) de pago"
- Comprobante se abre

---

### 4. ✅ Validación en BD

#### Paso 4.1: Verificar Pago_pedido
```bash
mysql -u root -p jbm -e "
SELECT 
  pp.id_pago, 
  pp.id_pedido,
  mp.descripcion as metodo,
  pp.monto,
  pp.fecha_hora
FROM Pago_pedido pp
JOIN Metodo_pago mp ON pp.id_mp = mp.id_mp
ORDER BY pp.id_pago DESC
LIMIT 5;
"
```

**Esperado:**
- Último pedido cobrado tiene 2 filas
- Monto 1: $500 (Efectivo)
- Monto 2: $300 (Tarjeta)

#### Paso 4.2: Verificar Pedido
```bash
mysql -u root -p jbm -e "
SELECT 
  id_pedido,
  cobrado,
  id_mp,
  monto_total,
  DATE_FORMAT(fecha_hora_creacion, '%H:%i:%s') as hora
FROM Pedido
WHERE cobrado = TRUE
ORDER BY id_pedido DESC
LIMIT 3;
"
```

**Esperado:**
- Último pedido: cobrado = 1
- id_mp = 1 (Efectivo - método primario)
- monto_total = 800

---

### 5. ✅ Efectivo Esperado en Caja

#### Paso 5.1: Resumen de Caja Abierta
1. En **Caja**, revisar métrica "Efectivo esperado en caja"
2. Debe incluir:
   - Monto inicial
   - Todo el efectivo de pago dividido

**Esperado:**
- Si inicio con $1000 y cobro $500 en efectivo (pago dividido)
- Efectivo esperado = $1000 + $500 = $1500

#### Paso 5.2: Cierre de Caja
1. Click **Cerrar caja**
2. Contar efectivo real
3. Ingresar monto final
4. Si hay diferencia > $200, solicita contraseña

**Esperado:**
- Diferencia se calcula correctamente
- Efectivo esperado = $1500
- Si cuento $1500 exacto, diferencia = $0

---

### 6. ✅ Casos Especiales

#### Caso 6.1: Tres o Más Métodos
1. Crear pedido de $1000
2. Cobrar con: Efectivo $300 + Tarjeta $400 + Cheque $300
3. Agregar método 2 y método 3

**Esperado:**
- Resumen suma correctamente
- Botón habilitado si suma = $1000
- Todos los métodos se guardan

#### Caso 6.2: Efectivo Insuficiente
1. Pago dividido: Efectivo $500 + Tarjeta $300 (Total $800)
2. En campo "Efectivo recibido": ingresar $400

**Esperado:**
- Vuelto Fila 1: $-100 (o no permitir ingreso menor)
- Botón "Confirmar cobro" se deshabilita
- No puede enviar

#### Caso 6.3: Cambiar de Método
1. Crear fila con Efectivo $800
2. Click en selector, cambiar a Tarjeta
3. El campo "Efectivo recibido" desaparece

**Esperado:**
- UI se adapta dinámicamente
- Resumen se actualiza

---

### 7. ✅ Auditoría

#### Verificar Registro
```bash
mysql -u root -p jbm -e "
SELECT 
  id_auditoria,
  accion,
  descripcion,
  DATE_FORMAT(fecha_hora, '%Y-%m-%d %H:%i:%s') as fecha
FROM Auditoria
WHERE accion = 'cobrar_pedido'
ORDER BY id_auditoria DESC
LIMIT 3;
"
```

**Esperado:**
- Descripción menciona: "Efectivo, Tarjeta"
- Monto_descuento = 0 (si no hay descuento)
- Porcentaje_descuento = 0

---

### 8. ✅ Descuentos con Pago Dividido

#### Paso 8.1: Pago Dividido + Descuento
1. Crear pedido $1000 (cliente registrado)
2. Abrir cobro
3. Tienes opción "Descuento" (si cliente no es genérico)
4. Seleccionar "Personalizado 10%"
5. Se calcula: Descuento = $100 → Total = $900
6. Dividir: Efectivo $500 + Tarjeta $400

**Esperado:**
- Subtotal: $1000
- Descuento: -$100
- **Total a cobrar: $900** ← importante
- Total ingresado: $900 (suma de pagos)
- Puede confirmar

---

### 9. 🔴 Errores Esperados

| Escenario | Comportamiento Esperado |
|-----------|------------------------|
| Suma pagos < Total | "Falta $X" + Botón deshabilitado |
| Suma pagos > Total | "Sobra $X" + Botón deshabilitado |
| Efectivo insuficiente | Vuelto negativo visible |
| Sin métodos de pago | Error: "Debes agregar..." |
| Eliminar todas las filas | Resumen vacío + Botón deshabilitado |
| Cambiar monto a $0 | Fila se invalida |

---

## 📊 Verificación de Performance

### Query Performance
```bash
# Verificar que índices existen
mysql -u root -p jbm -e "SHOW INDEX FROM Pago_pedido;"

# Esperado:
# - idx_id_pedido (FK)
# - idx_id_turno (para reportes)
```

### Transacción Atómica
- En `api_caja.php`, verificar que:
  1. Se inicia transacción
  2. Se insertan N filas en Pago_pedido
  3. Se actualiza Pedido.cobrado = 1
  4. Se registra auditoría
  5. COMMIT o ROLLBACK completo

---

## ✅ Checklist Final

- [ ] Tabla Pago_pedido creada
- [ ] Pago simple (1 método) funciona
- [ ] Pago dividido (2+ métodos) funciona
- [ ] Validación de montos correcta
- [ ] Efectivo esperado se calcula bien
- [ ] Auditoría registra todos los métodos
- [ ] Descuentos + pago dividido funcionan
- [ ] Comprobante se abre
- [ ] Cierre de caja incluye efectivo del pago dividido
- [ ] Sin errores de JavaScript en consola

---

## 🚀 Próximos Pasos

Después de pasar estas pruebas:
1. Actualizar `boleta_pedido.php` para mostrar desglose de pagos
2. Actualizar reportes/módulo auditoría
3. Revisar otras APIs que lean `Pedido.id_mp`
4. Documentar en manual de usuario
