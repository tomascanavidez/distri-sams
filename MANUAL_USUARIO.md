# Manual de usuario — Distribuidora Toro

Guía práctica para el personal. No hace falta saber de computación: cada sección explica qué hace la pantalla y cómo
usarla paso a paso.

---

## 1. Ingreso y pantalla principal

- Entrá a la dirección del sistema e ingresá tu **usuario** y **contraseña**. Cada persona entra directo a su pantalla:
  la oficina al **Panel**, el mostrador al **Mostrador**, los preventistas a **Mi ruta de hoy**, los choferes a **Mis
  entregas de hoy**, el depósito a **Stock** y el contador a **Reportes**.
- Si te equivocás 5 veces seguidas, el usuario se bloquea 15 minutos.
- Si no usás el sistema por un rato (60 minutos en la oficina, 12 horas en el celular) la sesión se cierra sola.
- **Mi clave** (abajo en el menú) sirve para cambiar tu contraseña.
- El botón ◐ cambia entre **modo claro y oscuro** (útil de noche o al sol).

**Panel principal**: ventas del día, la semana y el mes; pedidos pendientes; entregas en curso; cobranzas del día; plata en
caja; deuda vencida; productos con stock bajo o por vencer; mejores clientes y productos; gráficos de ventas por día y por
zona. Tocá cualquier tarjeta para ir al detalle.

En la computadora el menú está a la izquierda. En el celular el menú se abre con ☰ y abajo hay una barra con los accesos más
usados.

---

## 2. Productos y precios (menú Productos)

### Alta de un producto
*Productos › Nuevo producto*:
1. **Código interno** (se propone solo), **código de barras**, **descripción**, rubro, marca y proveedor habitual.
2. **Unidad base**: la unidad en que se cuenta el stock (unidad, paquete, botella…).
3. **Presentaciones**: agregá cómo se vende o compra en bulto. Ejemplo: *Display = 20 unidades*, *Caja = 240 unidades*.
   Marcá cuál se usa por defecto para vender y para comprar. Cada presentación puede tener su propio código de barras.
4. **Costo neto** (sin IVA) por unidad base, **IVA** (21 %, 10,5 % o exento) e **impuestos internos** si corresponde.
5. Stock mínimo y máximo, ubicación en el depósito (para armar pedidos), si maneja **lotes y vencimientos**, y una **foto**.

### Precios por lista
En la ficha del producto, cuadro **Precios por lista**. Hay listas (Mayorista, Kiosco, Minorista, Especial). Para cada una:
- **Por margen**: el precio se calcula solo con el costo + margen + IVA y se redondea (si el margen queda vacío se usa el de la
  lista). Cuando cambia el costo, el precio cambia solo.
- **Precio fijo**: lo escribís a mano y no cambia con el costo.

Los precios son **finales con IVA** y por unidad; el precio del display o la caja se calcula multiplicando.

**Margen por defecto de cada lista**: se cambia en *Administración › Empresa y parámetros › Listas de precios: margen por
defecto*. Al guardar se recalculan los precios de todos los productos que usan el margen de la lista; los que tienen margen
propio o precio fijo no se tocan, salvo que marques **Aplicar también a esos productos**.

### Aumentos de precios
*Productos › Actualizar precios*: elegí rubro, marca o proveedor, el porcentaje (negativo para bajar) y si se aumenta el
**costo** (lo habitual cuando el proveedor manda lista nueva) o directamente los **precios** de una lista. Todo queda en
*Historial de precios*.

Con **Fijar margen** le das un margen propio en una lista a todo un rubro, marca o proveedor (dejalo vacío para que vuelvan a
usar el margen de la lista).

### Promociones
*Productos › Promociones*: "llevando 12 te regalamos 1". Al cargar pedidos se aplican solas (unidades sin cargo).

### Importar y exportar
*Productos › Importar / exportar*: descargá la plantilla, completala en Excel (separador `;`) y subila. Si el código existe se
actualiza; si no, se crea. También podés bajar la **lista de precios** en Excel o PDF.

