<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protección CSRF: un token por sesión, enviado como campo _token
 * o en la cabecera X-CSRF-Token (peticiones fetch/JSON).
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    public static function verificar(): bool
    {
        $enviado = $_POST['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? Request::json()['_token']
            ?? '';
        return is_string($enviado) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $enviado);
    }

    public static function renovar(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
