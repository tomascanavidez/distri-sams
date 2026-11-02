<?php
/**
 * Rutas de la aplicación. El tercer parámetro es el permiso requerido ("modulo.accion";
 * varios separados por "|" = alcanza con uno). Sin permiso = sólo requiere sesión.
 */

declare(strict_types=1);

use App\Controllers as C;
use App\Core\Router;

$r = new Router();

// --- Autenticación ---
$r->publica('GET', '/login', [C\AuthController::class, 'formulario']);
$r->publica('POST', '/login', [C\AuthController::class, 'ingresar']);
$r->post('/logout', [C\AuthController::class, 'salir']);
$r->get('/perfil', [C\AuthController::class, 'perfil']);
$r->post('/perfil', [C\AuthController::class, 'cambiarClave']);
$r->publica('GET', '/manifest.webmanifest', [C\AuthController::class, 'manifest']);

// --- Panel ---
$r->get('/', [C\DashboardController::class, 'index']);

// --- API de búsqueda (autocompletado) ---
$r->get('/api/productos', [C\ApiController::class, 'productos'], 'productos.ver|ventas.crear|pedidos.crear|compras.crear|stock.crear');
$r->get('/api/producto/{id}', [C\ApiController::class, 'producto'], 'productos.ver|ventas.crear|pedidos.crear|compras.crear|stock.crear');
$r->get('/api/clientes', [C\ApiController::class, 'clientes'], 'clientes.ver|ventas.crear|pedidos.crear|cobranzas.crear');
$r->get('/api/cliente/{id}', [C\ApiController::class, 'cliente'], 'clientes.ver|ventas.crear|pedidos.crear|cobranzas.crear');
$r->get('/api/proveedores', [C\ApiController::class, 'proveedores'], 'proveedores.ver|compras.ver');
$r->get('/api/comprobantes', [C\ApiController::class, 'comprobantes'], 'ventas.ver');

// --- Catálogos simples (ABM genérico) ---
$r->get('/catalogo/{entidad}', [C\CatalogoController::class, 'index']);
$r->get('/catalogo/{entidad}/nuevo', [C\CatalogoController::class, 'nuevo']);
$r->post('/catalogo/{entidad}/nuevo', [C\CatalogoController::class, 'crear']);
$r->get('/catalogo/{entidad}/{id}/editar', [C\CatalogoController::class, 'editar']);
$r->post('/catalogo/{entidad}/{id}/editar', [C\CatalogoController::class, 'actualizar']);
$r->post('/catalogo/{entidad}/{id}/estado', [C\CatalogoController::class, 'alternarEstado']);

// --- Productos ---
$r->get('/productos', [C\ProductosController::class, 'index'], 'productos.ver');
$r->get('/productos/nuevo', [C\ProductosController::class, 'nuevo'], 'productos.crear');
$r->post('/productos/nuevo', [C\ProductosController::class, 'guardar'], 'productos.crear');
$r->get('/productos/actualizacion-masiva', [C\ProductosController::class, 'masiva'], 'productos.precios');
$r->post('/productos/actualizacion-masiva', [C\ProductosController::class, 'aplicarMasiva'], 'productos.precios');
$r->get('/productos/historial-precios', [C\ProductosController::class, 'historial'], 'productos.ver');
$r->get('/productos/importar', [C\ProductosController::class, 'importar'], 'productos.crear|productos.exportar');
$r->post('/productos/importar', [C\ProductosController::class, 'procesarImportacion'], 'productos.crear');
$r->get('/productos/exportar', [C\ProductosController::class, 'exportar'], 'productos.exportar');
$r->get('/productos/plantilla-csv', [C\ProductosController::class, 'plantilla'], 'productos.crear');
$r->get('/productos/{id}', [C\ProductosController::class, 'ver'], 'productos.ver');
$r->get('/productos/{id}/editar', [C\ProductosController::class, 'editar'], 'productos.editar');
$r->post('/productos/{id}/editar', [C\ProductosController::class, 'guardar'], 'productos.editar');
$r->post('/productos/{id}/precios', [C\ProductosController::class, 'guardarPrecios'], 'productos.precios');
$r->post('/productos/{id}/estado', [C\ProductosController::class, 'alternarEstado'], 'productos.anular');