---

## 3. Stock (menú Stock)

- **Existencias**: stock físico, reservado (pedidos confirmados), **disponible** y valor a costo. Filtrá por depósito, rubro o
  "bajo el mínimo".
- **Movimientos (kardex)**: cada entrada y salida con fecha, motivo, usuario y saldo.
- **Ajustes**: para roturas, vencidos, faltantes, sobrantes o consumo interno. Elegí el motivo, buscá los productos y
  confirmá. Se pueden anular.
- **Transferencias**: pasar mercadería entre depósitos.
- **Inventario físico**: *Generar planilla* (de todo o de un rubro) → imprimí la planilla → contá → cargá lo contado →
  *Aplicar diferencias*. El sistema ajusta automáticamente.
- **Vencimientos**: lo que vence en los próximos días (los vencidos en rojo).
- **Depósitos**: el depósito central y uno por cada camión (se crea solo al cargar el vehículo).

---

## 4. Clientes (menú Clientes)

- **Nuevo cliente**: comercio, titular, CUIT/DNI y condición de IVA (Responsable Inscripto recibe Factura A), dirección,
  zona, ubicación (botón 🎯 para tomar la posición actual desde el celular), teléfono y WhatsApp, días y horario de visita y
  de entrega, **lista de precios**, preventista, **condición de pago** (contado o cuenta corriente a X días) y **límite de
  crédito** (0 = sin límite).
- **Ficha del cliente**: botones para **llamar**, **WhatsApp** (con un mensaje ya escrito) y **Cómo llegar** (Google Maps);
  saldo, deuda vencida, antigüedad de la deuda, productos que compra habitualmente, últimos pedidos, comprobantes y visitas.
- **Estado**: activo, inactivo o **bloqueado** (no se le pueden confirmar pedidos).
- **Cuenta corriente**: todos los movimientos con saldo, qué está pendiente y desde cuándo. Se puede exportar o imprimir para
  mandarle al cliente. Desde ahí también se carga un **saldo inicial** o un ajuste.
- **Saldos y deudas**: todos los clientes con deuda, separada por antigüedad (a vencer, 1-30, 31-60, más de 60 días).
- **Zonas / rutas**: cada zona con sus días de visita y entrega y su preventista.

> El control de crédito (avisar o bloquear cuando un cliente supera el límite o tiene deuda vencida) se configura en
> *Administración › Empresa y parámetros*.

---

## 5. Pedidos (menú Pedidos)

### Cargar un pedido en la oficina
*Pedidos › Nuevo pedido*: buscá el cliente (se ven su lista, saldo y avisos de deuda). Buscá productos por nombre, marca o
código, o **escaneá** el código de barras (📷). Elegí la presentación (unidad, display, caja), la cantidad con − / +,
descuentos y bonificaciones. **Repetir último pedido** carga lo que pidió la vez anterior.
- **Guardar borrador**: queda sin reservar mercadería.
- **Confirmar pedido**: **reserva el stock**. Si falta mercadería, avisa qué producto.

### Estados
Borrador → Confirmado → En preparación → Preparado → En reparto → Entregado / Entregado parcial / Rechazado (o Anulado).

### Preparación (depósito)
*Pedidos › Preparación*: marcá los pedidos y tocá **Hoja de preparación** (lista consolidada ordenada por ubicación en el
depósito, más el detalle por pedido). Al terminar, **Marcar preparados**.

### Pedidos de la app "a revisar"
Si un pedido del celular tuvo un problema (precio cambiado, falta de stock, cliente bloqueado) aparece en *Pedidos › A revisar*
con el detalle. Abrilo, **Editá** lo que haga falta y **Confirmalo**.

### Convertir en venta
Para un pedido que el cliente retira: en el pedido, *Convertir en venta* (factura o remito, con cobro opcional).

---

## 6. Ventas y mostrador (menú Ventas)

