# Decisiones tomadas

Registro de las decisiones de diseño tomadas donde el pedido no definía el detalle, pensando en una
distribuidora mayorista chica de Salta.

## Arquitectura

| # | Decisión | Motivo |
|---|---|---|
| 1 | MVC propio liviano (`app/Core`): router, request/response, vistas en PHP plano, PDO. Autoloader PSR-4 propio, **sin Composer**. | Funciona subiendo archivos por FTP a Ferozo sin ejecutar comandos. |
| 2 | Lógica de negocio en servicios (`app/Services`) y controladores finos. | Se puede probar sin HTTP (pruebas automatizadas y datos demo usan los mismos servicios). |
| 3 | Un único punto de entrada `public/index.php`. Si no se puede apuntar el dominio a `/public`, el `.htaccess` de la raíz reenvía todo a `/public` y bloquea `app`, `config`, `storage`, `vendor`, etc. | Requisito de hosting compartido. Cada carpeta sensible tiene además su propio `.htaccess` "Require all denied". |
| 4 | La ruta base se detecta sola (`/`, subcarpeta o subdominio). Se puede forzar con `app.url`. | Instalaciones en subcarpetas sin configurar nada. |
| 5 | PDF con **FPDF 1.9** incluido en `vendor/fpdf` (un solo archivo, licencia libre). Excel con un generador **XLSX propio** (ZipArchive); si el hosting no tiene `zip`, se entrega CSV. CSV con separador `;` y BOM UTF-8 (abre bien en Excel en español). | Sin dependencias pesadas (Dompdf/PhpSpreadsheet). |
| 6 | Comprobantes también en HTML optimizado para imprimir (A4 y ticket 80 mm). | Imprimir desde el navegador es lo más simple en mostrador. |
| 7 | Sesiones guardadas en `storage/sessions`. | En hosting compartido la carpeta de sesiones del sistema a veces se limpia o comparte. |
| 8 | Zona horaria fija UTC−3 en MySQL (`SET time_zone = '-03:00'`). | Argentina no usa horario de verano y el hosting puede no tener cargadas las zonas horarias con nombre. |

## Precios, costos e impuestos

| # | Decisión | Motivo |
|---|---|---|
| 9 | Los precios de lista son **finales con IVA incluido, por unidad base**. El precio de una presentación es precio unitario × factor. | En kioscos se habla de precio final; Factura A discrimina el IVA a partir del final. |
| 10 | Precio = costo × (1 + margen) × (1 + IVA) + impuestos internos, redondeado según la lista (múltiplo configurable; modo "hacia arriba" por defecto). | Fórmula usual en distribuidoras. |
| 11 | Cada precio por lista puede ser **por margen** (se recalcula con el costo) o **fijo** (manual). Margen vacío = margen de la lista. | Pedido explícito. |
| 12 | Impuestos internos como **monto fijo por unidad base** (cigarrillos, gaseosas), sin IVA, discriminados en Factura A. | Simplificación razonable; el proveedor informa el importe. |
| 13 | **Costo predeterminado: último costo**; se mantiene también el **promedio ponderado** y se puede elegir en Configuración. | Con inflación, el último costo refleja el precio de reposición. |
| 14 | Al recibir mercadería se actualizan costos y se recalculan precios por margen (desactivable). Toda variación queda en `precios_historial`. | Evita vender por debajo del costo nuevo. |
| 15 | Letra de comprobante: empresa RI → cliente RI = **A**, resto = **B**; empresa monotributista = **C**. Factura B muestra "IVA contenido" (Ley 27.743). | Normativa vigente. |
| 16 | Bonificaciones "12+1" como **promociones por producto** (unidades sin cargo), aplicadas automáticamente al cargar pedidos y editables por línea. | Práctica común de las marcas. |

## Stock

| # | Decisión | Motivo |
|---|---|---|
| 17 | Todo cambio de stock pasa por `StockService` y queda en el kardex con el **saldo resultante**; `stock` se actualiza en la misma transacción con bloqueo de fila. | Consistencia (stock = suma del kardex, verificado en las pruebas). |
| 18 | Reservas al confirmar pedidos (`stock.reservado`). Disponible = físico − reservado. La venta en mostrador respeta las reservas. | Pedido explícito. |
| 19 | Lotes opcionales por producto; las salidas usan **FEFO** (primero vence, primero sale). Las transferencias conservan lote y vencimiento. | Golosinas, galletitas y bebidas vencen. |
| 20 | Cada vehículo tiene su **depósito "Camión"**. Al dar salida a la hoja de ruta la carga se transfiere al camión (y se liberan las reservas); en la rendición lo no entregado vuelve al depósito y los faltantes/sobrantes se ajustan. | Control real de lo que sale y vuelve. |
| 21 | Stock negativo no permitido por defecto (configurable). | Evita errores de carga. |
| 22 | Inventario físico: se congela el stock del sistema al generar la planilla; al aplicar se ajusta contado − sistema. | Los movimientos durante el conteo no distorsionan el ajuste. |

## Ventas, cuentas corrientes y caja