// --- Stock ---
$r->get('/stock', [C\StockController::class, 'index'], 'stock.ver');
$r->get('/stock/exportar', [C\StockController::class, 'exportar'], 'stock.exportar');
$r->get('/stock/movimientos', [C\StockController::class, 'movimientos'], 'stock.ver');
$r->get('/stock/ajustes', [C\StockController::class, 'ajustes'], 'stock.ver');
$r->get('/stock/ajustes/nuevo', [C\StockController::class, 'nuevoAjuste'], 'stock.crear');
$r->post('/stock/ajustes/nuevo', [C\StockController::class, 'guardarAjuste'], 'stock.crear');
$r->get('/stock/ajustes/{id}', [C\StockController::class, 'verAjuste'], 'stock.ver');
$r->post('/stock/ajustes/{id}/anular', [C\StockController::class, 'anularAjuste'], 'stock.anular');
$r->get('/stock/transferencias', [C\StockController::class, 'transferencias'], 'stock.ver');
$r->get('/stock/transferencias/nueva', [C\StockController::class, 'nuevaTransferencia'], 'stock.crear');
$r->post('/stock/transferencias/nueva', [C\StockController::class, 'guardarTransferencia'], 'stock.crear');
$r->get('/stock/transferencias/{id}', [C\StockController::class, 'verTransferencia'], 'stock.ver');
$r->post('/stock/transferencias/{id}/anular', [C\StockController::class, 'anularTransferencia'], 'stock.anular');
$r->get('/stock/inventarios', [C\StockController::class, 'inventarios'], 'stock.ver');
$r->post('/stock/inventarios/nuevo', [C\StockController::class, 'crearInventario'], 'stock.crear');
$r->get('/stock/inventarios/{id}', [C\StockController::class, 'verInventario'], 'stock.ver');
$r->get('/stock/inventarios/{id}/planilla', [C\StockController::class, 'planillaInventario'], 'stock.ver');
$r->post('/stock/inventarios/{id}/conteo', [C\StockController::class, 'guardarConteo'], 'stock.crear');
$r->post('/stock/inventarios/{id}/aplicar', [C\StockController::class, 'aplicarInventario'], 'stock.crear');
$r->post('/stock/inventarios/{id}/anular', [C\StockController::class, 'anularInventario'], 'stock.anular');
$r->get('/stock/vencimientos', [C\StockController::class, 'vencimientos'], 'stock.ver');

// --- Clientes ---
$r->get('/clientes', [C\ClientesController::class, 'index'], 'clientes.ver');
$r->get('/clientes/nuevo', [C\ClientesController::class, 'nuevo'], 'clientes.crear');
$r->post('/clientes/nuevo', [C\ClientesController::class, 'guardar'], 'clientes.crear');
$r->get('/clientes/saldos', [C\ClientesController::class, 'saldos'], 'clientes.ver');
$r->get('/clientes/exportar', [C\ClientesController::class, 'exportar'], 'clientes.exportar');
$r->get('/clientes/{id}', [C\ClientesController::class, 'ver'], 'clientes.ver');
$r->get('/clientes/{id}/editar', [C\ClientesController::class, 'editar'], 'clientes.editar');
$r->post('/clientes/{id}/editar', [C\ClientesController::class, 'guardar'], 'clientes.editar');
$r->post('/clientes/{id}/estado', [C\ClientesController::class, 'cambiarEstado'], 'clientes.editar');
$r->get('/clientes/{id}/cuenta', [C\ClientesController::class, 'cuenta'], 'clientes.ver');
$r->post('/clientes/{id}/ajuste-cuenta', [C\ClientesController::class, 'ajusteCuenta'], 'clientes.editar');