### Mostrador (POS)
Pensado para el lector de códigos de barras:
- El cursor queda en el buscador: pasá el lector o escribí. **3*779…** agrega 3 unidades de ese código.
- **F2** buscar · **F4** cobrar · **F8** nueva venta.
- El cliente por defecto es "Consumidor Final" (sólo contado); se puede cambiar.
- **Cobrar**: elegí una o varias formas de pago (efectivo, transferencia, Mercado Pago/QR, tarjeta, cheque). En efectivo,
  "Paga con" calcula el **vuelto**. Al confirmar se imprime el **ticket**.
- Para cobrar en efectivo la **caja tiene que estar abierta**.

### Nueva venta (oficina)
Factura, remito o presupuesto para cualquier cliente. Lo que no se paga en el momento va a la **cuenta corriente**. El
sistema elige solo Factura **A** (clientes Responsables Inscriptos) o **B** (el resto).

### Comprobantes
Lista de todo lo emitido. En cada uno: imprimir en **A4** o **ticket**, descargar **PDF**, hacer una **devolución**, cobrar el
saldo o **anular** (pide el motivo; vuelve el stock, la cuenta corriente y la caja; el número no se reutiliza).

### Devoluciones y notas de crédito/débito
*Ventas › Devolución / NC*:
- **Devolución de mercadería**: buscá la factura, indicá cuánto devuelve de cada producto y si **reingresa al stock** (si está
  en buen estado) o no. Se emite la nota de crédito.
- **Nota de débito o crédito por concepto**: intereses, diferencias de precio, gastos de un cheque rechazado, etc.

### Presupuestos
No mueven stock ni cuenta corriente. Desde el presupuesto, *Convertir en pedido*.

---

## 7. Reparto (menú Reparto)

1. **Nueva hoja de ruta**: filtrá por zona y fecha, marcá los pedidos preparados, elegí **vehículo** y **chofer**.
2. En la hoja: ordená las paradas (número de orden), agregá o quitá pedidos. Imprimí la **hoja de ruta** (con remitos para
   firmar) y la **carga del camión** (consolidado por producto con bultos).
3. **Dar salida**: la mercadería pasa del depósito al camión. (El chofer también puede darla desde su celular.)
4. Durante el reparto el chofer registra cada entrega desde el celular. Si no usa el celular, la oficina puede registrar cada
   parada con el botón **Registrar** (entregado, parcial, rechazado o cerrado, con el cobro).
5. **Rendición** (al volver):
   - **Mercadería**: contá lo que vuelve; el sistema muestra cuánto debería volver. Las diferencias se ajustan como faltante o
     sobrante y lo que vuelve regresa al depósito.
   - **Dinero**: efectivo esperado vs. rendido (la diferencia queda registrada); transferencias y Mercado Pago para verificar.
     El efectivo ingresa a la caja elegida (tiene que estar abierta).
   - **No entregados**: marcá **Reprogramar** para que el pedido vuelva a "preparado" y salga en otra hoja; si no, queda
     rechazado con su motivo.

**Vehículos** y **Choferes** se cargan en el mismo menú. Para que un chofer use la app, asociale su usuario.

---

## 8. Cobranzas (menú Cobranzas)

*Nueva cobranza*: buscá el cliente; aparecen sus comprobantes pendientes con días de atraso. Cargá lo que paga (una o varias
formas; para cheques: número, banco y fecha de pago) y tocá **Imputar el cobro** (se aplica a lo más viejo) o escribí cuánto
va a cada comprobante. Lo que sobra queda **a cuenta**. Se imprime el **recibo**. Un recibo se puede anular (con motivo).

---

## 9. Caja y finanzas (menú Finanzas)

- **Caja**: cada caja (oficina, mostrador) se **abre** con el fondo inicial, recibe automáticamente los cobros y pagos en
  efectivo, y permite **movimientos manuales** (cambio, retiros, depósitos). Al final del día, **Arqueo y cierre**: cargá la
  cantidad de billetes de cada valor y el sistema calcula la diferencia con lo teórico.
