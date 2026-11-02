<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Petición HTTP actual.
 */
final class Request
{
    private static ?array $json = null;

    public static function metodo(): string
    {
        $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($m === 'POST' && isset($_POST['_metodo'])) {
            $m = strtoupper((string) $_POST['_metodo']);
        }
        return $m;
    }

    /** Ruta relativa a la base de la aplicación, ej. "/productos/5". */
    public static function ruta(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        $base = base_path();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/public/')) {
            $uri = substr($uri, 7);
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public static function get(string $clave, mixed $defecto = null): mixed
    {
        return $_GET[$clave] ?? $defecto;
    }

    public static function post(string $clave, mixed $defecto = null): mixed
    {
        return $_POST[$clave] ?? self::json()[$clave] ?? $defecto;
    }

    public static function input(string $clave, mixed $defecto = null): mixed
    {
        return $_POST[$clave] ?? self::json()[$clave] ?? $_GET[$clave] ?? $defecto;
    }

    /** Texto recortado (o null si viene vacío). */
    public static function texto(string $clave, ?string $defecto = null): ?string
    {
        $v = self::input($clave);
        if ($v === null || is_array($v)) {
            return $defecto;
        }
        $v = trim((string) $v);
        return $v === '' ? $defecto : $v;
    }

    public static function entero(string $clave, int $defecto = 0): int
    {
        $v = self::input($clave);
        return is_numeric($v) ? (int) $v : $defecto;
    }

    public static function decimal(string $clave, float $defecto = 0.0): float
    {
        $v = self::input($clave);
        if ($v === null || $v === '') {
            return $defecto;
        }
        return parse_decimal($v);
    }

    public static function todos(): array
    {
        return array_merge($_GET, $_POST, self::json());
    }

    public static function json(): array
    {
        if (self::$json === null) {
            self::$json = [];
            if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
                $datos = json_decode((string) file_get_contents('php://input'), true);
                self::$json = is_array($datos) ? $datos : [];
            }
        }
        return self::$json;
    }

    public static function esAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function esHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function archivo(string $clave): ?array
    {
        $f = $_FILES[$clave] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $f;
    }
}