| # | Decisión | Motivo |
|---|---|---|
| 23 | **Toda venta va a la cuenta corriente** del cliente y los cobros son **recibos** imputados (aunque sea contado). | Un único circuito de cobranza; facilita pagos mixtos y parciales. |
| 24 | Cuenta corriente como libro con importe con signo + "pendiente" por renglón + tabla de imputaciones. Lo no imputado se aplica automáticamente a la deuda más antigua (FIFO). | Permite antigüedad de deuda exacta. |
| 25 | Nunca se borra un documento: se **anula** con motivo, se revierte stock (al depósito original o al central si salió de un camión), cuenta corriente (contra-asiento) y caja/banco. | Pedido explícito. |
| 26 | Remitos ("X") también descuentan stock y generan deuda (remito valorizado); presupuestos no mueven nada y se convierten en pedido. | Práctica habitual. |
| 27 | Consumidor Final sólo de contado. | Evita deuda sin titular. |
| 28 | Control de crédito configurable: **advertir** (por defecto), bloquear o ignorar; tolerancia de días y bloqueo automático diario por mora (cron). | Cada distribuidora tiene su política. |
| 29 | El efectivo cobrado por el chofer no entra a caja hasta la **rendición** (queda "pendiente de rendición"); transferencias y Mercado Pago van directo a la cuenta y se confirman en la rendición. | Refleja dónde está el dinero. |
| 30 | El comprobante del reparto se emite al **registrar la entrega** (con lo realmente entregado), factura o remito según configuración. | Evita notas de crédito por cada faltante o rechazo. |
| 31 | Cheques de terceros: cartera → depositado / endosado a proveedor / rechazado. Un rechazo debita el banco (si estaba depositado) y vuelve a cargar la deuda al cliente. | Circuito habitual. |
| 32 | Pagos con tarjeta van a una cuenta "Tarjetas a acreditar"; la acreditación se registra como transferencia entre cuentas. | Simple y suficiente. |
| 33 | Comisiones: % sobre ventas netas sin IVA (descontando NC) o sobre cobranzas, según el preventista. Los documentos liquidados quedan marcados para no liquidarse dos veces. | Esquemas más comunes. |

## Facturación electrónica

| # | Decisión | Motivo |
|---|---|---|
| 34 | Interfaz `ProveedorFacturacion` + `FacturacionInterna` (numeración propia, sin CAE) + fábrica por punto de venta. Campos `cae` y `cae_vencimiento` ya en la base. **No** se implementa la conexión a ARCA (ver `docs/FACTURACION_ELECTRONICA.md`). | Pedido explícito. |
| 35 | Numeración correlativa sin huecos (bloqueo de fila dentro de la transacción: si la emisión falla, el número no se consume). | Verificado en pruebas. |

## App móvil (PWA) y modo sin conexión

| # | Decisión | Motivo |
|---|---|---|
| 36 | Dos apps livianas (Alpine.js) en `/movil` (preventista) y `/chofer`, con barra inferior y hojas inferiores; el resto del sistema es responsive con menú lateral. Cada rol entra directo a su pantalla. | Mobile-first para la calle. |
| 37 | La app descarga **todos** sus datos (clientes asignados, catálogo, precios de sus listas, stock disponible, últimos pedidos) en IndexedDB y trabaja sin señal. Las operaciones van a una **cola con UUID del dispositivo**; el servidor las procesa de forma idempotente. | Requisito de conexión inestable; reintentos sin duplicar. |
| 38 | Si al sincronizar hay problemas (stock, precio cambiado, cliente bloqueado, crédito) el pedido **se guarda igual en borrador "para revisión"** con el detalle; nunca se pierde. Sin problemas se confirma automáticamente (configurable). | Pedido explícito. |
| 39 | Service Worker: estáticos con caché, páginas con red primero y copia guardada; la API no se cachea. La primera visita se guarda pidiendo al SW que la descargue. | Abrir la app sin señal desde el primer uso. |
| 40 | La ubicación GPS de visitas y pedidos es opcional y nunca demora más de 2,5 s. | Un permiso sin responder no puede trabar la venta. |
| 41 | Sesión de preventistas y choferes: 12 h de inactividad (oficina: 60 min). Si vence, la cola queda guardada y se envía al volver a ingresar. | Trabajan todo el día con el celular. |
| 42 | Escáner con **html5-qrcode** (carga diferida sólo al usar la cámara). Requiere HTTPS (Let's Encrypt gratis en Ferozo). | Liviano para gama baja. |

## Seguridad y operación

| # | Decisión | Motivo |
|---|---|---|
| 43 | Bloqueo de ingreso: 5 intentos fallidos por usuario (o 20 por IP) en 15 minutos (configurable). La sesión se ata al navegador, no a la IP (en datos móviles cambia). | Seguridad sin molestar en la calle. |
| 44 | Permisos `módulo.acción` (ver, crear, editar, anular, exportar, precios); el Administrador tiene todo siempre. Los enlaces del menú y del panel se muestran sólo si hay permiso (verificado rastreando el sistema con cada rol). | Granularidad pedida. |
| 45 | Auditoría guarda sólo los campos que cambiaron (antes/después) y nunca la contraseña. | Legibilidad y seguridad. |
| 46 | Backups en PHP por lotes, comprimidos con gzip, se conservan los últimos 15; cron diario que además aplica el bloqueo automático de morosos. | Sin `mysqldump` en hosting compartido. |
| 47 | Migraciones numeradas en `database/migrations` (SQL o PHP); la 001 es `schema.sql`. Se aplican desde el panel o por consola. | Actualizaciones futuras ordenadas. |
| 48 | Datos demo generados con los servicios reales (4 semanas simuladas) y exportados a `seed_demo.sql` con **fechas relativas al momento de importar**. | El panel y los reportes muestran datos recientes siempre. |
| 49 | Las fotos de productos se reducen a 600 px y miniatura de 160 px (JPEG). | Pantallas livianas en 3G. |
| 50 | Contador = sólo panel y reportes (lectura y exportación). | Pedido explícito. |