- **Bancos y billeteras**: saldos y movimientos de cada cuenta (banco, Mercado Pago, tarjetas a acreditar). Movimientos
  manuales y transferencias entre cuentas.
- **Cheques**: los cheques de terceros recibidos. Desde acá se **depositan** o se marcan como **rechazados** (vuelve la deuda
  al cliente). Para **endosarlos** a un proveedor, usalos como forma de pago en *Compras › Pagos*.
- **Gastos**: combustible, sueldos, alquiler, servicios, mantenimiento de vehículos… Elegí si sale de la caja o del banco.

---

## 10. Compras y proveedores (menú Compras)

- **Proveedores**: datos, plazo de pago y **cuenta corriente**.
- **Sugerencia de compra**: según las ventas de los últimos días y el stock mínimo, propone qué comprar a cada proveedor
  (en bultos). Marcá y tocá *Crear orden con lo marcado*.
- **Órdenes de compra**: borrador → enviada → recibida parcial → recibida (o anulada). Se imprimen para mandar al proveedor.
- **Recepciones**: *Recibir mercadería* contra una orden (se proponen las cantidades pendientes; podés recibir parcial) o sin
  orden. Cargá lote y vencimiento si el producto los maneja. **Ingresa el stock y actualiza los costos** (y precios por margen).
- **Facturas de proveedor**: número, fecha, neto, IVA, percepciones e impuestos; van a la cuenta corriente del proveedor.
- **Pagos**: efectivo, transferencia, cheque propio o **cheque de terceros** (endoso); se imputan a las facturas.
- **Devoluciones**: mercadería que vuelve al proveedor (descuenta stock), con su nota de crédito opcional.

---

## 11. App del preventista (celular)

Abrila una vez con conexión y agregala a la pantalla de inicio (menú del navegador › *Agregar a pantalla de inicio*).

- **Barra de estado** (arriba): verde "Sincronizado", amarilla "Sin conexión · X pendientes", naranja "pendientes de enviar"
  (tocala para reintentar). Todo lo que cargues sin señal **se guarda en el celular** y se envía solo cuando vuelve.
- **Inicio**: visitas hechas y pendientes, pedidos y ventas del día, y las próximas visitas.
- **Mi ruta**: los clientes que tocan hoy, con su saldo y deuda vencida. En cada uno: **Pedido**, **Sin pedido** (elegís el
  motivo: tiene mercadería, cerrado, sin dinero…) y **Mapa**. Con "Todos mis clientes" ves el resto.
- **Cargar un pedido**: buscá por nombre, marca o código, o escaneá con la cámara 📷. Usá los filtros **Habituales** (lo que
  ese cliente compra siempre) y por rubro. Elegí la presentación y tocá **+** / **−**. Abajo siempre ves el **total**. *Ver
  pedido* para revisar y **Confirmar pedido**. *Repetir último* carga el pedido anterior.
- **Ficha del cliente**: llamar, WhatsApp, mapa, saldo, límite y últimos pedidos.
- **Nuevo cliente** (en Inicio, Clientes o Más): nombre del comercio, dirección y los datos que tengas (CUIT, teléfono, zona,
  lista, días de visita, ubicación GPS si estás en el comercio). Funciona **sin señal**: el cliente aparece enseguida en tu
  lista, podés tomarle el pedido (*Guardar y tomar pedido*) y todo se envía junto al volver la conexión. Queda con pago
  contado; la oficina ajusta crédito y condiciones. Si el CUIT es inválido se guarda sin él y con una nota para revisar.
- **Productos y precios** (en Inicio o Más): todo el catálogo con el precio de **cada lista** por unidad y por presentación,
  y el stock disponible. Se busca por nombre, marca o código y se filtra por rubro. También funciona sin señal.
- **Más › Mis pedidos**: estado de lo enviado. Si un pedido quedó **para revisión** (por precio o stock) la oficina lo
  revisa: no se pierde.

## 12. App del chofer (celular)

