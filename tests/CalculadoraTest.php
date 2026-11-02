<?php

declare(strict_types=1);

namespace Tests;

use App\Services\CalculadoraComprobante as Calc;

/** Discriminación de IVA, descuentos e impuestos internos de los comprobantes. */
final class CalculadoraTest extends Prueba
{
    public function testLetraSegunCondiciones(): void
    {
        $this->igual('A', Calc::letra('RI', 'RI'));
        $this->igual('B', Calc::letra('RI', 'MONO'));
        $this->igual('B', Calc::letra('RI', 'CF'));
        $this->igual('B', Calc::letra('RI', 'EX'));
        $this->igual('C', Calc::letra('MONO', 'RI'));
        $this->igual('FA', Calc::tipo('factura', 'A'));
        $this->igual('NCB', Calc::tipo('nc', 'B'));
        $this->igual('RM', Calc::tipo('remito', 'A'));
    }

    public function testDiscriminaIvaPorAlicuota(): void
    {
        $r = Calc::calcular([
            ['precio_unitario' => 121, 'cantidad' => 2, 'factor' => 1, 'iva_alicuota' => 21],
            ['precio_unitario' => 110.5, 'cantidad' => 1, 'factor' => 1, 'iva_alicuota' => 10.5],
        ], 0, 'A');
        $this->igual(352.5, $r['total']);
        $this->igual(200.0, $r['neto_21']);
        $this->igual(42.0, $r['iva_21']);
        $this->igual(100.0, $r['neto_105']);
        $this->igual(10.5, $r['iva_105']);
        $this->igual($r['total'], $r['neto_21'] + $r['iva_21'] + $r['neto_105'] + $r['iva_105'], 'neto + IVA = total');
    }

    public function testDescuentosDeLineaYGeneral(): void
    {
        $r = Calc::calcular([['precio_unitario' => 1000, 'cantidad' => 3, 'factor' => 12, 'descuento_porc' => 10, 'iva_alicuota' => 21]], 5);
        $this->igual(2700.0, $r['subtotal'], '3 × 1000 − 10 %');
        $this->igual(2565.0, $r['total'], 'con 5 % general');
        $this->igual(135.0, $r['descuento_importe']);
    }

    public function testImpuestosInternosNoLlevanIva(): void
    {
        // Precio final 151,25 que incluye 30 de internos por unidad base
        $r = Calc::calcular([['precio_unitario' => 151.25, 'cantidad' => 1, 'factor' => 1, 'iva_alicuota' => 21, 'impuesto_interno' => 30]]);
        $this->igual(30.0, $r['impuestos_internos']);
        $this->igual(100.21, $r['neto_21']);
        $this->igual(151.25, $r['neto_21'] + $r['iva_21'] + $r['impuestos_internos']);
    }

    public function testFacturaCNoDiscrimina(): void
    {
        $r = Calc::calcular([['precio_unitario' => 121, 'cantidad' => 1, 'factor' => 1, 'iva_alicuota' => 21]], 0, 'C');
        $this->igual(0.0, $r['iva_21']);
        $this->igual(121.0, $r['neto_exento']);
    }

    public function testParseoDeNumerosYFechasArgentinos(): void
    {
        $this->igual(1234.56, parse_decimal('1.234,56'));
        $this->igual(1234.56, parse_decimal('1234,56'));
        $this->igual(1234.56, parse_decimal('1234.56'));
        $this->igual('$ 1.234,56', money(1234.56));
        $this->igual('2026-03-05', parse_fecha('05/03/2026'));
        $this->igual(null, parse_fecha('31/02/2026'));
        $this->igual('1,5', cant(1.5));
    }
}
