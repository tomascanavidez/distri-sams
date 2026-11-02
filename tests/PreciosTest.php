<?php

declare(strict_types=1);

namespace Tests;

use App\Core\DB;
use App\Services\PrecioService;

/** Cálculo de precios, redondeos, márgenes, bonificaciones y actualización masiva. */
final class PreciosTest extends Prueba
{
    public function testPrecioDesdeCostoMargenEIva(): void
    {
        // 100 × 1,25 = 125 neto; × 1,21 = 151,25 final
        $this->igual(151.25, PrecioService::calcularPrecio(100, 25, 21));
        $this->igual(138.13, PrecioService::calcularPrecio(100, 25, 10.5), 'IVA 10,5 %');
        $this->igual(125.0, PrecioService::calcularPrecio(100, 25, 0), 'exento');
        // Internos fijos por unidad se suman al final (no llevan IVA)
        $this->igual(201.25, PrecioService::calcularPrecio(100, 25, 21, 50));
    }

    public function testRedondeos(): void
    {
        $this->igual(152.0, PrecioService::calcularPrecio(100, 25, 21, 0, 1, 'arriba'));
        $this->igual(155.0, PrecioService::calcularPrecio(100, 25, 21, 0, 5, 'arriba'));
        $this->igual(150.0, PrecioService::calcularPrecio(100, 25, 21, 0, 5, 'cercano'));
        $this->igual(151.5, PrecioService::calcularPrecio(100, 25, 21, 0, 0.5, 'arriba'));
        $this->igual(150.0, PrecioService::redondear(150.0, 10, 'arriba'), 'un múltiplo exacto no cambia');
    }

    public function testMargenImplicito(): void
    {
        $this->igual(25.0, PrecioService::margenDesdePrecio(151.25, 100, 21));
        $this->igual(0.0, PrecioService::margenDesdePrecio(100, 0, 21), 'sin costo no hay margen');
    }

    public function testBonificacionDocePorUno(): void
    {
        $p = $this->producto();
        DB::insertar('promociones', ['producto_id' => $p, 'cantidad_compra' => 12, 'cantidad_bonificada' => 1, 'activo' => 1]);
        $this->igual(0.0, PrecioService::bonificacion($p, 11));
        $this->igual(1.0, PrecioService::bonificacion($p, 12));
        $this->igual(2.0, PrecioService::bonificacion($p, 30));
    }

    public function testRecalculoAlCambiarCostoConHistorial(): void
    {
        $p = $this->producto(200);
        $antes = PrecioService::precioLista($p, 1); // Mayorista 15 %, redondeo $1: 200 × 1,15 × 1,21 = 278,30 → 279
        $this->igual(279.0, $antes);
        PrecioService::actualizarCosto($p, 220, 'Aumento proveedor');
        $this->igual(307.0, PrecioService::precioLista($p, 1), '220 × 1,15 × 1,21 = 306,13 → 307');
        $this->verdadero((int) DB::valor('SELECT COUNT(*) FROM precios_historial WHERE producto_id = ? AND lista_id IS NULL', [$p]) === 1, 'historial de costo');
        $this->verdadero((int) DB::valor('SELECT COUNT(*) FROM precios_historial WHERE producto_id = ? AND lista_id = 1', [$p]) >= 1, 'historial de precio');
    }

    public function testPrecioFijoNoSeRecalcula(): void
    {
        $p = $this->producto(100);
        PrecioService::guardarPrecio($p, 2, 999.0, null, 'Fijo', 'fijo', null, true);
        PrecioService::actualizarCosto($p, 150, 'Aumento');
        $this->igual(999.0, PrecioService::precioLista($p, 2));
    }

    public function testActualizacionMasivaPorRubro(): void
    {
        $rubro = DB::insertar('rubros', ['nombre' => 'Rubro masivo ' . uniqid(), 'activo' => 1]);
        $p = $this->producto(100);
        DB::query('UPDATE productos SET rubro_id = ? WHERE id = ?', [$rubro, $p]);
        $n = PrecioService::actualizacionMasiva(['rubro_id' => $rubro], 'costo', 10, null, 'Lista nueva');
        $this->igual(1, $n);
        $this->igual(110.0, (float) DB::valor('SELECT costo FROM productos WHERE id = ?', [$p]));
    }
}