// --- Proveedores ---
$r->get('/proveedores', [C\ProveedoresController::class, 'index'], 'proveedores.ver');
$r->get('/proveedores/nuevo', [C\ProveedoresController::class, 'nuevo'], 'proveedores.crear');
$r->post('/proveedores/nuevo', [C\ProveedoresController::class, 'guardar'], 'proveedores.crear');
$r->get('/proveedores/{id}', [C\ProveedoresController::class, 'ver'], 'proveedores.ver');
$r->get('/proveedores/{id}/editar', [C\ProveedoresController::class, 'editar'], 'proveedores.editar');
$r->post('/proveedores/{id}/editar', [C\ProveedoresController::class, 'guardar'], 'proveedores.editar');
$r->post('/proveedores/{id}/estado', [C\ProveedoresController::class, 'alternarEstado'], 'proveedores.anular');

// --- Compras ---
$r->get('/compras', [C\ComprasController::class, 'index'], 'compras.ver');
$r->get('/compras/nueva', [C\ComprasController::class, 'nueva'], 'compras.crear');
$r->post('/compras/guardar', [C\ComprasController::class, 'guardar'], 'compras.crear');
$r->get('/compras/sugerencia', [C\ComprasController::class, 'sugerencia'], 'compras.crear');
$r->get('/compras/recepciones', [C\ComprasController::class, 'recepciones'], 'compras.ver');
$r->get('/compras/recepciones/nueva', [C\ComprasController::class, 'nuevaRecepcion'], 'compras.crear');
$r->post('/compras/recepciones/guardar', [C\ComprasController::class, 'guardarRecepcion'], 'compras.crear');
$r->get('/compras/recepciones/{id}', [C\ComprasController::class, 'verRecepcion'], 'compras.ver');
$r->post('/compras/recepciones/{id}/anular', [C\ComprasController::class, 'anularRecepcion'], 'compras.anular');
$r->get('/compras/facturas', [C\ComprasController::class, 'facturas'], 'compras.ver');
$r->get('/compras/facturas/nueva', [C\ComprasController::class, 'nuevaFactura'], 'compras.crear');
$r->post('/compras/facturas/nueva', [C\ComprasController::class, 'guardarFactura'], 'compras.crear');
$r->post('/compras/facturas/{id}/anular', [C\ComprasController::class, 'anularFactura'], 'compras.anular');
$r->get('/compras/pagos', [C\ComprasController::class, 'pagos'], 'compras.ver');
$r->get('/compras/pagos/nuevo', [C\ComprasController::class, 'nuevoPago'], 'compras.crear');
$r->post('/compras/pagos/nuevo', [C\ComprasController::class, 'guardarPago'], 'compras.crear');
$r->get('/compras/pagos/{id}', [C\ComprasController::class, 'verPago'], 'compras.ver');
$r->post('/compras/pagos/{id}/anular', [C\ComprasController::class, 'anularPago'], 'compras.anular');
$r->get('/compras/devoluciones', [C\ComprasController::class, 'devoluciones'], 'compras.ver');
$r->get('/compras/devoluciones/nueva', [C\ComprasController::class, 'nuevaDevolucion'], 'compras.crear');
$r->post('/compras/devoluciones/guardar', [C\ComprasController::class, 'guardarDevolucion'], 'compras.crear');
$r->post('/compras/devoluciones/{id}/anular', [C\ComprasController::class, 'anularDevolucion'], 'compras.anular');
$r->get('/compras/{id}', [C\ComprasController::class, 'ver'], 'compras.ver');
$r->get('/compras/{id}/editar', [C\ComprasController::class, 'editar'], 'compras.editar');
$r->get('/compras/{id}/imprimir', [C\ComprasController::class, 'imprimir'], 'compras.ver');
$r->post('/compras/{id}/estado', [C\ComprasController::class, 'cambiarEstado'], 'compras.editar|compras.anular');

