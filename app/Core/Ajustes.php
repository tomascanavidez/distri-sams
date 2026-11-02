<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Parámetros del sistema guardados en la tabla `configuracion` (clave/valor).
 * Se cargan una sola vez por petición.
 */
final class Ajustes
{
    private static ?array $cache = null;

    /** Valores por defecto de todos los parámetros configurables. */
    public const DEFECTOS = [
        'empresa_nombre' => 'Distribuidora Toro',
        'empresa_razon_social' => 'Distribuidora Toro S.R.L.',
        'empresa_cuit' => '30-71234567-1',
        'empresa_condicion_iva' => 'RI',
        'empresa_iibb' => '30-71234567-1',
        'empresa_inicio_actividades' => '2015-03-01',
        'empresa_domicilio' => 'Av. Tavella 2850, Salta Capital',
        'empresa_telefono' => '(0387) 431-5500',
        'empresa_email' => 'ventas@distribuidoratoro.com.ar',
        'empresa_logo' => '',
        'empresa_leyenda' => 'Gracias por su compra',
        'punto_venta_defecto' => '1',
        'deposito_central' => '1',
        'cliente_consumidor_final' => '1',
        'costo_metodo' => 'ultimo',
        'actualizar_precios_al_recibir' => '1',
        'credito_modo' => 'advertir',
        'credito_tolerancia_dias' => '0',
        'bloqueo_automatico_dias' => '0',
        'stock_permitir_negativo' => '0',
        'stock_alerta_vencimiento_dias' => '30',
        'comprobante_reparto' => 'factura',
        'pedido_movil_auto_confirmar' => '1',
        'login_max_intentos' => '5',
        'login_bloqueo_min' => '15',
        'redondeo_modo' => 'arriba',
        'pos_lista_precio' => '3',
        'sugerencia_dias_venta' => '30',
        'sugerencia_dias_cobertura' => '15',
    ];

    public static function get(string $clave, mixed $defecto = null): mixed
    {
        self::cargar();
        if (array_key_exists($clave, self::$cache)) {
            return self::$cache[$clave];
        }
        return $defecto ?? (self::DEFECTOS[$clave] ?? null);
    }

    public static function set(string $clave, ?string $valor): void
    {
        DB::query(
            'INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [$clave, $valor]
        );
        self::cargar();
        self::$cache[$clave] = $valor;
    }

    public static function todos(): array
    {
        self::cargar();
        return array_merge(self::DEFECTOS, self::$cache);
    }

    public static function limpiar(): void
    {
        self::$cache = null;
    }

    private static function cargar(): void
    {
        if (self::$cache === null) {
            try {
                self::$cache = DB::pares('SELECT clave, valor FROM configuracion');
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
    }
}
