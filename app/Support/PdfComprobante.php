<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Ajustes;

require_once BASE_PATH . '/vendor/fpdf/fpdf.php';

/**
 * PDF de un comprobante de venta (A4), con logo y datos de la empresa configurables.
 */
final class PdfComprobante extends \FPDF
{
    public function __construct(private array $c)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetMargins(12, 12, 12);
        $this->SetAutoPageBreak(true, 30);
        $this->AliasNbPages();
    }

    private static function t(?string $s): string
    {
        return PdfTabla::t($s);
    }

    public function Header(): void
    {
        $c = $this->c;
        $letra = in_array($c['letra'], ['A', 'B', 'C'], true) ? $c['letra'] : 'X';
        $this->SetLineWidth(0.4);
        $this->Rect(12, 12, 186, 38);
        $this->Line(105, 12, 105, 50);
        $this->SetFillColor(255, 255, 255);
        $this->Rect(97, 12, 16, 16, 'DF');
        $this->SetXY(97, 13);
        $this->SetFont('Helvetica', 'B', 22);
        $this->Cell(16, 10, $letra, 0, 2, 'C');
        $this->SetFont('Helvetica', '', 6);
        $this->Cell(16, 3, self::t($letra === 'X' ? 'No válido' : 'Cód. ' . ['A' => '01', 'B' => '06', 'C' => '11'][$letra]), 0, 0, 'C');

        $logo = (string) Ajustes::get('empresa_logo', '');
        $y = 15;
        $rutaLogo = $logo ? PUBLIC_PATH . '/uploads/empresa/' . basename($logo) : PUBLIC_PATH . '/assets/img/logo.png';
        if (is_file($rutaLogo)) {
            try {
                $this->Image($rutaLogo, 15, 14, 0, 14);
                $y = 30;
            } catch (\Throwable) {
                // Logo en un formato que FPDF no soporta: se omite
            }
        }
        $this->SetXY(15, $y);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(80, 5, self::t(Ajustes::get('empresa_nombre')), 0, 2);
        $this->SetFont('Helvetica', '', 7.5);
        $this->Cell(80, 3.6, self::t(Ajustes::get('empresa_razon_social')), 0, 2);
        $this->Cell(80, 3.6, self::t(Ajustes::get('empresa_domicilio')), 0, 2);
        $this->Cell(80, 3.6, self::t('Tel. ' . Ajustes::get('empresa_telefono') . ' · ' . condicion_iva(Ajustes::get('empresa_condicion_iva'))), 0, 2);

        $this->SetXY(118, 15);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(78, 6, self::t(mb_strtoupper(tipo_comprobante($c['tipo']))), 0, 2);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(78, 5, self::t('N° ' . sprintf('%04d-%08d', $c['pv_numero'], $c['numero'])), 0, 2);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(78, 4, self::t('Fecha: ' . fecha($c['fecha'])), 0, 2);
        $this->Cell(78, 4, self::t('CUIT: ' . Ajustes::get('empresa_cuit') . '   IIBB: ' . Ajustes::get('empresa_iibb')), 0, 2);
        $this->Cell(78, 4, self::t('Inicio de actividades: ' . fecha(Ajustes::get('empresa_inicio_actividades'))), 0, 2);

        $this->Rect(12, 50, 186, 16);
        $this->SetXY(14, 51);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->Cell(120, 4.5, self::t('Cliente: ' . $c['cliente_nombre'] . ' (' . $c['cliente_codigo'] . ')'), 0, 0);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(60, 4.5, self::t($c['cliente_documento'] ?? ''), 0, 1, 'R');
        $this->SetX(14);
        $this->Cell(120, 4.5, self::t('Domicilio: ' . ($c['cliente_direccion'] ?? '')), 0, 0);
        $this->Cell(60, 4.5, self::t(condicion_iva($c['cliente_condicion_iva'])), 0, 1, 'R');
        $this->SetX(14);
        $this->Cell(120, 4.5, self::t('Condición de venta: ' . ($c['condicion_venta'] === 'cta_cte' ? 'Cuenta corriente - vence ' . fecha($c['vencimiento']) : 'Contado')), 0, 1);
        $this->SetY(69);
        $this->encabezadoItems();
    }

    private function encabezadoItems(): void
    {
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->SetFillColor(235, 235, 235);
        foreach ([[18, 'Código', 'L'], [74, 'Descripción', 'L'], [16, 'Cant.', 'R'], [20, 'Presentación', 'L'], [22, $this->discrimina() ? 'P. unit. neto' : 'P. unitario', 'R'], [12, 'Desc.', 'R'], [24, 'Importe', 'R']] as [$w, $t, $a]) {
            $this->Cell($w, 6, self::t($t), 'TB', 0, $a, true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 8);
    }

    private function discrimina(): bool
    {
        return $this->c['letra'] === 'A';
    }

    public function Footer(): void
    {
        $this->SetY(-22);
        $this->SetFont('Helvetica', '', 7);
        if ($this->c['cae']) {
            $this->Cell(0, 4, self::t('CAE N° ' . $this->c['cae'] . ' - Vencimiento CAE: ' . fecha($this->c['cae_vencimiento'])), 0, 1);
        } elseif (in_array($this->c['letra'], ['A', 'B', 'C'], true)) {
            $this->Cell(0, 4, self::t('Comprobante interno (sin CAE).'), 0, 1);
        } else {
            $this->Cell(0, 4, self::t('Documento no válido como factura.'), 0, 1);
        }
        $this->Cell(0, 4, self::t((string) Ajustes::get('empresa_leyenda')), 0, 1);
        $this->Cell(0, 4, self::t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    public function generar(): string
    {
        $c = $this->c;
        $this->AddPage();
        foreach ($c['items'] as $it) {
            $precio = (float) $it['precio_unitario'];
            if ($this->discrimina()) {
                $precio = round(($precio - (float) $it['internos'] / max(0.001, (float) $it['cantidad'])) / (1 + (float) $it['iva_alicuota'] / 100), 2);
            }
            $importe = $this->discrimina() ? (float) $it['neto'] : (float) $it['total'];
            $desc = $it['descripcion'] . ((float) $it['bonificacion_base'] > 0 ? ' (+' . cant($it['bonificacion_base']) . ' bonif.)' : '');
            $this->Cell(18, 5, self::t($it['codigo'] ?? ''), 0);
            $texto = self::t($desc);
            while ($this->GetStringWidth($texto) > 72) {
                $texto = substr($texto, 0, -1);
            }
            $this->Cell(74, 5, $texto, 0);
            $this->Cell(16, 5, self::t(cant($it['cantidad'])), 0, 0, 'R');
            $this->Cell(20, 5, self::t($it['presentacion_nombre'] . ((float) $it['factor'] != 1 ? ' x' . cant($it['factor']) : '')), 0);
            $this->Cell(22, 5, self::t(money($precio, false)), 0, 0, 'R');
            $this->Cell(12, 5, (float) $it['descuento_porc'] > 0 ? self::t(num($it['descuento_porc'], 1) . '%') : '', 0, 0, 'R');
            $this->Cell(24, 5, self::t(money($importe, false)), 0, 1, 'R');
        }
        $this->Ln(2);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->Ln(2);
        $filas = [];
        if ((float) $c['descuento_importe'] > 0) {
            $filas[] = ['Descuento general (' . num($c['descuento_porc'], 1) . '%)', '- ' . money($c['descuento_importe'])];
        }
        if ($this->discrimina()) {
            if ((float) $c['neto_21'] > 0) {
                $filas[] = ['Neto gravado 21%', money($c['neto_21'])];
            }
            if ((float) $c['neto_105'] > 0) {
                $filas[] = ['Neto gravado 10,5%', money($c['neto_105'])];
            }
            if ((float) $c['neto_exento'] > 0) {
                $filas[] = ['Exento', money($c['neto_exento'])];
            }
            if ((float) $c['iva_21'] > 0) {
                $filas[] = ['IVA 21%', money($c['iva_21'])];
            }
            if ((float) $c['iva_105'] > 0) {
                $filas[] = ['IVA 10,5%', money($c['iva_105'])];
            }
            if ((float) $c['impuestos_internos'] > 0) {
                $filas[] = ['Impuestos internos', money($c['impuestos_internos'])];
            }
        }
        foreach ($filas as [$k, $v]) {
            $this->SetX(120);
            $this->Cell(50, 5, self::t($k), 0, 0, 'R');
            $this->Cell(28, 5, self::t($v), 0, 1, 'R');
        }
        $this->SetX(120);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(50, 8, 'TOTAL', 'T', 0, 'R');
        $this->Cell(28, 8, self::t(money($c['total'])), 'T', 1, 'R');
        if ($c['letra'] === 'B') {
            $this->SetFont('Helvetica', '', 7.5);
            $this->Ln(1);
            $this->Cell(0, 4, self::t('Régimen de Transparencia Fiscal al Consumidor (Ley 27.743) - IVA contenido: ' . money((float) $c['iva_21'] + (float) $c['iva_105']) . ($c['impuestos_internos'] > 0 ? ' - Otros impuestos nacionales indirectos: ' . money($c['impuestos_internos']) : '')), 0, 1);
        }
        if ($c['observaciones']) {
            $this->SetFont('Helvetica', '', 8);
            $this->Ln(2);
            $this->MultiCell(0, 4, self::t('Observaciones: ' . $c['observaciones']));
        }
        return $this->Output('S');
    }
}
