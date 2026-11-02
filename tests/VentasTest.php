<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Ajustes;
use App\Core\DB;
use App\Services\CajaService;
use App\Services\CuentaCorriente;
use App\Services\PedidoService;
use App\Services\StockService;
use App\Services\VentaService;

/** Ventas: tipos de comprobante, pagos mixtos, crédito, NC por devolución y anulaciones completas. */
final class VentasTest extends Prueba
{
    public function testFacturaASegunCliente(): void
    {
        $p = $this->producto(100);
        StockService::mover($p, 1, 50, 'compra', 'prueba', 1);
        $id = VentaService::emitir(['clase' => 'factura', 'cliente_id' => $this->cliente('RI'), 'items' => [['producto_id' => $p, 'cantidad' => 2]]]);
        $c = DB::fila('SELECT * FROM comprobantes WHERE id = ?', [$id]);
        $this->igual('FA', $c['tipo']);
        $this->igual((float) $c['total'], round((float) $c['neto_21'] + (float) $c['iva_21'], 2));
        $this->igual('cta_cte', $c['condicion_venta']);
        $this->igual((float) $c['total'], CuentaCorriente::clientes()->saldo((int) $c['cliente_id']));
    }

    public function testPagoMixtoGeneraReciboCajaBancoYCheque(): void
    {
        $p = $this->producto(1000);
        StockService::mover($p, 1, 10, 'compra', 'prueba', 1);
        $cli = $this->cliente('MONO');
        $sesion = $this->cajaAbierta();
        $saldoCaja = CajaService::saldoTeorico($sesion);
        $precio = (float) DB::valor('SELECT precio FROM producto_precios WHERE producto_id = ? AND lista_id = 1', [$p]);
        $total = round($precio * 3, 2);
        $id = VentaService::emitir(['clase' => 'factura', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 3]], 'pagos' => [
            ['forma' => 'efectivo', 'importe' => 1000],
            ['forma' => 'transferencia', 'importe' => 500, 'referencia' => 'op 1'],
            ['forma' => 'cheque', 'importe' => $total - 1500, 'cheque' => ['numero' => '123', 'banco' => 'Macro', 'fecha_pago' => date('Y-m-d', strtotime('+15 days'))]],
        ]]);
        $this->igual('contado', DB::valor('SELECT condicion_venta FROM comprobantes WHERE id = ?', [$id]));
        $this->igual($saldoCaja + 1000, CajaService::saldoTeorico($sesion), 'el efectivo entra a la caja');
        $this->igual(1, (int) DB::valor("SELECT COUNT(*) FROM cheques WHERE numero = '123' AND estado = 'cartera'"), 'cheque en cartera');
        $this->igual(0.0, CuentaCorriente::clientes()->saldo($cli), 'queda saldado');
    }

    public function testConsumidorFinalSoloContado(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 5, 'compra', 'prueba', 1);
        $this->lanza(fn () => VentaService::emitir(['clase' => 'factura', 'cliente_id' => (int) Ajustes::get('cliente_consumidor_final'), 'items' => [['producto_id' => $p, 'cantidad' => 1]]]), 'Consumidor Final');
    }

    public function testBloqueoPorLimiteDeCredito(): void
    {
        Ajustes::set('credito_modo', 'bloquear');
        $p = $this->producto(1000);
        StockService::mover($p, 1, 10, 'compra', 'prueba', 1);
        $cli = $this->cliente('MONO', 'cta_cte', 500);
        $this->lanza(fn () => VentaService::emitir(['clase' => 'factura', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 1]]]), 'límite de crédito');
        Ajustes::set('credito_modo', 'advertir');
        $id = VentaService::emitir(['clase' => 'factura', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 1]]]);
        $this->verdadero($id > 0, 'en modo advertir se permite');
    }

    public function testNotaDeCreditoPorDevolucionConYSinReingreso(): void
    {
        $p = $this->producto(100);
        StockService::mover($p, 1, 20, 'compra', 'prueba', 1);
        $cli = $this->cliente();
        $f = VentaService::emitir(['clase' => 'factura', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 10]]]);
        $this->igual(10.0, (float) $this->stock($p)['cantidad']);
        $nc = VentaService::emitir(['clase' => 'nc', 'cliente_id' => $cli, 'comprobante_asociado_id' => $f, 'items' => [
            ['producto_id' => $p, 'cantidad' => 2, 'reingresa_stock' => 1],
            ['producto_id' => $p, 'cantidad' => 1, 'reingresa_stock' => 0],
        ]]);
        $this->igual('NCB', DB::valor('SELECT tipo FROM comprobantes WHERE id = ?', [$nc]));
        $this->igual(12.0, (float) $this->stock($p)['cantidad'], 'sólo reingresa lo que está en buen estado');
        $tf = (float) DB::valor('SELECT total FROM comprobantes WHERE id = ?', [$f]);
        $tnc = (float) DB::valor('SELECT total FROM comprobantes WHERE id = ?', [$nc]);
        $this->igual($tf - $tnc, CuentaCorriente::clientes()->saldo($cli));
        $this->igual(['producto' => 3.0], ['producto' => (float) (VentaService::devuelto($f)[$p] ?? 0)]);
    }

    public function testAnulacionRevierteStockCuentaYCaja(): void
    {
        $p = $this->producto(500);
        StockService::mover($p, 1, 30, 'compra', 'prueba', 1);
        $cli = $this->cliente();
        $sesion = $this->cajaAbierta();
        $caja0 = CajaService::saldoTeorico($sesion);
        $precio = (float) DB::valor('SELECT precio FROM producto_precios WHERE producto_id = ? AND lista_id = 1', [$p]);
        $id = VentaService::emitir(['clase' => 'factura', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 4]], 'pagos' => [['forma' => 'efectivo', 'importe' => $precio * 4]]]);
        $this->igual(26.0, (float) $this->stock($p)['cantidad']);
        VentaService::anular($id, 'Error de facturación');
        $this->igual(30.0, (float) $this->stock($p)['cantidad'], 'vuelve el stock');
        $this->igual(0.0, CuentaCorriente::clientes()->saldo($cli), 'cuenta en cero');
        $this->igual($caja0, CajaService::saldoTeorico($sesion), 'la caja se revierte');
        $this->igual('anulado', DB::valor('SELECT estado FROM comprobantes WHERE id = ?', [$id]));
        $this->igual(1, (int) DB::valor('SELECT COUNT(*) FROM comprobantes WHERE id = ?', [$id]), 'el comprobante no se borra');
        $this->lanza(fn () => VentaService::anular($id, 'Otra vez'), 'ya está anulado');
    }

    public function testPedidoConvertidoEnVentaLiberaReserva(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 40, 'compra', 'prueba', 1);
        $ped = PedidoService::guardar(['cliente_id' => $this->cliente(), 'items' => [['producto_id' => $p, 'cantidad' => 15]], 'confirmar' => true]);
        $this->igual(15.0, (float) $this->stock($p)['reservado']);
        $comp = PedidoService::facturar($ped);
        $this->igual(0.0, (float) $this->stock($p)['reservado']);
        $this->igual(25.0, (float) $this->stock($p)['cantidad']);
        $this->igual('entregado', DB::valor('SELECT estado FROM pedidos WHERE id = ?', [$ped]));
        $this->igual($ped, (int) DB::valor('SELECT pedido_id FROM comprobantes WHERE id = ?', [$comp]));
    }

    public function testNumeracionCorrelativaSinHuecos(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 10, 'compra', 'prueba', 1);
        $cli = $this->cliente();
        $a = (int) DB::valor('SELECT numero FROM comprobantes WHERE id = ?', [VentaService::emitir(['clase' => 'remito', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 1]]])]);
        try {
            VentaService::emitir(['clase' => 'remito', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 999]]]);
        } catch (\App\Core\ErrorNegocio) {
        }
        $b = (int) DB::valor('SELECT numero FROM comprobantes WHERE id = ?', [VentaService::emitir(['clase' => 'remito', 'cliente_id' => $cli, 'items' => [['producto_id' => $p, 'cantidad' => 1]]])]);
        $this->igual($a + 1, $b, 'una emisión fallida no consume número');
    }
}
