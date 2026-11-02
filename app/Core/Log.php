<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro de eventos en storage/logs/app-AAAA-MM-DD.log
 */
final class Log
{
    public static function escribir(string $nivel, string $mensaje, array $contexto = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $linea = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($nivel),
            $mensaje,
            $contexto ? json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $linea, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $mensaje, array $contexto = []): void
    {
        self::escribir('error', $mensaje, $contexto);
    }

    public static function info(string $mensaje, array $contexto = []): void
    {
        self::escribir('info', $mensaje, $contexto);
    }
}
