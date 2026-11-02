<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reloj de la aplicación. Normalmente devuelve la hora real; las pruebas y la carga de
 * datos de demostración pueden fijarlo para registrar operaciones en fechas pasadas.
 */
final class Reloj
{
    private static ?int $fijo = null;

    public static function ahora(): int
    {
        return self::$fijo ?? time();
    }

    public static function fijar(string|int $momento): void
    {
        self::$fijo = is_int($momento) ? $momento : (int) strtotime($momento);
    }

    public static function liberar(): void
    {
        self::$fijo = null;
    }
}
