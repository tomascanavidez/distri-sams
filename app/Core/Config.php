<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Acceso a la configuración del archivo config/config.php (notación con puntos).
 */
final class Config
{
    private static array $datos = [];
    private static bool $instalado = false;

    public static function cargar(string $archivo): void
    {
        $base = [
            'app' => [
                'nombre' => 'Distribuidora Toro',
                'debug' => false,
                'url' => '',
                'clave' => '',
            ],
            'db' => [
                'host' => 'localhost',
                'puerto' => 3306,
                'nombre' => '',
                'usuario' => '',
                'clave' => '',
            ],
            'sesion' => [
                'inactividad_min' => 60,
                'inactividad_movil_min' => 720,
            ],
            'backup' => [
                'conservar' => 15,
            ],
        ];
        if (is_file($archivo)) {
            $config = require $archivo;
            if (is_array($config)) {
                $base = array_replace_recursive($base, $config);
                self::$instalado = true;
            }
        }
        self::$datos = $base;
    }

    public static function instalado(): bool
    {
        return self::$instalado && is_file(STORAGE_PATH . '/instalado.lock');
    }

    public static function get(string $clave, mixed $defecto = null): mixed
    {
        $valor = self::$datos;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $defecto;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }

    public static function set(string $clave, mixed $valor): void
    {
        $ref = &self::$datos;
        foreach (explode('.', $clave) as $parte) {
            if (!isset($ref[$parte]) || !is_array($ref[$parte])) {
                $ref[$parte] = [];
            }
            $ref = &$ref[$parte];
        }
        $ref = $valor;
    }
}
