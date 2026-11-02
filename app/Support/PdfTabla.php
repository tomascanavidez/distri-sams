<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Ajustes;

require_once BASE_PATH . '/vendor/fpdf/fpdf.php';

/**
 * PDF de un listado/reporte en forma de tabla (FPDF, sin dependencias de Composer).
 */
final class PdfTabla extends \FPDF
{
    private array $anchos = [];

    public function __construct(
        private string $titulo,
        private string $subtitulo,
        private array $columnas,
        private array $filas,
        private ?array $totales
    ) {
        $orientacion = count($columnas) > 6 ? 'L' : 'P';
        parent::__construct($orientacion, 'mm', 'A4');
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 14);
        $this->AliasNbPages();
    }

    /** Convierte UTF-8 a la codificación de las fuentes base de FPDF. */
    public static function t(?string $s): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string) $s);
    }

    public function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 6, self::t(Ajustes::get('empresa_nombre') . ' - ' . $this->titulo), 0, 1);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(0, 4, self::t(trim($this->subtitulo . '   Emitido: ' . date('d/m/Y H:i'))), 0, 1);
        $this->Ln(2);
        $this->encabezadoTabla();
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 7);
        $this->Cell(0, 5, self::t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    private function encabezadoTabla(): void
    {
        if (!$this->anchos) {
            return;
        }
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetFillColor(230, 230, 230);
        $i = 0;
        foreach ($this->columnas as [$etiqueta, $tipo]) {
            $this->Cell($this->anchos[$i++], 6, self::t($etiqueta), 1, 0, $this->alineacion($tipo), true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 7.5);
    }

    private function alineacion(string $tipo): string
    {
        return in_array($tipo, ['money', 'num', 'cant', 'entero', 'porc'], true) ? 'R' : 'L';
    }

    public function generar(): string
    {
        $util = $this->GetPageWidth() - 20;
        // Anchos proporcionales al contenido (con mínimos)
        $this->SetFont('Helvetica', '', 7.5);
        $medidas = [];
        foreach ($this->columnas as $k => [$etiqueta, $tipo]) {
            $max = $this->GetStringWidth(self::t($etiqueta)) + 3;
            foreach (array_slice($this->filas, 0, 200) as $f) {
                $max = max($max, $this->GetStringWidth(self::t(Exportador::formatear($f[$k] ?? '', $tipo))) + 3);
            }
            $medidas[] = min($max, 70);
        }
        $total = array_sum($medidas) ?: 1;
        $this->anchos = array_map(fn ($m) => $m * $util / $total, $medidas);
        $this->AddPage();
        $this->imprimirFilas($this->filas, false);
        if ($this->totales) {
            $this->SetFont('Helvetica', 'B', 7.5);
            $this->imprimirFilas([$this->totales], true);
        }
        if (!$this->filas) {
            $this->Cell(0, 8, self::t('Sin datos para los filtros elegidos.'), 0, 1, 'C');
        }
        return $this->Output('S');
    }

    private function imprimirFilas(array $filas, bool $total): void
    {
        foreach ($filas as $f) {
            $i = 0;
            foreach ($this->columnas as $k => [, $tipo]) {
                $texto = self::t(Exportador::formatear($f[$k] ?? '', $tipo));
                $ancho = $this->anchos[$i++];
                while ($this->GetStringWidth($texto) > $ancho - 1.5 && strlen($texto) > 1) {
                    $texto = substr($texto, 0, -1);
                }
                $this->Cell($ancho, 5, $texto, $total ? 'T' : 'B', 0, $this->alineacion($tipo));
            }
            $this->Ln();
        }
    }
}
