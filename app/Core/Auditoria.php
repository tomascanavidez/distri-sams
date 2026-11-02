<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro de auditoría: quién hizo qué, cuándo y con qué valores.
 */
final class Auditoria
{
    public static function registrar(
        string $modulo,
        string $accion,
        ?string $entidad = null,
        ?int $entidadId = null,
        ?array $antes = null,
        ?array $despues = null,
        ?string $descripcion = null
    ): void {
        // Sólo se guardan los campos que cambiaron cuando hay antes y después.
        if ($antes !== null && $despues !== null) {
            $cambiosAntes = [];
            $cambiosDespues = [];
            foreach ($despues as $k => $v) {
                $previo = $antes[$k] ?? null;
                if ((string) $previo !== (string) (is_array($v) ? json_encode($v) : $v)) {
                    $cambiosAntes[$k] = $previo;
                    $cambiosDespues[$k] = $v;
                }
            }
            $antes = $cambiosAntes;
            $despues = $cambiosDespues;
        }
        unset($antes['password_hash'], $despues['password_hash']);

        DB::insertar('auditoria', [
            'fecha' => ahora(),
            'usuario_id' => Auth::id(),
            'modulo' => $modulo,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'descripcion' => $descripcion !== null ? mb_substr($descripcion, 0, 255) : null,
            'datos_anteriores' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            'datos_nuevos' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            'ip' => PHP_SAPI === 'cli' ? 'cli' : Request::ip(),
        ]);
    }
}
