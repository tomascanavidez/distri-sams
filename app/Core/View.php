<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Motor de vistas en PHP plano con layout y bloques (stacks) para scripts.
 */
final class View
{
    private static array $bloques = [];
    private static array $pila = [];

    public static function render(string $plantilla, array $datos = [], ?string $layout = 'layouts/app'): string
    {
        $contenido = self::parcial($plantilla, $datos);
        if ($layout === null) {
            return $contenido;
        }
        return self::parcial($layout, array_merge($datos, ['contenido' => $contenido]));
    }

    public static function parcial(string $plantilla, array $datos = []): string
    {
        $archivo = APP_PATH . '/Views/' . $plantilla . '.php';
        if (!is_file($archivo)) {
            throw new \RuntimeException("Vista no encontrada: $plantilla");
        }
        extract($datos, EXTR_SKIP);
        ob_start();
        try {
            include $archivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Inicia la captura de un bloque (ej. 'scripts'). */
    public static function inicio(string $nombre): void
    {
        self::$pila[] = $nombre;
        ob_start();
    }

    public static function fin(): void
    {
        $nombre = array_pop(self::$pila);
        self::$bloques[$nombre] = (self::$bloques[$nombre] ?? '') . ob_get_clean();
    }

    public static function bloque(string $nombre): string
    {
        return self::$bloques[$nombre] ?? '';
    }
}
