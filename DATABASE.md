# Modelo de datos — Distribuidora Toro

Base MySQL 5.7+ / MariaDB 10.4+, motor InnoDB, `utf8mb4_unicode_ci`. La estructura completa está en
`database/schema.sql`. Todas las relaciones tienen clave foránea e índice.

## Convenciones

- Nombres de tablas y columnas en español, `snake_case`.
- Importes: `DECIMAL(14,2)`. Costos unitarios: `DECIMAL(14,4)`. Cantidades: `DECIMAL(14,3)`.
- **Stock siempre en unidad base** (`cantidad_base`). Cada línea de documento guarda además la
  presentación usada (`presentacion_nombre`, `factor`, `cantidad`) para poder reimprimirla igual.
- Los documentos comerciales nunca se borran: tienen `estado` y se anulan con `motivo_anulacion`.
- Las fechas se guardan en hora de Salta (UTC−3).

## Diagrama entidad-relación (descripto)

```
                    roles ──< rol_permiso >── permisos
                      │
                      └──< usuarios >── cajas
                              │
     ┌──────────┬────────────┼──────────────┬───────────────┐
 preventistas  choferes   auditoria   login_intentos   (usuario_id en todos los documentos)
     │            │
     │            └──< hojas_ruta >── vehiculos ── depositos(tipo=vehiculo)
     │                    │
  zonas ──< clientes ──< pedidos ──< pedido_items >── productos
     │         │            │                              │
     │         │            └── hoja_ruta_paradas >── hojas_ruta
     │         │
     │         ├──< comprobantes ──< comprobante_items >── productos
     │         ├──< recibos ──< recibo_pagos >── cheques / cuentas_bancarias / caja_movimientos
     │         ├──< cc_clientes ──< cc_clientes_imputaciones (débito ↔ crédito)
     │         └──< visitas
     │
 listas_precios ──< producto_precios >── productos ──< producto_presentaciones
                                            │  ├──< promociones
 rubros ─────────────────────────────────── ┤  ├──< precios_historial
 marcas ─────────────────────────────────── ┤  └──< stock >── depositos
 proveedores ───────────────────────────────┘        stock_lotes, stock_movimientos (kardex)
     │
     ├──< ordenes_compra ──< orden_compra_items
     ├──< recepciones ──< recepcion_items
     ├──< facturas_proveedor
     ├──< devoluciones_proveedor ──< devoluciones_proveedor_items
     ├──< pagos_proveedor ──< pagos_proveedor_detalle
     └──< cc_proveedores ──< cc_proveedores_imputaciones

 cajas ──< caja_sesiones ──< caja_movimientos
 cuentas_bancarias ──< banco_movimientos
 gastos_categorias ──< gastos
 hojas_ruta ── rendiciones ──< rendicion_items / rendicion_pagos
 preventistas ──< comisiones_liquidaciones (comprobantes.liquidacion_id / recibos.liquidacion_id)
 puntos_venta ──< numeracion (último número por tipo de comprobante)
```

## Tablas por módulo

### Núcleo
| Tabla | Descripción |
|---|---|
| `configuracion` | Parámetros clave/valor (datos de empresa, crédito, stock, redondeos, etc.). |
| `roles`, `permisos`, `rol_permiso` | Permisos granulares `modulo.accion` (ver, crear, editar, anular, exportar). |
| `usuarios` | Login, rol, caja asignada. Contraseña con `password_hash`. |
| `login_intentos` | Para bloqueo temporal por intentos fallidos (usuario + IP). |
| `auditoria` | Quién, cuándo, qué, con JSON de datos anteriores y nuevos. |
| `migraciones` | Versiones de base aplicadas. |

### Catálogo
| Tabla | Descripción |
|---|---|
| `rubros`, `marcas` | Clasificación. |
| `productos` | Código interno único, EAN, costos (último y promedio), IVA, internos, mínimos/máximos, lotes. |
| `producto_presentaciones` | Unidad / display / pack / caja con `factor` a unidad base y EAN propio. |
| `listas_precios` | Mayorista, Kiosco, Minorista, Especial… con margen por defecto y redondeo. |
| `producto_precios` | Precio final por unidad base en cada lista; `modo` = margen (calculado) o fijo (manual). |
| `precios_historial` | Historial de cambios de costo (`lista_id` NULL) y de precios por lista. |
| `promociones` | Bonificaciones tipo "12+1" por producto con vigencia. |

