<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Inicio de sesión PHP con cookies seguras (HttpOnly, SameSite, Secure en HTTPS).
 */
final class Session
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = STORAGE_PATH . '/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        $vidaMax = max((int) Config::get('sesion.inactividad_min', 60), (int) Config::get('sesion.inactividad_movil_min', 720)) * 60;
        ini_set('session.gc_maxlifetime', (string) $vidaMax);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('DTSESION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (base_path() ?: '') . '/',
            'secure' => Request::esHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function regenerar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
