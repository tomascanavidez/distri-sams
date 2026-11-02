<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Manejo centralizado de errores: en producción muestra un mensaje amigable
 * y registra el detalle en storage/logs; en modo debug muestra la traza.
 */
final class ManejadorErrores
{
    public static function registrar(): void
    {
        $debug = (bool) Config::get('app.debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
            if (!(error_reporting() & $nivel)) {
                return false;
            }
            throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
        });

        set_exception_handler([self::class, 'manejar']);

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::manejar(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
            }
        });
    }

    public static function manejar(Throwable $e): void
    {
        $id = substr(bin2hex(random_bytes(4)), 0, 8);
        Log::error($e->getMessage(), [
            'ref' => $id,
            'archivo' => $e->getFile() . ':' . $e->getLine(),
            'url' => $_SERVER['REQUEST_URI'] ?? 'cli',
            'traza' => explode("\n", $e->getTraceAsString()),
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[ERROR] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        $debug = (bool) Config::get('app.debug', false);
        $esJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        if ($esJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => $debug ? $e->getMessage() : 'Ocurrió un error inesperado (ref. ' . $id . ').',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $detalle = $debug
            ? '<pre style="white-space:pre-wrap;font-size:12px">' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre>'
            : '';
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Error</title><style>body{font-family:system-ui,sans-serif;background:#f4f5f7;color:#222;padding:24px}'
            . '.c{max-width:640px;margin:10vh auto;background:#fff;border-radius:12px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.08)}'
            . 'a{color:#b3261e}</style></head><body><div class="c"><h1 style="margin-top:0">Algo salió mal</h1>'
            . '<p>Ocurrió un error inesperado. Ya quedó registrado para revisarlo.</p>'
            . '<p>Referencia: <strong>' . $id . '</strong></p><p><a href="javascript:history.back()">Volver</a></p>'
            . $detalle . '</div></body></html>';
    }
}
