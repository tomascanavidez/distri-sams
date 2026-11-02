// Recorrido completo del preventista en un celular de 360 px, con y sin conexión
const h = require('./helpers');
module.exports = async function () {
  console.log('▶ Preventista (360×640)');
  const { browser, page } = await h.abrir(360, 640);
  try {
    await h.login(page, 'preventista2', 'preventista123');
    h.verificar(page.url().endsWith('/movil'), 'al ingresar entra directo a la app de preventa');
    await h.esperarSincronizado(page);
    await h.esperar(2500); // instalación del Service Worker
    h.verificar(await page.evaluate(() => !!navigator.serviceWorker.controller), 'el Service Worker controla la app (PWA)');
    await h.clickVisible(page, '.bottom-nav a', 'Mi ruta');
    await h.app(page, 'd.verTodos = true');
    await h.clickVisible(page, '.tarjeta .btn-primary', 'Pedido');
    await h.app(page, 'd.rubro = ""');
    await h.escribir(page, 'input[aria-label="Buscar producto"]', 'alfajor');
    await h.clickN(page, '.stepper .btn-primary', 0);
    await h.clickN(page, '.stepper .btn-primary', 0);
    h.verificar(await h.app(page, 'd.carrito.length') === 1, 'búsqueda tolerante y botones + agregan al carrito');
    await h.clickVisible(page, '.carrito-barra button');
    await h.clickVisible(page, '.hoja-inferior button', 'Confirmar pedido');
    await h.esperar(4000);
    h.verificar((await h.estado(page)).includes('Sincronizado'), 'con conexión el pedido se envía al instante');
    await h.clickVisible(page, '.hoja-inferior button', 'Siguiente cliente');

    // --- Corte de conexión ---
    await page.setOfflineMode(true);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => document.querySelector('.barra-estado'), { timeout: 15000 });
    await h.esperar(1500);
    h.verificar((await h.estado(page)).includes('Sin conexión'), 'la app abre sin señal y lo indica');
    await h.clickVisible(page, '.bottom-nav a', 'Mi ruta');
    await h.app(page, 'd.verTodos = true');
    await h.clickN(page, '.tarjeta .btn-primary', 1);
    await h.app(page, 'd.rubro = ""');
    await h.clickN(page, '.stepper .btn-primary', 0);
    await h.clickN(page, '.stepper .btn-primary', 1);
    await h.clickVisible(page, '.carrito-barra button');
    await h.clickVisible(page, '.hoja-inferior button', 'Confirmar pedido');
    await h.esperar(3500);
    await h.clickVisible(page, '.hoja-inferior button', 'Siguiente cliente');
    await h.clickN(page, '.tarjeta button.btn-outline-secondary', 2);
    await h.clickVisible(page, '.hoja-inferior button', 'Comercio cerrado');
    await h.clickVisible(page, '.hoja-inferior button', 'Registrar visita');
    await h.esperar(3000);
    h.verificar((await h.estado(page)).includes('2 operaciones pendientes'), 'sin señal: "2 operaciones pendientes de enviar"');
    const uuids = await h.app(page, 'd.cola.map(o => o.uuid)');

    // --- Vuelve la conexión ---
    await page.setOfflineMode(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await h.esperarSincronizado(page);
    h.verificar(await h.app(page, 'd.pendientes()') === 0, 'al volver la señal se sincroniza solo');
    const reenvio = await page.evaluate(async (u) => {
      const s = await DT.api('/api/movil/sesion');
      const r = await fetch(DT.url('/api/movil/sincronizar'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': s.csrf, Accept: 'application/json' }, body: JSON.stringify({ items: [{ uuid: u, tipo: 'pedido', creado: new Date().toISOString(), datos: { cliente_id: 2, items: [] } }] }) });
      return (await r.json()).resultados[0];
    }, uuids.find(Boolean));
    h.verificar(reenvio.repetido === true, 'reenviar la misma operación no la duplica (UUID)');
    h.verificar(page.errores.length === 0, 'sin errores de JavaScript' + (page.errores.length ? ': ' + page.errores.join(' | ') : ''));
  } finally { await browser.close(); }
};
if (require.main === module) { module.exports().catch(e => { console.error(e.message); process.exit(1); }); }
