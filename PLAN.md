# Plan de trabajo — Distribuidora Toro

Leyenda: [x] terminado · [ ] pendiente

## Fase 0 — Arquitectura
- [x] Relevamiento del entorno (PHP 8.2/8.3, MariaDB 10.4, Apache con mod_rewrite)
- [x] Librerías frontend descargadas en `public/assets/vendor` (Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Alpine 3.14.1, Chart.js 4.4.4, html5-qrcode 2.3.8) y FPDF 1.9 en `vendor/fpdf`
- [x] Modelo de datos completo (`database/schema.sql`, `DATABASE.md`)

## Fase 1 — Núcleo
- [x] Autoloader PSR-4 propio, bootstrap, configuración, manejo centralizado de errores y logs
- [x] Router con URLs amigables, Request/Response, vistas con layout
- [x] PDO + helpers, transacciones
- [x] Sesión segura, login con bloqueo por intentos, CSRF, inactividad
- [x] Roles y permisos granulares, auditoría
- [x] Layout responsive (sidebar PC, barra inferior móvil, modo oscuro), componentes (tabla→tarjetas, paginación, autocompletado)

## Fase 2 — Productos y stock
- [x] ABM productos con presentaciones, precios por lista, foto, promociones
- [x] Rubros, marcas, listas de precios, depósitos (ABM genérico)
- [x] Actualización masiva de costos/precios + historial
- [x] Importación/exportación CSV
- [x] StockService: movimientos, kardex, lotes FEFO, reservas
- [x] Ajustes, transferencias, inventario físico, vencimientos, valorización

## Fase 3 — Clientes
- [x] ABM clientes, zonas, cuenta corriente con imputaciones y antigüedad, historial, bloqueo por deuda

## Fase 4 — Proveedores y compras
- [x] ABM proveedores, órdenes de compra con sugerencia, recepción (último costo / promedio), facturas, cc, pagos, devoluciones

## Fase 5 — Pedidos
- [x] Pedidos con estados, reservas, validación de crédito/stock, descuentos, bonificaciones, repetir, picking

## Fase 6 — Ventas y facturación
- [x] Servicio de comprobantes con IVA discriminado, ProveedorFacturacion (interno) desacoplado
- [x] Venta desde pedido, venta directa, POS con lector, NC/ND, devoluciones, presupuestos
- [x] Impresión A4 / ticket 80 mm, PDF (FPDF)

## Fase 7 — Reparto
- [x] Vehículos, choferes, hojas de ruta, carga consolidada, salida (transferencia al camión)
- [x] Registro de entregas (total/parcial/rechazo/cerrado) con cobro, rendición, reprogramación

## Fase 8 — Cobranzas, caja y finanzas
- [x] Recibos con imputación, cheques de terceros, cajas con arqueo, bancos/billeteras, gastos

## Fase 9 — Preventistas y app móvil
- [x] ABM preventistas, liquidación de comisiones
- [x] PWA preventista offline (ruta, clientes, catálogo, pedidos, visitas, escáner)
- [x] PWA chofer offline (entregas, cobros)

## Fase 10 — Reportes
- [x] Todos los reportes con filtros, CSV/Excel, PDF/impresión

## Fase 11 — Configuración, auditoría, backups
- [x] Empresa, parámetros, puntos de venta, usuarios/roles/permisos, auditoría, backups, cron

## Fase 12 — Instalador, datos demo, documentación y pruebas
- [x] Instalador web autobloqueante, migraciones, `datos_iniciales.sql`, `seed_demo.sql`
- [x] Pruebas automatizadas + prueba punta a punta + recorrido móvil y offline en navegador
- [x] README.md, MANUAL_USUARIO.md, DECISIONES.md

## Verificación final
- [x] `php -l` sin errores en PHP 8.2 y 8.3
- [x] 49 pruebas automatizadas OK (`php tests/run.php`), incluida la de ciclo completo compra → recepción → pedido → preparación → hoja de ruta → entrega parcial → cobranza → rendición → reportes
- [x] Pruebas de navegador OK: 42 pantallas sin desborde en 360/390/412 px; recorrido del preventista y del chofer con corte de conexión y resincronización sin duplicados
- [x] Rastreo de enlaces con todos los roles: sin páginas rotas ni enlaces a secciones sin permiso
- [x] Instalador web probado en base nueva (se autobloquea); `schema.sql` + `datos_iniciales.sql` / `seed_demo.sql` importan por SQL
- [x] Backup generado en PHP y restaurado con conteos idénticos
- [x] Despliegue en subcarpeta con Apache + `.htaccess` y con servidor embebido (raíz = `/public`)
- [x] Consistencia de datos: stock = kardex, reservas = pedidos, lotes = stock, sin UUID duplicados
