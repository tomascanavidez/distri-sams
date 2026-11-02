// Utilidades comunes de las pruebas de navegador
const puppeteer = require('puppeteer-core');
exports.BASE = process.env.DT_URL || 'http://127.0.0.1:8088';
exports.CHROME = process.env.CHROME || '/usr/bin/google-chrome';
exports.esperar = (ms) => new Promise(r => setTimeout(r, ms));
exports.abrir = async (ancho = 360, alto = 640) => {
  const browser = await puppeteer.launch({ executablePath: exports.CHROME, headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.setViewport({ width: ancho, height: alto, isMobile: true, hasTouch: true });
  page.errores = [];
  page.on('pageerror', e => page.errores.push(e.message));
  page.on('dialog', d => d.accept());
  return { browser, page };
};
// Escribe disparando los eventos que usa Alpine
exports.escribir = async (page, sel, texto) => {
  await page.$eval(sel, (i, t) => { i.focus(); i.value = t; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true })); }, texto);
};
exports.clickVisible = async (page, sel, texto) => {
  await page.waitForFunction((sel, texto) => [...document.querySelectorAll(sel)].some(b => b.offsetParent && (!texto || b.textContent.includes(texto))), { timeout: 10000 }, sel, texto || '');
  await page.evaluate((sel, texto) => [...document.querySelectorAll(sel)].find(b => b.offsetParent && (!texto || b.textContent.includes(texto))).click(), sel, texto || '');
  await exports.esperar(250);
};
exports.clickN = async (page, sel, n) => {
  await page.waitForFunction((sel, n) => document.querySelectorAll(sel).length > n, { timeout: 10000 }, sel, n);
  await page.evaluate((sel, n) => document.querySelectorAll(sel)[n].click(), sel, n);
  await exports.esperar(200);
};
exports.login = async (page, u, c) => {
  await page.goto(exports.BASE + '/login', { waitUntil: 'networkidle2' });
  await exports.escribir(page, '#usuario', u);
  await exports.escribir(page, '#clave', c);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
};
exports.estado = (page) => page.$eval('.barra-estado', e => e.textContent.trim());
exports.esperarSincronizado = (page, ms = 30000) => page.waitForFunction(() => document.querySelector('.barra-estado') && document.querySelector('.barra-estado').textContent.includes('Sincronizado'), { timeout: ms });
exports.app = (page, expr) => page.evaluate(new Function('return (function (d) { return ' + expr + '; })(Alpine.$data(document.querySelector(".app-movil")))'));
exports.verificar = (cond, msg) => { if (!cond) { throw new Error('FALLÓ: ' + msg); } console.log('  ✔ ' + msg); };
