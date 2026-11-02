<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\DB;
use App\Services\Facturacion\FacturacionFactory;

/**
 * Definición de los catálogos simples que maneja el ABM genérico (CatalogoController).
 * Cada campo: [etiqueta, tipo, reglas de validación, opciones extra]
 * Tipos: texto, decimal, entero, fecha, select, dias, check, producto, textarea
 */
final class Catalogos
{
    public static function definicion(string $entidad): ?array
    {
        $defs = [
            'rubros' => [
                'titulo' => 'Rubros', 'singular' => 'rubro', 'tabla' => 'rubros', 'modulo' => 'productos', 'orden' => 'nombre',
                'campos' => ['nombre' => ['Nombre', 'texto', 'requerido|max:80|unico:rubros,nombre']],
                'columnas' => ['nombre' => 'Nombre'],
                'contar' => ['productos' => 'SELECT COUNT(*) FROM productos WHERE rubro_id = ?'],
            ],
            'marcas' => [
                'titulo' => 'Marcas', 'singular' => 'marca', 'tabla' => 'marcas', 'modulo' => 'productos', 'orden' => 'nombre',
                'campos' => ['nombre' => ['Nombre', 'texto', 'requerido|max:80|unico:marcas,nombre']],
                'columnas' => ['nombre' => 'Nombre'],
                'contar' => ['productos' => 'SELECT COUNT(*) FROM productos WHERE marca_id = ?'],
            ],
            'listas' => [
                'titulo' => 'Listas de precios', 'singular' => 'lista de precios', 'tabla' => 'listas_precios', 'modulo' => 'productos',
                'permiso_editar' => 'productos.precios', 'orden' => 'orden, id',
                'campos' => [
                    'nombre' => ['Nombre', 'texto', 'requerido|max:60'],
                    'margen_porc' => ['Margen sobre costo (%)', 'decimal', 'requerido|numerico', ['ayuda' => 'Margen por defecto de los productos que no tengan uno propio.']],
                    'redondeo' => ['Redondeo del precio final', 'select', 'requerido', ['opciones' => ['0.00' => 'Sin redondeo (centavos)', '0.10' => 'A $ 0,10', '0.50' => 'A $ 0,50', '1.00' => 'A $ 1', '5.00' => 'A $ 5', '10.00' => 'A $ 10', '50.00' => 'A $ 50', '100.00' => 'A $ 100']]],
                    'orden' => ['Orden', 'entero', 'entero'],
                ],
                'columnas' => ['nombre' => 'Nombre', 'margen_porc' => 'Margen %', 'redondeo' => 'Redondeo', 'orden' => 'Orden'],
                'contar' => ['clientes' => 'SELECT COUNT(*) FROM clientes WHERE lista_precio_id = ?'],
                'despues' => 'recalcularLista',
            ],
            'depositos' => [
                'titulo' => 'Depósitos', 'singular' => 'depósito', 'tabla' => 'depositos', 'modulo' => 'stock', 'orden' => 'tipo, nombre',
                'campos' => ['nombre' => ['Nombre', 'texto', 'requerido|max:60']],
                'columnas' => ['nombre' => 'Nombre', 'tipo' => 'Tipo'],
                'fijos' => ['tipo' => 'deposito'],
                'ayuda' => 'Los depósitos de los camiones se crean solos al dar de alta un vehículo.',
            ],
            'zonas' => [
                'titulo' => 'Zonas / rutas', 'singular' => 'zona', 'tabla' => 'zonas', 'modulo' => 'clientes', 'orden' => 'nombre',
                'campos' => [
                    'nombre' => ['Nombre', 'texto', 'requerido|max:80'],
                    'descripcion' => ['Barrios / descripción', 'texto', 'max:255'],
                    'dias_visita' => ['Días de visita del preventista', 'dias', ''],
                    'dias_entrega' => ['Días de entrega', 'dias', ''],
                    'preventista_id' => ['Preventista', 'select', '', ['opciones' => fn () => DB::pares('SELECT id, nombre FROM preventistas WHERE activo = 1 ORDER BY nombre'), 'vacio' => 'Sin asignar']],
                ],
                'columnas' => ['nombre' => 'Nombre', 'descripcion' => 'Descripción', 'dias_visita' => 'Visita', 'dias_entrega' => 'Entrega', 'preventista' => 'Preventista'],
                'consulta' => 'SELECT z.*, p.nombre AS preventista FROM zonas z LEFT JOIN preventistas p ON p.id = z.preventista_id',
                'contar' => ['clientes' => 'SELECT COUNT(*) FROM clientes WHERE zona_id = ?'],
            ],
            'vehiculos' => [
                'titulo' => 'Vehículos', 'singular' => 'vehículo', 'tabla' => 'vehiculos', 'modulo' => 'reparto', 'orden' => 'patente',
                'campos' => [
                    'patente' => ['Patente', 'texto', 'requerido|max:10'],
                    'descripcion' => ['Descripción', 'texto', 'max:100', ['placeholder' => 'Ej.: Fiat Fiorino blanca']],
                    'capacidad_kg' => ['Capacidad (kg)', 'decimal', 'numerico'],
                    'capacidad_bultos' => ['Capacidad (bultos)', 'entero', 'entero'],
                ],
                'columnas' => ['patente' => 'Patente', 'descripcion' => 'Descripción', 'capacidad_kg' => 'Kg', 'capacidad_bultos' => 'Bultos'],
                'despues' => 'depositoVehiculo',
            ],
            'choferes' => [
                'titulo' => 'Choferes / repartidores', 'singular' => 'chofer', 'tabla' => 'choferes', 'modulo' => 'reparto', 'orden' => 'nombre',
                'campos' => [
                    'nombre' => ['Nombre y apellido', 'texto', 'requerido|max:100'],
                    'dni' => ['DNI', 'texto', 'max:12', ['inputmode' => 'numeric']],
                    'telefono' => ['Teléfono', 'texto', 'max:50', ['tipo' => 'tel']],
                    'licencia_vencimiento' => ['Vencimiento de licencia', 'fecha', 'fecha'],
                    'usuario_id' => ['Usuario del sistema (para la app del chofer)', 'select', '', ['opciones' => fn () => DB::pares("SELECT u.id, CONCAT(u.nombre, ' (', u.usuario, ')') FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.activo = 1 ORDER BY r.slug = 'chofer' DESC, u.nombre"), 'vacio' => 'Sin usuario']],
                ],
                'columnas' => ['nombre' => 'Nombre', 'dni' => 'DNI', 'telefono' => 'Teléfono', 'licencia_vencimiento' => 'Licencia vence', 'usuario' => 'Usuario'],
                'consulta' => 'SELECT ch.*, u.usuario FROM choferes ch LEFT JOIN usuarios u ON u.id = ch.usuario_id',
            ],
            'cajas' => [
                'titulo' => 'Cajas', 'singular' => 'caja', 'tabla' => 'cajas', 'modulo' => 'caja', 'permiso_editar' => 'configuracion.editar|caja.crear', 'orden' => 'nombre',
                'campos' => ['nombre' => ['Nombre', 'texto', 'requerido|max:60']],
                'columnas' => ['nombre' => 'Nombre'],
            ],
            'gastos_categorias' => [
                'titulo' => 'Categorías de gastos', 'singular' => 'categoría', 'tabla' => 'gastos_categorias', 'modulo' => 'finanzas', 'permiso_editar' => 'finanzas.crear', 'orden' => 'nombre',
                'campos' => ['nombre' => ['Nombre', 'texto', 'requerido|max:80|unico:gastos_categorias,nombre']],
                'columnas' => ['nombre' => 'Nombre'],
            ],
            'cuentas_bancarias' => [
                'titulo' => 'Cuentas bancarias y billeteras', 'singular' => 'cuenta', 'tabla' => 'cuentas_bancarias', 'modulo' => 'finanzas', 'permiso_editar' => 'finanzas.crear', 'orden' => 'nombre',
                'campos' => [
                    'nombre' => ['Nombre', 'texto', 'requerido|max:80'],
                    'tipo' => ['Tipo', 'select', 'requerido|en:banco,billetera,tarjeta', ['opciones' => ['banco' => 'Cuenta bancaria', 'billetera' => 'Billetera virtual (Mercado Pago, etc.)', 'tarjeta' => 'Cupones de tarjeta a acreditar']]],
                    'banco' => ['Banco / entidad', 'texto', 'max:80'],
                    'numero' => ['Número de cuenta', 'texto', 'max:40'],
                    'cbu_alias' => ['CBU / CVU / alias', 'texto', 'max:60'],
                    'saldo_inicial' => ['Saldo inicial', 'decimal', 'numerico'],
                ],
                'columnas' => ['nombre' => 'Nombre', 'tipo' => 'Tipo', 'banco' => 'Entidad', 'cbu_alias' => 'CBU / alias'],
            ],
            'puntos_venta' => [
                'titulo' => 'Puntos de venta', 'singular' => 'punto de venta', 'tabla' => 'puntos_venta', 'modulo' => 'configuracion', 'orden' => 'numero',
                'campos' => [
                    'numero' => ['Número', 'entero', 'requerido|entero'],
                    'nombre' => ['Nombre', 'texto', 'requerido|max:60'],
                    'proveedor_facturacion' => ['Facturación', 'select', 'requerido', ['opciones' => fn () => FacturacionFactory::disponibles()]],
                ],
                'columnas' => ['numero' => 'Número', 'nombre' => 'Nombre', 'proveedor_facturacion' => 'Facturación'],
                'ayuda' => 'La numeración de cada tipo de comprobante se lleva por punto de venta. Se puede ajustar en Administración › Empresa y parámetros.',
            ],
            'promociones' => [
                'titulo' => 'Promociones / bonificaciones', 'singular' => 'promoción', 'tabla' => 'promociones', 'modulo' => 'productos', 'permiso_editar' => 'productos.precios', 'orden' => 'p.descripcion',
                'campos' => [
                    'producto_id' => ['Producto', 'producto', 'requerido'],
                    'descripcion' => ['Descripción', 'texto', 'max:100', ['placeholder' => 'Ej.: Llevando 12 te regalamos 1']],
                    'cantidad_compra' => ['Cantidad que lleva (unidades)', 'decimal', 'requerido|numerico|min:1'],
                    'cantidad_bonificada' => ['Unidades sin cargo', 'decimal', 'requerido|numerico|min:1'],
                    'desde' => ['Vigente desde', 'fecha', 'fecha'],
                    'hasta' => ['Vigente hasta', 'fecha', 'fecha'],
                ],
                'columnas' => ['producto' => 'Producto', 'descripcion' => 'Descripción', 'regla' => 'Bonificación', 'desde' => 'Desde', 'hasta' => 'Hasta'],
                'consulta' => "SELECT pr.*, p.descripcion AS producto, CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.cantidad_compra)), ' + ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.cantidad_bonificada))) AS regla FROM promociones pr JOIN productos p ON p.id = pr.producto_id",
            ],
        ];
        return $defs[$entidad] ?? null;
    }
}