### Stock
| Tabla | Descripción |
|---|---|
| `depositos` | Depósito central y depósitos de vehículo (uno por camión). |
| `stock` | Físico y reservado por producto/depósito. Disponible = cantidad − reservado. |
| `stock_lotes` | Lotes y vencimientos (solo productos con `maneja_lotes`). Salidas por FEFO. |
| `stock_movimientos` | Kardex: tipo, documento origen, cantidad con signo, **saldo resultante**, costo, usuario. |
| `ajustes_stock(_items)` | Ajustes con motivo. |
| `transferencias(_items)` | Entre depósitos (también la carga y descarga del camión). |
| `inventarios`, `inventario_items` | Toma de inventario físico: stock del sistema congelado + conteo. |

### Clientes, zonas, preventistas
| Tabla | Descripción |
|---|---|
| `zonas` | Zonas/rutas con días de visita y entrega y preventista responsable. |
| `clientes` | Datos comerciales, fiscales, logísticos, lista de precios, crédito, estado. |
| `cc_clientes` | Libro de cuenta corriente. `importe` con signo; `pendiente` = saldo sin imputar del renglón. |
| `cc_clientes_imputaciones` | Qué crédito (recibo/NC) canceló qué débito (factura/ND). Base de la antigüedad de deuda. |
| `visitas` | Visitas del preventista con o sin pedido (con `uuid` del dispositivo). |
| `preventistas` | Vinculado a usuario, comisión % y base (ventas o cobranzas). |
| `comisiones_liquidaciones` | Liquidaciones por período. |

### Compras
`ordenes_compra(_items)`, `recepciones(_items)`, `facturas_proveedor`, `devoluciones_proveedor(_items)`,
`pagos_proveedor(_detalle)`, `cc_proveedores(_imputaciones)`.

### Ventas
| Tabla | Descripción |
|---|---|
| `puntos_venta`, `numeracion` | Numeración por punto de venta y tipo (bloqueo `SELECT ... FOR UPDATE`). |
| `pedidos`, `pedido_items` | Preventa. `uuid` único evita duplicados al sincronizar offline. `reservado_base` por línea. |
| `comprobantes`, `comprobante_items` | Facturas A/B/C, remitos, NC, ND, presupuestos. Snapshot del cliente, discriminación de IVA, costo para rentabilidad, campos `cae`/`cae_vencimiento`. |
| `recibos`, `recibo_pagos` | Cobranzas con pagos mixtos; cada pago enlaza su movimiento de caja/banco o cheque. |

### Reparto
`hojas_ruta`, `hoja_ruta_paradas` (una por pedido, con estado de entrega y `evento_uuid`),
`rendiciones`, `rendicion_items` (mercadería), `rendicion_pagos` (dinero por forma de pago).

### Finanzas
`cajas`, `caja_sesiones`, `caja_movimientos`, `cuentas_bancarias`, `banco_movimientos`, `cheques`,
`gastos_categorias`, `gastos`.

## Reglas de integridad del negocio

1. **Stock**: sólo `App\Services\StockService` modifica `stock`, `stock_lotes` y `stock_movimientos`, siempre
   dentro de una transacción, con `SELECT ... FOR UPDATE` sobre la fila de stock. El saldo del kardex
   es el físico resultante; `stock.cantidad` = suma de movimientos del depósito.
2. **Reservas**: al confirmar un pedido se incrementa `stock.reservado` y `pedido_items.reservado_base`;
   se liberan al anular, al editar, al salir a reparto (la mercadería pasa al depósito del camión) o al
   convertirse en venta.
3. **Cuenta corriente**: saldo del cliente = `SUM(importe)` de `cc_clientes`. Toda anulación agrega un
   contra-asiento (`tipo = anulacion`) y deshace las imputaciones del renglón anulado.
4. **Caja**: los cobros en efectivo en oficina entran a la sesión abierta de la caja del usuario. Los cobros
   en efectivo del chofer quedan `pendiente_rendicion` hasta la rendición del reparto.
