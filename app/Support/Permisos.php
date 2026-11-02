<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\DB;

/**
 * Catálogo de permisos (módulo + acción) y permisos por defecto de los roles predefinidos.
 */
final class Permisos
{
    public const ACCIONES = [
        'ver' => 'Ver',
        'crear' => 'Crear',
        'editar' => 'Editar',
        'anular' => 'Anular',
        'exportar' => 'Exportar',
        'precios' => 'Modificar precios',
    ];

    /** módulo => [nombre, acciones disponibles] */
    public static function modulos(): array
    {
        return [
            'dashboard' => ['Panel principal', ['ver']],
            'productos' => ['Productos y precios', ['ver', 'crear', 'editar', 'anular', 'exportar', 'precios']],
            'stock' => ['Stock e inventario', ['ver', 'crear', 'anular', 'exportar']],
            'clientes' => ['Clientes', ['ver', 'crear', 'editar', 'anular', 'exportar']],
            'proveedores' => ['Proveedores', ['ver', 'crear', 'editar', 'anular', 'exportar']],
            'compras' => ['Compras y recepciones', ['ver', 'crear', 'editar', 'anular', 'exportar']],
            'pedidos' => ['Pedidos (preventa)', ['ver', 'crear', 'editar', 'anular', 'exportar']],
            'ventas' => ['Ventas y facturación', ['ver', 'crear', 'anular', 'exportar']],
            'reparto' => ['Reparto y logística', ['ver', 'crear', 'editar', 'anular', 'exportar']],
            'cobranzas' => ['Cobranzas', ['ver', 'crear', 'anular', 'exportar']],
            'caja' => ['Caja', ['ver', 'crear', 'anular']],
            'finanzas' => ['Bancos, cheques y gastos', ['ver', 'crear', 'anular', 'exportar']],
            'preventistas' => ['Preventistas y comisiones', ['ver', 'crear', 'editar', 'anular']],
            'reportes' => ['Reportes', ['ver', 'exportar']],
            'configuracion' => ['Configuración', ['ver', 'editar']],
            'usuarios' => ['Usuarios y roles', ['ver', 'crear', 'editar', 'anular']],
            'auditoria' => ['Auditoría', ['ver', 'exportar']],
            'backups' => ['Copias de seguridad', ['ver', 'crear']],
            'movil_preventa' => ['App preventista (celular)', ['ver']],
            'movil_reparto' => ['App chofer (celular)', ['ver']],
        ];
    }

    /** Roles del sistema: slug => [nombre, descripción, pantalla de inicio, permisos] */
    public static function rolesBase(): array
    {
        $todo = [];
        foreach (self::modulos() as $mod => [, $acciones]) {
            foreach ($acciones as $a) {
                $todo[] = "$mod.$a";
            }
        }
        $sin = fn (array $lista, array $quitar) => array_values(array_diff($lista, $quitar));

        return [
            'admin' => ['Administrador', 'Acceso total al sistema', '/', $todo],
            'encargado' => ['Encargado / Oficina', 'Gestión comercial y administrativa completa', '/', $sin($todo, [
                'usuarios.crear', 'usuarios.editar', 'usuarios.anular', 'configuracion.editar', 'backups.crear',
            ])],
            'mostrador' => ['Vendedor de mostrador', 'Ventas en mostrador, cobranzas y caja', '/ventas/pos', [
                'dashboard.ver', 'productos.ver', 'stock.ver', 'clientes.ver', 'clientes.crear', 'clientes.editar',
                'pedidos.ver', 'pedidos.crear', 'ventas.ver', 'ventas.crear', 'cobranzas.ver', 'cobranzas.crear',
                'caja.ver', 'caja.crear',
            ]],
            'preventista' => ['Preventista', 'Toma de pedidos en la calle desde el celular', '/movil', [
                'movil_preventa.ver', 'clientes.ver', 'productos.ver', 'stock.ver', 'pedidos.ver', 'pedidos.crear',
            ]],
            'deposito' => ['Depósito', 'Stock, recepción de mercadería y preparación de pedidos', '/stock', [
                'dashboard.ver', 'productos.ver', 'stock.ver', 'stock.crear', 'stock.exportar', 'pedidos.ver', 'pedidos.editar',
                'compras.ver', 'compras.crear', 'reparto.ver', 'proveedores.ver',
            ]],
            'chofer' => ['Chofer / Repartidor', 'Entregas y cobros en el reparto desde el celular', '/chofer', [
                'movil_reparto.ver',
            ]],
            'contador' => ['Contador', 'Sólo lectura de reportes', '/reportes', [
                'dashboard.ver', 'reportes.ver', 'reportes.exportar',
            ]],
        ];
    }

    /** Crea los permisos faltantes en la base (idempotente). */
    public static function sincronizar(): void
    {
        $etiquetas = self::ACCIONES;
        foreach (self::modulos() as $mod => [$nombre, $acciones]) {
            foreach ($acciones as $a) {
                DB::query(
                    'INSERT IGNORE INTO permisos (modulo, accion, descripcion) VALUES (?, ?, ?)',
                    [$mod, $a, $nombre . ' — ' . $etiquetas[$a]]
                );
            }
        }
    }
}