// --- Pedidos ---
$r->get('/pedidos', [C\PedidosController::class, 'index'], 'pedidos.ver');
$r->get('/pedidos/nuevo', [C\PedidosController::class, 'nuevo'], 'pedidos.crear');
$r->post('/pedidos/guardar', [C\PedidosController::class, 'guardar'], 'pedidos.crear|pedidos.editar');
$r->get('/pedidos/preparacion', [C\PedidosController::class, 'preparacion'], 'pedidos.editar');
$r->post('/pedidos/preparacion', [C\PedidosController::class, 'marcarPreparacion'], 'pedidos.editar');
$r->get('/pedidos/picking', [C\PedidosController::class, 'picking'], 'pedidos.ver');
$r->get('/pedidos/exportar', [C\PedidosController::class, 'exportar'], 'pedidos.exportar');
$r->get('/pedidos/ultimo/{clienteId}', [C\PedidosController::class, 'ultimo'], 'pedidos.crear');
$r->get('/pedidos/{id}', [C\PedidosController::class, 'ver'], 'pedidos.ver');
$r->get('/pedidos/{id}/editar', [C\PedidosController::class, 'editar'], 'pedidos.editar');
$r->get('/pedidos/{id}/imprimir', [C\PedidosController::class, 'imprimir'], 'pedidos.ver');
$r->post('/pedidos/{id}/confirmar', [C\PedidosController::class, 'confirmar'], 'pedidos.crear|pedidos.editar');
$r->post('/pedidos/{id}/estado', [C\PedidosController::class, 'cambiarEstado'], 'pedidos.editar');
$r->post('/pedidos/{id}/anular', [C\PedidosController::class, 'anular'], 'pedidos.anular');
$r->post('/pedidos/{id}/facturar', [C\PedidosController::class, 'facturar'], 'ventas.crear');

// --- Ventas ---
$r->get('/ventas', [C\VentasController::class, 'index'], 'ventas.ver');
$r->get('/ventas/pos', [C\VentasController::class, 'pos'], 'ventas.crear');
$r->get('/ventas/nueva', [C\VentasController::class, 'nueva'], 'ventas.crear');
$r->post('/ventas/emitir', [C\VentasController::class, 'emitir'], 'ventas.crear');
$r->get('/ventas/devolucion', [C\VentasController::class, 'devolucion'], 'ventas.crear');
$r->get('/ventas/exportar', [C\VentasController::class, 'exportar'], 'ventas.exportar');
$r->get('/ventas/credito/{clienteId}', [C\VentasController::class, 'credito'], 'ventas.crear|pedidos.crear');
$r->get('/ventas/{id}', [C\VentasController::class, 'ver'], 'ventas.ver');
$r->get('/ventas/{id}/imprimir', [C\VentasController::class, 'imprimir'], 'ventas.ver');
$r->get('/ventas/{id}/pdf', [C\VentasController::class, 'pdf'], 'ventas.ver');
$r->post('/ventas/{id}/anular', [C\VentasController::class, 'anular'], 'ventas.anular');
$r->post('/ventas/{id}/convertir', [C\VentasController::class, 'convertirPresupuesto'], 'ventas.crear');

