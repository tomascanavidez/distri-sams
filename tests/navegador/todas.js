// Corre todas las pruebas de navegador. Requiere la base con datos demo (php tools/instalar_dev.php --demo)
(async () => {
  let fallas = 0;
  for (const p of ['./anchos', './preventista', './preventista_alta', './chofer']) {
    try { await require(p)(); } catch (e) { fallas++; console.error('  ✘ ' + e.message); }
  }
  console.log(fallas ? `\n${fallas} prueba(s) fallaron.` : '\nTodas las pruebas de navegador pasaron.');
  process.exit(fallas ? 1 : 0);
})();
