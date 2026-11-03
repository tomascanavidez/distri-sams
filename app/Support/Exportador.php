<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Ajustes;
use App\Core\View;

/**
 * Exportación de listados y reportes a CSV (Excel en español), XLSX nativo, impresión HTML y PDF.
 * $columnas = [clave => [etiqueta, tipo]] con tipo: texto | money | num | cant | fecha | fecha_hora | entero
 */
final class Exportador
{
    /** $grupo: columna por la que se agrupa con subtítulos en PDF e impresión (en CSV/Excel queda como columna). */
    public static function enviar(string $formato, string $titulo, array $columnas, array $filas, ?array $totales = null, string $subtitulo = '', ?string $grupo = null): void
    {
        $nombre = self::nombreArchivo($titulo);
        switch ($formato) {
            case 'xlsx':
                if (class_exists(\ZipArchive::class)) {
                    $contenido = Xlsx::generar($titulo, $columnas, $filas, $totales);
                    \App\Core\Response::descarga($contenido, $nombre . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    return;
                }
                // sin ZipArchive se entrega CSV
            case 'csv':
                \App\Core\Response::descarga(self::csv($columnas, $filas, $totales), $nombre . '.csv', 'text/csv; charset=utf-8');
                return;
            case 'pdf':
                $pdf = new PdfTabla($titulo, $subtitulo, $columnas, $filas, $totales, $grupo);
                \App\Core\Response::descarga($pdf->generar(), $nombre . '.pdf', 'application/pdf');
                return;
            default:
                echo View::render('impresion/tabla', compact('titulo', 'subtitulo', 'columnas', 'filas', 'totales', 'grupo') + [
                    'urlPdf' => url(\App\Core\Request::ruta()) . '?' . http_build_query(array_merge($_GET, ['formato' => 'pdf'])),
                ], 'layouts/impresion');
        }
    }

    /** CSV con BOM UTF-8 y ";" como separador (se abre bien en Excel configurado en español). */
    public static function csv(array $columnas, array $filas, ?array $totales = null): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_map(fn ($c) => $c[0], $columnas), ';');
        $escribir = function (array $f) use ($fh, $columnas) {
            $linea = [];
            foreach ($columnas as $k => [, $tipo]) {
                $v = $f[$k] ?? '';
                $linea[] = match ($tipo) {
                    'money', 'num' => $v === '' || $v === null ? '' : number_format((float) $v, 2, ',', ''),
                    'cant' => $v === '' || $v === null ? '' : str_replace('.', ',', (string) round((float) $v, 3)),
                    'fecha' => fecha((string) $v),
                    'fecha_hora' => fecha_hora((string) $v),
                    default => (string) $v,
                };
            }
            fputcsv($fh, $linea, ';');
        };
        foreach ($filas as $f) {
            $escribir($f);
        }
        if ($totales) {
            $escribir($totales);
        }
        rewind($fh);
        return (string) stream_get_contents($fh);
    }

    /** Formato para pantalla. */
    public static function formatear(mixed $v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($tipo) {
            'money' => money($v),
            'num' => num($v),
            'cant' => cant($v),
            'entero' => number_format((float) $v, 0, ',', '.'),
            'fecha' => fecha((string) $v),
            'fecha_hora' => fecha_hora((string) $v),
            'porc' => num($v, 1) . ' %',
            default => (string) $v,
        };
    }

    public static function nombreArchivo(string $titulo): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titulo) ?: 'reporte';
        $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $s), '_'));
        return ($s ?: 'reporte') . '_' . date('Ymd_His');
    }

    public static function empresa(): array
    {
        return [
            'nombre' => Ajustes::get('empresa_nombre'),
            'razon' => Ajustes::get('empresa_razon_social'),
            'cuit' => Ajustes::get('empresa_cuit'),
        ];
    }
}
