# Cómo agregar la facturación electrónica de ARCA (ex AFIP)

El sistema emite hoy comprobantes con **facturación interna** (numeración propia, sin CAE). La capa de
facturación está desacoplada para incorporar ARCA sin tocar ventas, stock ni cuentas corrientes.

## Piezas existentes

| Archivo | Rol |
|---|---|
| `app/Services/Facturacion/ProveedorFacturacion.php` | Interfaz: `codigo()`, `requiereAutorizacion($tipo)`, `proximoNumero($tipo, $pv)`, `autorizar($comprobante, $items)`. |
| `app/Services/Facturacion/FacturacionInterna.php` | Implementación actual. |
| `app/Services/Facturacion/FacturacionFactory.php` | Devuelve el proveedor según `puntos_venta.proveedor_facturacion`. |
| `app/Services/VentaService::emitir()` | Pide el número al proveedor, guarda el comprobante y, si corresponde, llama a `autorizar()` dentro de la misma transacción. Si `autorizar()` lanza `ErrorNegocio` se revierte todo (stock, cuenta corriente, numeración). |
| Tabla `comprobantes` | Campos `cae`, `cae_vencimiento`, `proveedor_facturacion`, importes discriminados (`neto_21`, `neto_105`, `neto_exento`, `iva_21`, `iva_105`, `impuestos_internos`). |

## Pasos

1. **Certificado**: generar clave privada y CSR, dar de alta el certificado en ARCA (servicio `wsfe`) y
   asociarlo al CUIT. Guardar `.crt` y `.key` en `storage/arca/` (protegida por `.htaccess`).
2. **Punto de venta**: dar de alta en ARCA un punto de venta "Factura electrónica - Web Services" y crearlo en
   *Administración › Puntos de venta*.
3. **Clase** `app/Services/Facturacion/ProveedorFacturacionArca.php` que implemente la interfaz:
   - `obtenerTicketAcceso()`: firma un TRA (CMS/PKCS#7 con `openssl_pkcs7_sign`) y llama a **WSAA**
     `loginCms`; cachear token y sign en `storage/cache` hasta su vencimiento (12 h).
   - `proximoNumero()`: **WSFEv1** `FECompUltimoAutorizado` + 1. Códigos: FA=1, NDA=2, NCA=3, FB=6, NDB=7, NCB=8, FC=11, NDC=12, NCC=13.
   - `autorizar()`: `FECAESolicitar` con `Concepto=1` (productos), `DocTipo` 80 (CUIT) / 96 (DNI) / 99 (consumidor final),
     `ImpNeto = neto_21 + neto_105`, `ImpOpEx = neto_exento`, `ImpTrib = impuestos_internos` (tributo 4), alícuotas
     `Id 5` (21 %) y `Id 4` (10,5 %), `CondicionIVAReceptorId` según `cliente_condicion_iva`, y `CbtesAsoc` para NC/ND
     (usar `comprobante_asociado_id`). Devolver `['cae' => ..., 'cae_vencimiento' => 'AAAA-MM-DD']`; si el resultado es
     `R`, lanzar `ErrorNegocio` con las observaciones.
   - `requiereAutorizacion()`: `true` para FA/FB/FC/NC/ND, `false` para RM y PR.
   Se puede usar `SoapClient` de PHP (extensión `soap`) con los WSDL de homologación y producción.
4. **Registrar** en `FacturacionFactory::PROVEEDORES` → `'arca' => ProveedorFacturacionArca::class`, y agregar la opción
   en `disponibles()`.
5. Elegir "ARCA" en el punto de venta. Probar primero en **homologación**.
6. **QR fiscal**: en `impresion/comprobante.php` y `PdfComprobante` agregar el QR (JSON en base64 según RG 4892)
   cuando exista `cae`.

## Recomendaciones

- Mantener la emisión dentro de la transacción: si ARCA no responde, no queda un comprobante sin CAE.
- Para cortes del servicio, permitir emitir **remito** (interno) y facturar después desde el pedido/remito.
- Registrar en `storage/logs` los XML enviados/recibidos (sin la clave privada).
