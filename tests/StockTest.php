<?php

declare(strict_types=1);

namespace Tests;

use App\Core\DB;
use App\Services\PedidoService;
use App\Services\StockService;
use App\Services\VentaService;

/** Movimientos (kardex), presentaciones, reservas, lotes FEFO, transferencias y reversión. */
final class StockTest extends Prueba
{
    public function testKardexConSaldoResultante(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 100, 'compra', 'prueba', 1);
        StockService::mover($p, 1, -30, 'venta', 'prueba', 2);
        $this->igual(70.0, (float) $this->stock($p)['cantidad']);
        $saldos = DB::columna('SELECT saldo FROM stock_movimientos WHERE producto_id = ? ORDER BY id', [$p]);
        $this->igual([100.0, 70.0], array_map('floatval', $saldos));
    }

    public function testNoPermiteStockNegativo(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 5, 'compra', 'prueba', 1);
        $this->lanza(fn () => StockService::mover($p, 1, -6, 'venta', 'prueba', 2), 'Stock insuficiente');
        $this->igual(5.0, (float) $this->stock($p)['cantidad'], 'no se modificó');
    }

    public function testConversionDePresentaciones(): void
    {
        // 1 caja = 12 displays = 240 unidades
        $p = $this->producto(10, 21, ['Display' => 20, 'Caja' => 240]);
        StockService::mover($p, 1, 1000, 'compra', 'prueba', 1);
        [$nombre, $factor] = VentaService::presentacion($p, $this->presentacion($p, 'Caja'));
        $this->igual(['Caja', 240.0], [$nombre, $factor]);
        $c = $this->cliente('CF', 'contado');
        $this->cajaAbierta();
        $id = VentaService::emitir(['clase' => 'factura', 'cliente_id' => $c, 'items' => [
            ['producto_id' => $p, 'presentacion_id' => $this->presentacion($p, 'Caja'), 'cantidad' => 2],
            ['producto_id' => $p, 'presentacion_id' => $this->presentacion($p, 'Display'), 'cantidad' => 3],
        ], 'pagos' => []]);
        $this->igual(1000 - 480 - 60, (float) $this->stock($p)['cantidad'], 'el stock se descuenta en unidades base');
        $this->igual(540.0, (float) DB::valor('SELECT SUM(cantidad_base) FROM comprobante_items WHERE comprobante_id = ?', [$id]));
    }

    public function testReservasAlConfirmarYLiberarAlAnular(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 50, 'compra', 'prueba', 1);
        $c = $this->cliente();
        $ped = PedidoService::guardar(['cliente_id' => $c, 'items' => [['producto_id' => $p, 'cantidad' => 30]], 'confirmar' => true]);
        $this->igual(30.0, (float) $this->stock($p)['reservado']);
        $this->igual(20.0, StockService::disponible($p, 1));
        // Otro pedido por más de lo disponible no se puede confirmar
        $ped2 = PedidoService::guardar(['cliente_id' => $c, 'items' => [['producto_id' => $p, 'cantidad' => 25]]]);
        $this->lanza(fn () => PedidoService::confirmar($ped2), 'disponible insuficiente');
        PedidoService::anular($ped, 'Cliente canceló');
        $this->igual(0.0, (float) $this->stock($p)['reservado']);
        $this->igual(50.0, (float) $this->stock($p)['cantidad'], 'el físico no cambia');
    }

    public function testEdicionDePedidoConfirmadoReajustaReserva(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 100, 'compra', 'prueba', 1);
        $ped = PedidoService::guardar(['cliente_id' => $this->cliente(), 'items' => [['producto_id' => $p, 'cantidad' => 10]], 'confirmar' => true]);
        PedidoService::guardar(['cliente_id' => DB::valor('SELECT cliente_id FROM pedidos WHERE id = ?', [$ped]), 'items' => [['producto_id' => $p, 'cantidad' => 40]]], $ped);
        $this->igual(40.0, (float) $this->stock($p)['reservado']);
    }

    public function testLotesSalenPorFefoYTransferenciaLosConserva(): void
    {
        $p = $this->producto(10, 21, [], true);
        StockService::mover($p, 1, 10, 'compra', 'prueba', 1, ['lote' => 'L-TARDE', 'vencimiento' => date('Y-m-d', strtotime('+90 days'))]);
        StockService::mover($p, 1, 10, 'compra', 'prueba', 2, ['lote' => 'L-PRONTO', 'vencimiento' => date('Y-m-d', strtotime('+10 days'))]);
        $lotes = StockService::mover($p, 1, -12, 'venta', 'prueba', 3);
        $this->igual('L-PRONTO', $lotes[0][0], 'primero vence, primero sale');
        $this->igual(-10.0, (float) $lotes[0][2]);
        $this->igual(-2.0, (float) $lotes[1][2]);
        $deposito2 = DB::insertar('depositos', ['nombre' => 'Depósito 2 ' . uniqid(), 'tipo' => 'deposito', 'activo' => 1]);
        StockService::transferir(1, $deposito2, [[$p, 5]]);
        $this->igual(5.0, (float) DB::valor('SELECT cantidad FROM stock_lotes WHERE producto_id = ? AND deposito_id = ? AND lote = ?', [$p, $deposito2, 'L-TARDE']));
        $this->igual(3.0, (float) $this->stock($p)['cantidad']);
        $suma = (float) DB::valor('SELECT SUM(cantidad) FROM stock_lotes WHERE producto_id = ? AND deposito_id = 1', [$p]);
        $this->igual(3.0, $suma, 'lotes = stock');
    }

    public function testReversionDeDocumento(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 20, 'compra', 'doc_prueba', 77);
        StockService::revertirDocumento('doc_prueba', 77, 'Anulación');
        $this->igual(0.0, (float) $this->stock($p)['cantidad']);
        $kardex = (float) DB::valor('SELECT SUM(cantidad) FROM stock_movimientos WHERE producto_id = ?', [$p]);
        $this->igual(0.0, $kardex);
    }

    public function testAjusteConMotivo(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 10, 'compra', 'prueba', 1);
        $id = StockService::ajustar(1, 'rotura', [[$p, -3]], 'Se cayó una caja');
        $this->igual(7.0, (float) $this->stock($p)['cantidad']);
        $this->igual('rotura', DB::valor('SELECT motivo FROM ajustes_stock WHERE id = ?', [$id]));
        $this->lanza(fn () => StockService::ajustar(1, 'inventado', [[$p, 1]]), 'Motivo');
    }
}
