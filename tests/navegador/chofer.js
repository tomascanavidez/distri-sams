// Recorrido del chofer en un celular de 390 px: entrega parcial con cobro, y entregas sin conexión
const h = require('./helpers');
module.exports = async function () {
  console.log('▶ Chofer (390×844)');
  const { browser, page } = await h.abrir(390, 844);
  try {
    await h.login(page, 'chofer1', 'chofer123');
    h.verificar(page.url().endsWith('/chofer'), 'al ingresar entra directo a "Mis entregas de hoy"');
    await h.esperarSincronizado(page);
    await h.esperar(2500);
    const pendientesAntes = await h.app(page, 'd.paradas().filter(p => !d.hecha(p)).length');
    h.verificar(pendientesAntes >= 3, 'hay paradas pendientes (' + pendientesAntes + ')');
    await h.clickVisible(page, '.tarjeta .btn-primary', 'Entregar');
    await h.clickVisible(page, '.botonera-entrega .btn', 'Parcial');
    const total = await h.app(page, 'd.aCobrar()');
    await h.clickN(page, '.hoja-inferior .stepper .btn-outline-secondary', 0);
    const parcial = await h.app(page, 'd.aCobrar()');
    h.verificar(parcial < total, 'el importe a cobrar baja al entregar menos');
    h.verificar(Math.abs((await h.app(page, 'd.totalPagos()')) - parcial) < 0.01 || (await h.app(page, 'd.parada.cliente.cp')) !== 'contado', 'el cobro sugerido sigue a lo entregado');
    await h.clickVisible(page, '.hoja-inferior .opcion-grande', 'Faltante');
    await h.clickVisible(page, '.hoja-inferior button', 'Confirmar');
    await h.esperar(3000);
    h.verificar((await h.estado(page)).includes('Sincronizado'), 'entrega parcial enviada');

    await page.setOfflineMode(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));
    await h.clickVisible(page, '.botonera-entrega .btn', 'Entregado');
    await h.clickVisible(page, '.hoja-inferior button', 'Confirmar');
    await h.esperar(1200);
    await h.clickVisible(page, '.botonera-entrega .btn', 'Rechazado');
    await h.clickVisible(page, '.hoja-inferior .opcion-grande', 'Sin dinero');
    await h.clickVisible(page, '.hoja-inferior button', 'Confirmar');
    await h.esperar(1200);
    h.verificar((await h.estado(page)).includes('2 operaciones pendientes'), 'sin señal las entregas quedan en el celular');
    await page.setOfflineMode(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await h.esperarSincronizado(page);
    const pendientesDespues = await h.app(page, 'd.paradas().filter(p => !d.hecha(p)).length');
    h.verificar(pendientesDespues === pendientesAntes - 3, 'al volver la señal se registran en el servidor');
    h.verificar(page.errores.length === 0, 'sin errores de JavaScript' + (page.errores.length ? ': ' + page.errores.join(' | ') : ''));
  } finally { await browser.close(); }
};
if (require.main === module) { module.exports().catch(e => { console.error(e.message); process.exit(1); }); }
