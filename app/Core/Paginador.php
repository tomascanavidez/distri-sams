<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Paginación y ordenamiento del lado del servidor.
 * Lee ?pagina, ?orden y ?dir de la URL; el orden sólo acepta columnas de una lista blanca.
 */
final class Paginador
{
    public array $items = [];
    public int $total = 0;
    public int $pagina = 1;
    public int $paginas = 1;
    public string $orden;
    public string $dir;

    public function __construct(
        string $sql,
        array $params,
        private array $ordenes,
        string $ordenDefecto,
        string $dirDefecto = 'asc',
        public int $porPagina = 25
    ) {
        $this->orden = isset($ordenes[$_GET['orden'] ?? '']) ? (string) $_GET['orden'] : $ordenDefecto;
        $dir = strtolower((string) ($_GET['dir'] ?? $dirDefecto));
        $this->dir = $dir === 'desc' ? 'desc' : 'asc';
        $pp = (int) ($_GET['por_pagina'] ?? 0);
        if (in_array($pp, [10, 25, 50, 100], true)) {
            $this->porPagina = $pp;
        }

        $this->total = (int) DB::valor("SELECT COUNT(*) FROM ($sql) t_conteo", $params);
        $this->paginas = max(1, (int) ceil($this->total / $this->porPagina));
        $this->pagina = min(max(1, (int) ($_GET['pagina'] ?? 1)), $this->paginas);
        $offset = ($this->pagina - 1) * $this->porPagina;
        $ordenSql = $ordenes[$this->orden] ?? $ordenes[$ordenDefecto];
        $this->items = DB::filas(
            "$sql ORDER BY $ordenSql {$this->dir} LIMIT {$this->porPagina} OFFSET $offset",
            $params
        );
    }

    /** URL de la página actual cambiando algunos parámetros. */
    public static function urlCon(array $cambios): string
    {
        $q = array_merge($_GET, $cambios);
        $q = array_filter($q, fn ($v) => $v !== null && $v !== '');
        $ruta = Request::ruta();
        return url($ruta) . ($q ? '?' . http_build_query($q) : '');
    }

    /** Encabezado de columna ordenable. */
    public function th(string $clave, string $etiqueta, string $clase = ''): string
    {
        $activo = $this->orden === $clave;
        $dir = $activo && $this->dir === 'asc' ? 'desc' : 'asc';
        $icono = $activo ? ($this->dir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
        return '<th class="' . e($clase) . '"><a class="text-reset text-decoration-none" href="'
            . e(self::urlCon(['orden' => $clave, 'dir' => $dir, 'pagina' => null])) . '">' . e($etiqueta) . $icono . '</a></th>';
    }

    public function enlaces(): string
    {
        $desde = $this->total ? ($this->pagina - 1) * $this->porPagina + 1 : 0;
        $hasta = min($this->total, $this->pagina * $this->porPagina);
        $html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 paginacion">';
        $html .= '<small class="text-body-secondary">Mostrando ' . $desde . '–' . $hasta . ' de ' . number_format($this->total, 0, ',', '.') . '</small>';
        if ($this->paginas > 1) {
            $html .= '<nav aria-label="Paginación"><ul class="pagination mb-0">';
            $html .= $this->item($this->pagina - 1, '<i class="bi bi-chevron-left"></i>', $this->pagina <= 1, false, 'Anterior');
            $inicio = max(1, $this->pagina - 2);
            $fin = min($this->paginas, $this->pagina + 2);
            if ($inicio > 1) {
                $html .= $this->item(1, '1', false, false);
                if ($inicio > 2) {
                    $html .= '<li class="page-item disabled d-none d-sm-block"><span class="page-link">…</span></li>';
                }
            }
            for ($i = $inicio; $i <= $fin; $i++) {
                $html .= $this->item($i, (string) $i, false, $i === $this->pagina);
            }
            if ($fin < $this->paginas) {
                if ($fin < $this->paginas - 1) {
                    $html .= '<li class="page-item disabled d-none d-sm-block"><span class="page-link">…</span></li>';
                }
                $html .= $this->item($this->paginas, (string) $this->paginas, false, false);
            }
            $html .= $this->item($this->pagina + 1, '<i class="bi bi-chevron-right"></i>', $this->pagina >= $this->paginas, false, 'Siguiente');
            $html .= '</ul></nav>';
        }
        return $html . '</div>';
    }

    private function item(int $pagina, string $texto, bool $deshabilitado, bool $activo, string $aria = ''): string
    {
        $clase = 'page-item' . ($deshabilitado ? ' disabled' : '') . ($activo ? ' active' : '');
        $href = $deshabilitado ? '#' : e(self::urlCon(['pagina' => $pagina]));
        return '<li class="' . $clase . '"><a class="page-link" href="' . $href . '"' . ($aria ? ' aria-label="' . $aria . '"' : '') . '>' . $texto . '</a></li>';
    }
}
