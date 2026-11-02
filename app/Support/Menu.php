<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Menú lateral por módulos. Cada ítem se muestra sólo si el usuario tiene el permiso.
 */
final class Menu
{
    public static function secciones(): array
    {
        return [
            ['Inicio', 'speedometer2', '/', 'dashboard.ver', []],
            ['Ventas', 'receipt', '/ventas', 'ventas.ver|ventas.crear', [
                ['Mostrador (POS)', '/ventas/pos', 'ventas.crear'],
                ['Nueva venta', '/ventas/nueva', 'ventas.crear'],
                ['Comprobantes', '/ventas', 'ventas.ver'],
                ['Presupuestos', '/ventas?tipo=PR', 'ventas.ver'],
                ['Devolución / NC', '/ventas/devolucion', 'ventas.crear'],
            ]],
            ['Pedidos', 'cart3', '/pedidos', 'pedidos.ver', [
                ['Pedidos', '/pedidos', 'pedidos.ver'],
                ['Nuevo pedido', '/pedidos/nuevo', 'pedidos.crear'],
                ['Preparación (picking)', '/pedidos/preparacion', 'pedidos.editar'],
                ['A revisar (app)', '/pedidos?revision=1', 'pedidos.editar'],
            ]],
            ['Reparto', 'truck', '/reparto', 'reparto.ver', [
                ['Hojas de ruta', '/reparto', 'reparto.ver'],
                ['Nueva hoja de ruta', '/reparto/nueva', 'reparto.crear'],
                ['Vehículos', '/catalogo/vehiculos', 'reparto.ver'],
                ['Choferes', '/catalogo/choferes', 'reparto.ver'],
            ]],
            ['Clientes', 'shop', '/clientes', 'clientes.ver', [
                ['Clientes', '/clientes', 'clientes.ver'],
                ['Nuevo cliente', '/clientes/nuevo', 'clientes.crear'],
                ['Saldos y deudas', '/clientes/saldos', 'clientes.ver'],
                ['Zonas / rutas', '/catalogo/zonas', 'clientes.ver'],
            ]],
            ['Cobranzas', 'cash-coin', '/cobranzas', 'cobranzas.ver', [
                ['Recibos', '/cobranzas', 'cobranzas.ver'],
                ['Nueva cobranza', '/cobranzas/nueva', 'cobranzas.crear'],
            ]],
            ['Productos', 'box-seam', '/productos', 'productos.ver', [
                ['Productos', '/productos', 'productos.ver'],
                ['Nuevo producto', '/productos/nuevo', 'productos.crear'],
                ['Actualizar precios', '/productos/actualizacion-masiva', 'productos.precios'],
                ['Historial de precios', '/productos/historial-precios', 'productos.ver'],
                ['Promociones', '/catalogo/promociones', 'productos.ver'],
                ['Importar / exportar', '/productos/importar', 'productos.crear|productos.exportar'],
                ['Rubros', '/catalogo/rubros', 'productos.ver'],
                ['Marcas', '/catalogo/marcas', 'productos.ver'],
                ['Listas de precios', '/catalogo/listas', 'productos.ver'],
            ]],
            ['Stock', 'boxes', '/stock', 'stock.ver', [
                ['Existencias', '/stock', 'stock.ver'],
                ['Movimientos (kardex)', '/stock/movimientos', 'stock.ver'],
                ['Ajustes', '/stock/ajustes', 'stock.ver'],
                ['Transferencias', '/stock/transferencias', 'stock.ver'],
                ['Inventario físico', '/stock/inventarios', 'stock.ver'],
                ['Vencimientos', '/stock/vencimientos', 'stock.ver'],
                ['Depósitos', '/catalogo/depositos', 'stock.ver'],
            ]],
            ['Compras', 'bag-check', '/compras', 'compras.ver|proveedores.ver', [
                ['Órdenes de compra', '/compras', 'compras.ver'],
                ['Sugerencia de compra', '/compras/sugerencia', 'compras.crear'],
                ['Recepciones', '/compras/recepciones', 'compras.ver'],
                ['Facturas de proveedor', '/compras/facturas', 'compras.ver'],
                ['Pagos a proveedores', '/compras/pagos', 'compras.ver'],
                ['Devoluciones', '/compras/devoluciones', 'compras.ver'],
                ['Proveedores', '/proveedores', 'proveedores.ver'],
            ]],
            ['Finanzas', 'bank', '/caja', 'caja.ver|finanzas.ver', [
                ['Caja', '/caja', 'caja.ver'],
                ['Bancos y billeteras', '/finanzas/bancos', 'finanzas.ver'],
                ['Cheques', '/finanzas/cheques', 'finanzas.ver'],
                ['Gastos', '/finanzas/gastos', 'finanzas.ver'],
                ['Cajas', '/catalogo/cajas', 'caja.ver'],
                ['Categorías de gastos', '/catalogo/gastos_categorias', 'finanzas.ver'],
            ]],
            ['Preventistas', 'person-badge', '/preventistas', 'preventistas.ver', [
                ['Preventistas', '/preventistas', 'preventistas.ver'],
                ['Visitas', '/preventistas/visitas', 'preventistas.ver'],
                ['Comisiones', '/preventistas/comisiones', 'preventistas.ver'],
            ]],
            ['Reportes', 'bar-chart-line', '/reportes', 'reportes.ver', []],
            ['App celular', 'phone', '/movil', 'movil_preventa.ver|movil_reparto.ver', [
                ['Preventista', '/movil', 'movil_preventa.ver'],
                ['Chofer', '/chofer', 'movil_reparto.ver'],
            ]],
            ['Administración', 'gear', '/configuracion', 'configuracion.ver|usuarios.ver|auditoria.ver|backups.ver', [
                ['Empresa y parámetros', '/configuracion', 'configuracion.ver'],
                ['Puntos de venta', '/catalogo/puntos_venta', 'configuracion.ver'],
                ['Usuarios', '/usuarios', 'usuarios.ver'],
                ['Roles y permisos', '/usuarios/roles', 'usuarios.ver'],
                ['Auditoría', '/auditoria', 'auditoria.ver'],
                ['Copias de seguridad', '/backups', 'backups.ver'],
                ['Actualizaciones de base', '/configuracion/migraciones', 'configuracion.editar'],
            ]],
        ];
    }
}
