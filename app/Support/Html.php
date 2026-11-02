<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ayudantes para campos de formulario Bootstrap con errores de validación.
 * Opciones comunes: requerido, tipo, inputmode, ayuda, col, attrs, placeholder.
 */
final class Html
{
    public static function input(string $nombre, string $etiqueta, array $datos = [], array $errores = [], array $o = []): string
    {
        $valor = $o['valor'] ?? ($datos[$nombre] ?? '');
        $tipo = $o['tipo'] ?? 'text';
        $attrs = $o['attrs'] ?? '';
        if (isset($o['inputmode'])) {
            $attrs .= ' inputmode="' . e($o['inputmode']) . '"';
        }
        if (!empty($o['requerido'])) {
            $attrs .= ' required';
        }
        if (isset($o['placeholder'])) {
            $attrs .= ' placeholder="' . e($o['placeholder']) . '"';
        }
        if (isset($o['max'])) {
            $attrs .= ' maxlength="' . (int) $o['max'] . '"';
        }
        $campo = '<input type="' . e($tipo) . '" class="form-control' . (isset($errores[$nombre]) ? ' is-invalid' : '') . '" id="f_' . e($nombre) . '" name="' . e($nombre) . '" value="' . e($valor) . '"' . $attrs . '>';
        if (isset($o['prefijo'])) {
            $campo = '<div class="input-group"><span class="input-group-text">' . e($o['prefijo']) . '</span>' . $campo . '</div>';
        }
        return self::envolver($nombre, $etiqueta, $campo, $errores, $o);
    }

    public static function decimal(string $nombre, string $etiqueta, array $datos = [], array $errores = [], array $o = []): string
    {
        $v = $o['valor'] ?? ($datos[$nombre] ?? '');
        if ($v !== '' && $v !== null && is_numeric($v)) {
            $dec = $o['decimales'] ?? 2;
            $v = rtrim(rtrim(number_format((float) $v, $dec, ',', ''), '0'), ',');
            if ($v === '') {
                $v = '0';
            }
        }
        return self::input($nombre, $etiqueta, $datos, $errores, $o + ['valor' => $v, 'inputmode' => 'decimal', 'attrs' => ($o['attrs'] ?? '') . ' autocomplete="off"']);
    }

    public static function select(string $nombre, string $etiqueta, array $opciones, array $datos = [], array $errores = [], array $o = []): string
    {
        $valor = $o['valor'] ?? ($datos[$nombre] ?? '');
        $attrs = ($o['attrs'] ?? '') . (!empty($o['requerido']) ? ' required' : '');
        $campo = '<select class="form-select' . (isset($errores[$nombre]) ? ' is-invalid' : '') . '" id="f_' . e($nombre) . '" name="' . e($nombre) . '"' . $attrs . '>'
            . opciones($opciones, $valor, $o['vacio'] ?? null) . '</select>';
        return self::envolver($nombre, $etiqueta, $campo, $errores, $o);
    }

    public static function textarea(string $nombre, string $etiqueta, array $datos = [], array $errores = [], array $o = []): string
    {
        $campo = '<textarea class="form-control' . (isset($errores[$nombre]) ? ' is-invalid' : '') . '" id="f_' . e($nombre) . '" name="' . e($nombre) . '" rows="' . (int) ($o['filas'] ?? 2) . '"' . ($o['attrs'] ?? '') . '>' . e($datos[$nombre] ?? '') . '</textarea>';
        return self::envolver($nombre, $etiqueta, $campo, $errores, $o);
    }

    public static function check(string $nombre, string $etiqueta, array $datos = [], array $o = []): string
    {
        $marcado = !empty($o['valor'] ?? ($datos[$nombre] ?? false));
        return '<div class="' . e($o['col'] ?? 'col-12') . '"><div class="form-check form-switch">'
            . '<input type="hidden" name="' . e($nombre) . '" value="0">'
            . '<input class="form-check-input" type="checkbox" role="switch" id="f_' . e($nombre) . '" name="' . e($nombre) . '" value="1"' . ($marcado ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="f_' . e($nombre) . '">' . e($etiqueta) . '</label></div>'
            . (isset($o['ayuda']) ? '<div class="form-text">' . e($o['ayuda']) . '</div>' : '') . '</div>';
    }

    /** Selector de días de la semana (guarda "1,3,5"). */
    public static function dias(string $nombre, string $etiqueta, array $datos = [], array $o = []): string
    {
        $sel = array_filter(explode(',', (string) ($datos[$nombre] ?? '')));
        $html = '<div class="' . e($o['col'] ?? 'col-12') . '"><label class="form-label d-block">' . e($etiqueta) . '</label><div class="d-flex flex-wrap gap-2">';
        foreach (dias_semana() as $n => $dia) {
            $id = 'f_' . $nombre . '_' . $n;
            $html .= '<input type="checkbox" class="btn-check" id="' . $id . '" name="' . e($nombre) . '[]" value="' . $n . '"' . (in_array((string) $n, $sel, true) ? ' checked' : '') . ' autocomplete="off">'
                . '<label class="btn btn-outline-secondary btn-touch" for="' . $id . '">' . mb_substr($dia, 0, 3) . '</label>';
        }
        return $html . '</div></div>';
    }

    private static function envolver(string $nombre, string $etiqueta, string $campo, array $errores, array $o): string
    {
        $req = !empty($o['requerido']) ? ' <span class="text-danger">*</span>' : '';
        $html = '<div class="' . e($o['col'] ?? 'col-12') . '">';
        $html .= '<label class="form-label" for="f_' . e($nombre) . '">' . e($etiqueta) . $req . '</label>' . $campo;
        if (isset($errores[$nombre])) {
            $html .= '<div class="invalid-feedback d-block">' . e($errores[$nombre]) . '</div>';
        }
        if (isset($o['ayuda'])) {
            $html .= '<div class="form-text">' . e($o['ayuda']) . '</div>';
        }
        return $html . '</div>';
    }

    /** Botón de acción POST con confirmación opcional. */
    public static function botonPost(string $accion, string $texto, string $clase = 'btn-outline-secondary', ?string $confirmar = null, bool $motivo = false, array $ocultos = []): string
    {
        $attrs = $confirmar ? ' data-confirmar="' . e($confirmar) . '"' . ($motivo ? ' data-motivo' : '') : '';
        $html = '<form method="post" action="' . e(url($accion)) . '" class="d-inline"' . $attrs . '>' . csrf_campo();
        foreach ($ocultos as $k => $v) {
            $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
        }
        return $html . '<button type="submit" class="btn ' . e($clase) . ' btn-touch">' . $texto . '</button></form>';
    }

    /** Barra de exportación (CSV / Excel / imprimir) que conserva los filtros actuales. */
    public static function exportar(string $ruta, bool $imprimir = true): string
    {
        $q = $_GET;
        unset($q['pagina']);
        $html = '<div class="btn-group" role="group" aria-label="Exportar">';
        foreach (['csv' => 'CSV', 'xlsx' => 'Excel'] as $f => $t) {
            $html .= '<a class="btn btn-outline-secondary btn-touch" href="' . e(url($ruta) . '?' . http_build_query($q + ['formato' => $f])) . '"><i class="bi bi-filetype-' . $f . '"></i> ' . $t . '</a>';
        }
        if ($imprimir) {
            $html .= '<a class="btn btn-outline-secondary btn-touch" href="' . e(url($ruta) . '?' . http_build_query($q + ['formato' => 'imprimir'])) . '" target="_blank"><i class="bi bi-printer"></i> PDF / Imprimir</a>';
        }
        return $html . '</div>';
    }
}
