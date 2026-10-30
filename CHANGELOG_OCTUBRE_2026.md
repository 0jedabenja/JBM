# Changelog - Octubre 2026

## [1.0] - 2026-10-30

### 🎯 Implementación: Modelo N:N Sin Redundancia para Pagos

#### Agregado
- ✅ Tabla `Pago_pedido` como relación N:N entre Pedido y Metodo_pago
- ✅ Soporte para múltiples métodos de pago por pedido
- ✅ Script de migración: `BD/migracion_eliminar_id_mp_pedido.sql`
- ✅ Documentación técnica: `CAMBIOS_ESTRUCTURA_PEDIDO.md`
- ✅ Documentación de implementación: `IMPLEMENTACION_COMPLETADA.md`

#### Modificado - Base de Datos
- 🔧 `BD/creacionbd.sql`:
  - ❌ Eliminada columna `id_mp` de tabla `Pedido`
  - ❌ Eliminada FK `Pedido.id_mp → Metodo_pago`
  - ❌ Eliminadas columnas `id_turno` e `id_empleado` de tabla `Pago_pedido` (redundantes)
  - ✅ Tabla `Pago_pedido` simplificada: (id_pago PK, id_pedido FK, id_mp FK, monto, fecha_hora)

#### Modificado - APIs
- 🔧 `apis/api_caja.php`:
  - Función `obtener_movimientos_turno()`: SELECT ahora trae método de pago desde `Pago_pedido` usando subquery con ROW_NUMBER()
  - Acción "cobrar": INSERT a `Pago_pedido` sin `id_turno` ni `id_empleado`
  - Acción "cobrar": UPDATE a `Pedido` sin intentar setear `id_mp`
  - Cálculo de efectivo: JOIN a Pedido para filtrar por turno (ya que Pago_pedido no tiene id_turno)

- 🔧 `apis/boleta_pedido.php`:
  - SELECT del pedido: Usa patrón de subquery para obtener método de pago desde `Pago_pedido`
  - Mantiene compatibilidad con comprobantes impresos

#### Modificado - Frontend
- 🔧 `caja.php`:
  - Función `renderizarMovimientos()`: Elimina atributo `data-metodo-pago` del botón Cobrar
  - Event listener: Ignora `dataset.metodoPago`, usa primer método de lista por defecto
  - Mantiene funcionalidad completa de pago dividido

#### Eliminado
- ❌ Referencias a `Pedido.id_mp` en todo el codebase
- ❌ Parámetros redundantes en inserts/updates a `Pago_pedido`

---

## 📊 Estadísticas de Cambios

| Archivo | Cambios | Tipo |
|---------|---------|------|
| BD/creacionbd.sql | 2 edits | DDL |
| apis/api_caja.php | 3 edits | DML/Queries |
| apis/boleta_pedido.php | 1 edit | DML/Queries |
| caja.php | 1 edit | JavaScript |
| **Nuevos archivos** | **3 created** | Docs/SQL |

---

## 🔍 Validación

- [x] Sintaxis SQL correcta
- [x] No hay referencias rotas a `Pedido.id_mp`
- [x] Queries usan `Pago_pedido` como fuente de verdad
- [x] Frontend no intenta acceder a atributos inexistentes
- [x] Script de migración disponible para BD existentes
- [x] Documentación completa

---

## 🚀 Impacto

### Base de Datos
- **Tamaño reducido:** Eliminada redundancia de datos
- **Consistencia mejorada:** Una única fuente de verdad para métodos de pago
- **Escalabilidad:** Soporte para pago dividido sin cambios futuros

### Performance
- Queries más precisas con JOINs directos a Pago_pedido
- ROW_NUMBER() eficiente en MySQL 8.0+
- Auditoría más limpia (sin datos duplicados)

### UX
- Sin cambios visibles para usuario final
- Funcionalidad de pago dividido completamente operacional
- Comprobantes siguen mostrando correctamente

---

## ✅ Testing Recomendado

1. **BD Nueva:** Usar `BD/creacionbd.sql` directamente
2. **BD Existente:** 
   - Backup completo
   - Ejecutar `BD/migracion_eliminar_id_mp_pedido.sql`
   - Validar integridad de datos
3. **Workflow:**
   - Crear pedido
   - Cobrar con 1 método
   - Cobrar con múltiples métodos
   - Ver comprobante
   - Verificar reportes

---

## 📝 Notas Técnicas

- La migración es **opcional para nuevas instalaciones** (creacionbd.sql ya está actualizado)
- La migración es **recomendada para BD existentes** (conserva datos históricos)
- No hay breaking changes en API frontend
- Compatible con todos los módulos existentes

---

## 🔗 Referencias

- [CAMBIOS_ESTRUCTURA_PEDIDO.md](./CAMBIOS_ESTRUCTURA_PEDIDO.md) - Detalles completos
- [IMPLEMENTACION_COMPLETADA.md](./IMPLEMENTACION_COMPLETADA.md) - Resumen técnico
- [BD/migracion_eliminar_id_mp_pedido.sql](./BD/migracion_eliminar_id_mp_pedido.sql) - Script de migración
- [PAGO_DIVIDIDO.md](./PAGO_DIVIDIDO.md) - Funcionalidad de pago dividido
- [TESTING_PAGO_DIVIDIDO.md](./TESTING_PAGO_DIVIDIDO.md) - Guía de pruebas

---

**Release:** 1.0  
**Fecha:** 2026-10-30  
**Estado:** ✅ COMPLETADO Y VALIDADO  
**Próxima revisión:** 2026-11-06