// --- Reparto ---
$r->get('/reparto', [C\RepartoController::class, 'index'], 'reparto.ver');
$r->get('/reparto/nueva', [C\RepartoController::class, 'nueva'], 'reparto.crear');
$r->post('/reparto/nueva', [C\RepartoController::class, 'crear'], 'reparto.crear');
$r->get('/reparto/{id}', [C\RepartoController::class, 'ver'], 'reparto.ver');
$r->get('/reparto/{id}/imprimir', [C\RepartoController::class, 'imprimir'], 'reparto.ver');
$r->get('/reparto/{id}/carga', [C\RepartoController::class, 'carga'], 'reparto.ver');
$r->post('/reparto/{id}/paradas', [C\RepartoController::class, 'paradas'], 'reparto.editar');
$r->post('/reparto/{id}/iniciar', [C\RepartoController::class, 'iniciar'], 'reparto.editar');
$r->post('/reparto/{id}/anular', [C\RepartoController::class, 'anular'], 'reparto.anular');
$r->post('/reparto/parada/{id}/entrega', [C\RepartoController::class, 'entrega'], 'reparto.editar');
$r->get('/reparto/{id}/rendicion', [C\RepartoController::class, 'rendicion'], 'reparto.editar');
$r->post('/reparto/{id}/rendicion', [C\RepartoController::class, 'rendir'], 'reparto.editar');

// --- Cobranzas ---
$r->get('/cobranzas', [C\CobranzasController::class, 'index'], 'cobranzas.ver');
$r->get('/cobranzas/nueva', [C\CobranzasController::class, 'nueva'], 'cobranzas.crear');
$r->post('/cobranzas/nueva', [C\CobranzasController::class, 'guardar'], 'cobranzas.crear');
$r->get('/cobranzas/pendientes/{clienteId}', [C\CobranzasController::class, 'pendientes'], 'cobranzas.crear');
$r->get('/cobranzas/exportar', [C\CobranzasController::class, 'exportar'], 'cobranzas.exportar');
$r->get('/cobranzas/{id}', [C\CobranzasController::class, 'ver'], 'cobranzas.ver');
$r->get('/cobranzas/{id}/imprimir', [C\CobranzasController::class, 'imprimir'], 'cobranzas.ver');
$r->post('/cobranzas/{id}/anular', [C\CobranzasController::class, 'anular'], 'cobranzas.anular');

// --- Caja y finanzas ---
$r->get('/caja', [C\CajaController::class, 'index'], 'caja.ver');
$r->post('/caja/abrir', [C\CajaController::class, 'abrir'], 'caja.crear');
$r->post('/caja/movimiento', [C\CajaController::class, 'movimiento'], 'caja.crear');
$r->get('/caja/sesion/{id}', [C\CajaController::class, 'sesion'], 'caja.ver');
$r->post('/caja/sesion/{id}/cerrar', [C\CajaController::class, 'cerrar'], 'caja.crear');
$r->get('/finanzas/bancos', [C\FinanzasController::class, 'bancos'], 'finanzas.ver');
$r->get('/finanzas/bancos/{id}', [C\FinanzasController::class, 'cuenta'], 'finanzas.ver');
$r->post('/finanzas/bancos/movimiento', [C\FinanzasController::class, 'movimientoBanco'], 'finanzas.crear');
$r->get('/finanzas/cheques', [C\FinanzasController::class, 'cheques'], 'finanzas.ver');
$r->post('/finanzas/cheques/{id}/depositar', [C\FinanzasController::class, 'depositarCheque'], 'finanzas.crear');
$r->post('/finanzas/cheques/{id}/rechazar', [C\FinanzasController::class, 'rechazarCheque'], 'finanzas.anular');
$r->get('/finanzas/gastos', [C\FinanzasController::class, 'gastos'], 'finanzas.ver');
$r->post('/finanzas/gastos/nuevo', [C\FinanzasController::class, 'guardarGasto'], 'finanzas.crear');
$r->post('/finanzas/gastos/{id}/anular', [C\FinanzasController::class, 'anularGasto'], 'finanzas.anular');