- **Inicio**: avance del reparto, plata a entregar y la **próxima parada** (con *Cómo llegar*). Si la hoja todavía no salió,
  botón **Salir a reparto**.
- **Entregar** una parada:
  - **Entregado**: todo el pedido. **Parcial**: ajustás con − / + lo que realmente recibió (el importe a cobrar se recalcula
    solo) y elegís el motivo. **Rechazado**: elegís el motivo. **Cerrado**: el comercio estaba cerrado.
  - **Cobro**: efectivo, transferencia o Mercado Pago (puede ser mixto). Lo que no cobra queda en la cuenta corriente.
- Sin señal funciona igual: las entregas quedan guardadas y se envían solas.
- **Más**: lo cobrado pendiente de enviar y la opción de actualizar la hoja de ruta.
- Al volver, la oficina hace la **rendición** con lo que trajo.

---

## 13. Preventistas y comisiones (menú Preventistas)

- **Preventistas**: datos, usuario de la app, zonas y **comisión** (% sobre ventas netas o sobre cobranzas). Muestra ventas,
  visitas y efectividad del mes.
- **Visitas**: todas las visitas con o sin pedido, motivo y ubicación.
- **Comisiones**: elegí preventista y período → *Calcular* → *Liquidar*. Luego *Marcar pagada* (o anular). Lo liquidado no se
  vuelve a liquidar.

---

## 14. Reportes (menú Reportes)

Todos tienen filtros por fecha y se exportan a **CSV**, **Excel** o **PDF/imprimir**:
ventas (por día, mes, cliente, producto, rubro, marca, zona, preventista o comprobante), cobranzas por forma de pago,
rentabilidad y margen bruto, comisiones, compras, stock valorizado, kardex, productos sin movimiento, rotación, cuentas
corrientes de clientes y proveedores con antigüedad, **Libro IVA Ventas** y **Compras**, rendiciones de reparto, entregas
rechazadas y motivos, flujo de caja y **resultado del mes** (ventas − costo de mercadería − gastos).

---

## 15. Administración

- **Empresa y parámetros**: nombre, razón social, CUIT, IVA, Ingresos Brutos, domicilio, **logo** (sale en las facturas),
  leyenda, punto de venta, comprobante del reparto (factura o remito), costo para precios (último o promedio), redondeo,
  control de crédito, stock negativo, alertas de vencimiento, seguridad del ingreso y **numeración** de comprobantes.
- **Puntos de venta**, **Listas de precios**, **Cajas**, **Categorías de gastos**, **Cuentas bancarias**: en sus menús.
- **Usuarios**: alta, rol, caja que usa, contraseña, activar o desactivar.
- **Roles y permisos**: para cada rol, qué puede **ver, crear, editar, anular, exportar** en cada módulo y a qué pantalla
  entra. Se pueden crear roles nuevos copiando uno existente.
- **Auditoría**: quién hizo qué y cuándo, con los valores anteriores y nuevos (precios, stock, anulaciones, cobranzas,
  usuarios…).
- **Copias de seguridad**: *Generar copia ahora* y descargar. Las automáticas se generan todas las noches si está configurado
  el cron.
- **Actualizaciones de base**: al instalar una versión nueva del sistema, aplicar las pendientes (antes, hacer una copia).

---

## Preguntas frecuentes

- **¿Puedo borrar una factura mal hecha?** No se borran: se **anulan** con motivo y el sistema deshace todo (stock, cuenta
  corriente, caja). Luego hacé la correcta.
- **Un cliente pagó de más.** El sobrante queda como crédito a favor y se descuenta solo de su próxima compra.
- **¿Por qué no me deja confirmar un pedido?** Falta stock disponible (mirá el producto en rojo) o el cliente está bloqueado
  o supera su crédito (según la configuración).
- **El celular dice "pendientes de enviar".** Está sin señal o la sesión venció; cuando vuelva la conexión (o al ingresar de
  nuevo) se envía todo solo.
