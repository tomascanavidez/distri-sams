// Verifica que ninguna pantalla tenga scroll horizontal en 360, 390 y 412 px
const h = require('./helpers');
const RUTAS = ['/', '/pedidos', '/pedidos/nuevo', '/pedidos/1', '/ventas', '/ventas/pos', '/ventas/nueva', '/ventas/1', '/clientes', '/clientes/2', '/clientes/2/cuenta', '/clientes/saldos',
  '/productos', '/productos/1', '/productos/1/editar', '/stock', '/stock/movimientos', '/stock/inventarios/1', '/compras', '/compras/1', '/compras/recepciones/nueva?orden_id=1',
  '/compras/pagos/nuevo?proveedor_id=1', '/reparto', '/reparto/1', '/cobranzas', '/cobranzas/nueva?cliente_id=2', '/caja', '/caja/sesion/1', '/finanzas/cheques', '/finanzas/gastos',
  '/preventistas', '/preventistas/comisiones', '/reportes', '/reportes/ventas', '/reportes/iva_ventas', '/configuracion', '/usuarios', '/usuarios/roles', '/auditoria', '/catalogo/zonas', '/movil', '/chofer'];
module.exports = async function () {
  console.log('▶ Anchos de pantalla (sin scroll horizontal)');
  const { browser, page } = await h.abrir(360, 640);
  try {
    await h.login(page, 'admin', 'admin123');
    for (const w of [360, 390, 412]) {
      await page.setViewport({ width: w, height: 800, isMobile: true, hasTouch: true });
      const malos = [];
      for (const r of RUTAS) {
        await page.goto(h.BASE + r, { waitUntil: 'networkidle2' });
        const sw = await page.evaluate(() => document.documentElement.scrollWidth);
        if (sw > w + 1) { malos.push(r + ' (' + sw + 'px)'); }
      }
      h.verificar(!malos.length, w + ' px: ' + RUTAS.length + ' pantallas sin desborde' + (malos.length ? ' — desbordan: ' + malos.join(', ') : ''));
    }
    h.verificar(page.errores.length === 0, 'sin errores de JavaScript' + (page.errores.length ? ': ' + page.errores.join(' | ') : ''));
  } finally { await browser.close(); }
};
if (require.main === module) { module.exports().catch(e => { console.error(e.message); process.exit(1); }); }
