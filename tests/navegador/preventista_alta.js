// Alta de cliente desde el celular (sin señal), pedido para ese cliente y lista de precios
const h = require('./helpers');
module.exports = async function () {
  console.log('▶ Preventista: alta de cliente y precios (360×640)');
  const { browser, page } = await h.abrir(360, 640);
  try {
    await h.login(page, 'preventista1', 'preventista123');
    await h.esperarSincronizado(page);
    await h.esperar(2500);
    const nombre = 'Kiosco Prueba ' + Date.now();

    await page.setOfflineMode(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));
    await h.clickVisible(page, '.bottom-nav a', 'Clientes');
    await h.clickVisible(page, '.buscador-movil button', 'Nuevo');
    await h.escribir(page, '#nc-nombre', nombre);
    await h.escribir(page, '#nc-dir', 'Av. Siempre Viva 742');
    await h.escribir(page, '#nc-doc', '20-12345678-0');
    await h.clickVisible(page, 'button', 'Guardar y tomar pedido');
    await h.esperar(800);
    h.verificar(await h.app(page, 'd.vista') === 'pedido' && await h.app(page, 'd.cliente.nuevo') === true, 'sin señal se da de alta el cliente y se abre su pedido');
    await h.app(page, 'd.rubro = ""');
    await h.clickN(page, '.stepper .btn-primary', 0);
    await h.clickVisible(page, '.carrito-barra button');
    await h.clickVisible(page, '.hoja-inferior button', 'Confirmar pedido');
    await h.esperar(2000);
    await h.clickVisible(page, '.hoja-inferior button', 'Ir al inicio');
    h.verificar(await h.app(page, 'd.pendientes()') === 2, 'quedan 2 operaciones pendientes (cliente + pedido)');
    h.verificar(await h.app(page, `d.clientes().some(c => c.n === ${JSON.stringify(nombre)})`), 'el cliente nuevo aparece en la lista antes de enviarse');

    await page.setOfflineMode(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await h.esperarSincronizado(page);
    await h.esperar(2500);
    h.verificar(await h.app(page, 'd.pendientes()') === 0, 'al volver la señal se envían el cliente y el pedido');
    await page.waitForFunction((n) => Alpine.$data(document.querySelector(".app-movil")).datos.clientes.some(c => c.n === n), { timeout: 15000 }, nombre).catch(() => {});
    const real = await h.app(page, `d.datos.clientes.filter(c => c.n === ${JSON.stringify(nombre)}).map(c => ({ i: c.i, peds: c.peds.length }))[0]`);
    h.verificar(real && real.i > 0, 'el cliente queda registrado en el sistema con su número');
    h.verificar(real.peds === 1, 'el pedido quedó asociado al cliente nuevo');
    h.verificar(await h.app(page, `d.clientes().filter(c => c.n === ${JSON.stringify(nombre)}).length`) === 1, 'el cliente no se duplica en la lista');

    await h.clickVisible(page, '.bottom-nav a', 'Inicio');
    await h.clickVisible(page, 'button', 'Precios');
    await h.escribir(page, 'input[aria-label="Buscar producto"]', 'alfajor');
    await h.esperar(400);
    const filas = await page.$$eval('.tarjeta table tbody tr', t => t.filter(f => f.offsetParent).length);
    const listas = await h.app(page, 'd.listasOrdenadas().length');
    h.verificar(filas >= listas && listas > 1, 'la lista de precios muestra el precio de cada lista (' + listas + ' listas)');
    const ancho = await page.evaluate(() => document.documentElement.scrollWidth);
    h.verificar(ancho <= 360, 'sin scroll horizontal a 360 px');
    h.verificar(page.errores.length === 0, 'sin errores de JavaScript' + (page.errores.length ? ': ' + page.errores.join(' | ') : ''));
  } finally { await browser.close(); }
};
if (require.main === module) { module.exports().catch(e => { console.error(e.message); process.exit(1); }); }