// --- Preventistas ---
$r->get('/preventistas', [C\PreventistasController::class, 'index'], 'preventistas.ver');
$r->get('/preventistas/nuevo', [C\PreventistasController::class, 'nuevo'], 'preventistas.crear');
$r->post('/preventistas/nuevo', [C\PreventistasController::class, 'guardar'], 'preventistas.crear');
$r->get('/preventistas/visitas', [C\PreventistasController::class, 'visitas'], 'preventistas.ver');
$r->get('/preventistas/comisiones', [C\PreventistasController::class, 'comisiones'], 'preventistas.ver');
$r->post('/preventistas/comisiones/liquidar', [C\PreventistasController::class, 'liquidar'], 'preventistas.crear');
$r->get('/preventistas/comisiones/{id}', [C\PreventistasController::class, 'verLiquidacion'], 'preventistas.ver');
$r->post('/preventistas/comisiones/{id}/estado', [C\PreventistasController::class, 'estadoLiquidacion'], 'preventistas.editar|preventistas.anular');
$r->get('/preventistas/{id}/editar', [C\PreventistasController::class, 'editar'], 'preventistas.editar');
$r->post('/preventistas/{id}/editar', [C\PreventistasController::class, 'guardar'], 'preventistas.editar');

// --- App móvil del preventista (PWA offline) ---
$r->get('/movil', [C\MovilController::class, 'app'], 'movil_preventa.ver');
$r->get('/api/movil/sesion', [C\MovilController::class, 'sesion']);
$r->get('/api/movil/datos', [C\MovilController::class, 'datos'], 'movil_preventa.ver');
$r->post('/api/movil/sincronizar', [C\MovilController::class, 'sincronizar'], 'movil_preventa.ver');
$r->get('/api/movil/pedidos', [C\MovilController::class, 'misPedidos'], 'movil_preventa.ver');

// --- App móvil del chofer (PWA offline) ---
$r->get('/chofer', [C\ChoferController::class, 'app'], 'movil_reparto.ver');
$r->get('/api/chofer/datos', [C\ChoferController::class, 'datos'], 'movil_reparto.ver');
$r->post('/api/chofer/sincronizar', [C\ChoferController::class, 'sincronizar'], 'movil_reparto.ver');

// --- Reportes ---
$r->get('/reportes', [C\ReportesController::class, 'index'], 'reportes.ver');
$r->get('/reportes/{clave}', [C\ReportesController::class, 'ver'], 'reportes.ver');

// --- Administración ---
$r->get('/configuracion', [C\ConfiguracionController::class, 'index'], 'configuracion.ver');
$r->post('/configuracion', [C\ConfiguracionController::class, 'guardar'], 'configuracion.editar');
$r->get('/configuracion/migraciones', [C\ConfiguracionController::class, 'migraciones'], 'configuracion.editar');
$r->post('/configuracion/migraciones', [C\ConfiguracionController::class, 'migrar'], 'configuracion.editar');
$r->get('/usuarios', [C\UsuariosController::class, 'index'], 'usuarios.ver');
$r->get('/usuarios/nuevo', [C\UsuariosController::class, 'nuevo'], 'usuarios.crear');
$r->post('/usuarios/nuevo', [C\UsuariosController::class, 'guardar'], 'usuarios.crear');
$r->get('/usuarios/roles', [C\UsuariosController::class, 'roles'], 'usuarios.ver');
$r->post('/usuarios/roles', [C\UsuariosController::class, 'guardarRoles'], 'usuarios.editar');
$r->post('/usuarios/roles/nuevo', [C\UsuariosController::class, 'crearRol'], 'usuarios.crear');
$r->get('/usuarios/{id}/editar', [C\UsuariosController::class, 'editar'], 'usuarios.editar');
$r->post('/usuarios/{id}/editar', [C\UsuariosController::class, 'guardar'], 'usuarios.editar');
$r->post('/usuarios/{id}/estado', [C\UsuariosController::class, 'alternarEstado'], 'usuarios.anular');
$r->get('/auditoria', [C\AuditoriaController::class, 'index'], 'auditoria.ver');
$r->get('/backups', [C\BackupController::class, 'index'], 'backups.ver');
$r->post('/backups/generar', [C\BackupController::class, 'generar'], 'backups.crear');
$r->get('/backups/descargar/{nombre}', [C\BackupController::class, 'descargar'], 'backups.ver');

return $r;
