<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuestas HTTP: redirecciones, JSON, descargas y páginas de error.
 */
final class Response
{
    public static function redirigir(string $ruta): void
    {
        $destino = preg_match('#^https?://#', $ruta) ? $ruta : url($ruta);
        header('Location: ' . $destino);
        exit;
    }

    /** Vuelve a la página anterior (si es del mismo sitio) o al inicio. */
    public static function volver(string $porDefecto = '/'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
            header('Location: ' . $ref);
            exit;
        }
        self::redirigir($porDefecto);
    }

    public static function json(mixed $datos, int $codigo = 200): void
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function descarga(string $contenido, string $nombre, string $tipo): void
    {
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nombre) . '"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: no-store');
        echo $contenido;
    }

    public static function error(int $codigo, string $mensaje): void
    {
        http_response_code($codigo);
        if (Request::esAjax()) {
            self::json(['ok' => false, 'error' => $mensaje], $codigo);
            return;
        }
        $titulos = [403 => 'Acceso denegado', 404 => 'Página no encontrada', 405 => 'Método no permitido'];
        $layout = Auth::id() ? 'layouts/app' : 'layouts/simple';
        echo View::render('errores/error', [
            'titulo' => $titulos[$codigo] ?? 'Error',
            'codigo' => $codigo,
            'mensaje' => $mensaje,
        ], $layout);
    }
}
