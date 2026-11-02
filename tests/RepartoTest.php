<?php

declare(strict_types=1);

namespace Tests;

use App\Core\DB;
use App\Services\CajaService;
use App\Services\CuentaCorriente;
use App\Services\PedidoService;
use App\Services\RepartoService;
use App\Services\StockService;

/** Reparto: salida al camión, entregas (total, parcial, rechazo), idempotencia y rendición con diferencias. */
final class RepartoTest extends Prueba
{
    private function hojaDeRuta(array $pedidos): int
    {
        $veh = DB::insertar('vehiculos', ['patente' => 'T' . substr(uniqid(), -6), 'activo' => 1]);
        $ch = DB::insertar('choferes', ['nombre' => 'Chofer prueba', 'activo' => 1]);
        foreach ($pedidos as $pid) {
            PedidoService::cambiarEstado($pid, 'en_preparacion');
            PedidoService::cambiarEstado($pid, 'preparado');
        }
        return RepartoService::crear(['vehiculo_id' => $veh, 'chofer_id' => $ch, 'pedidos' => $pedidos]);
    }

    public function testCicloDeRepartoConRendicion(): void
    {
        $p = $this->producto(100);
        StockService::mover($p, 1, 100, 'compra', 'prueba', 1);
        $c1 = $this->cliente('MONO', 'contado');
        $c2 = $this->cliente('MONO', 'cta_cte');
        $c3 = $this->cliente('CF', 'contado');
        $ped1 = PedidoService::guardar(['cliente_id' => $c1, 'items' => [['producto_id' => $p, 'cantidad' => 10]], 'confirmar' => true]);
        $ped2 = PedidoService::guardar(['cliente_id' => $c2, 'items' => [['producto_id' => $p, 'cantidad' => 20]], 'confirmar' => true]);
        $ped3 = PedidoService::guardar(['cliente_id' => $c3, 'items' => [['producto_id' => $p, 'cantidad' => 5]], 'confirmar' => true]);
        $this->igual(35.0, (float) $this->stock($p)['reservado']);
        $hr = $this->hojaDeRuta([$ped1, $ped2, $ped3]);
        RepartoService::iniciar($hr);
        $camion = (int) DB::valor('SELECT d.id FROM depositos d JOIN hojas_ruta h ON h.vehiculo_id = d.vehiculo_id WHERE h.id = ?', [$hr]);
        $this->igual(65.0, (float) $this->stock($p)['cantidad'], 'salió del depósito');
        $this->igual(0.0, (float) $this->stock($p)['reservado'], 'se liberan las reservas');
        $this->igual(35.0, (float) $this->stock($p, $camion)['cantidad'], 'está en el camión');

        $paradas = DB::pares('SELECT pedido_id, id FROM hoja_ruta_paradas WHERE hoja_ruta_id = ?', [$hr]);
        $precio = (float) DB::valor('SELECT precio_unitario FROM pedido_items WHERE pedido_id = ?', [$ped1]);
        // 1) Entrega total con cobro en efectivo
        $uuid = uuid4();
        RepartoService::registrarEntrega($paradas[$ped1], ['resultado' => 'entregado', 'evento_uuid' => $uuid, 'cobros' => [['forma' => 'efectivo', 'importe' => $precio * 10]]]);
        // Reintento del mismo evento (sincronización repetida): no duplica
        $r = RepartoService::registrarEntrega($paradas[$ped1], ['resultado' => 'entregado', 'evento_uuid' => $uuid, 'cobros' => [['forma' => 'efectivo', 'importe' => $precio * 10]]]);
        $this->verdadero($r['repetido'], 'el reintento se detecta');
        $this->igual(1, (int) DB::valor('SELECT COUNT(*) FROM recibos WHERE hoja_ruta_id = ?', [$hr]));
        // 2) Entrega parcial: 15 de 20, sin cobro (cuenta corriente)
        $item2 = (int) DB::valor('SELECT id FROM pedido_items WHERE pedido_id = ?', [$ped2]);
        RepartoService::registrarEntrega($paradas[$ped2], ['resultado' => 'parcial', 'items' => [$item2 => 15], 'motivo' => 'faltante', 'evento_uuid' => uuid4()]);
        $this->igual('entregado_parcial', DB::valor('SELECT estado FROM pedidos WHERE id = ?', [$ped2]));
        $this->igual(round($precio * 15, 2), CuentaCorriente::clientes()->saldo($c2), 'sólo se factura lo entregado');
        // 3) Rechazo
        RepartoService::registrarEntrega($paradas[$ped3], ['resultado' => 'rechazado', 'motivo' => 'sin_dinero', 'evento_uuid' => uuid4()]);
        $this->lanza(fn () => RepartoService::registrarEntrega($paradas[$ped3], ['resultado' => 'entregado', 'evento_uuid' => uuid4()]), 'ya fue registrada');

        // Rendición: deben volver 5 (parcial) + 5 (rechazo) = 10; vuelven 9 (falta 1). El chofer rinde $100 menos.
        $datos = RepartoService::datosRendicion($hr);
        $this->igual(10.0, (float) $datos['productos'][0]['devolucion_esperada']);
        $this->igual(round($precio * 10, 2), (float) $datos['esperado']['efectivo']);
        $sesion = $this->cajaAbierta();
        $caja0 = CajaService::saldoTeorico($sesion);
        $rend = RepartoService::rendir($hr, ['devuelto' => [$p => 9], 'rendido' => ['efectivo' => $precio * 10 - 100], 'reprogramar' => [$paradas[$ped3] => 1], 'caja_id' => 1]);
        $r = DB::fila('SELECT * FROM rendiciones WHERE id = ?', [$rend]);
        $this->igual(-100.0, (float) $r['diferencia_efectivo']);
        $this->igual(-100.0, (float) $r['diferencia_mercaderia'], '1 unidad faltante a costo 100');
        $this->igual($caja0 + $precio * 10 - 100, CajaService::saldoTeorico($sesion), 'el efectivo rendido entra a caja');
        $this->igual(0.0, (float) $this->stock($p, $camion)['cantidad'], 'el camión queda vacío');
        $this->igual(65.0 + 9, (float) $this->stock($p)['cantidad'], 'vuelve lo no entregado');
        $this->igual('preparado', DB::valor('SELECT estado FROM pedidos WHERE id = ?', [$ped3]), 'el rechazado se reprogramó');
        $this->igual(5.0, (float) $this->stock($p)['reservado'], 'y reservó stock otra vez');
        $this->igual('rendida', DB::valor('SELECT estado FROM hojas_ruta WHERE id = ?', [$hr]));
        $this->igual(0, (int) DB::valor('SELECT COUNT(*) FROM recibo_pagos rp JOIN recibos r ON r.id = rp.recibo_id WHERE r.hoja_ruta_id = ? AND rp.pendiente_rendicion = 1', [$hr]));
        $kardex = (float) DB::valor('SELECT SUM(cantidad) FROM stock_movimientos WHERE producto_id = ?', [$p]);
        $fisico = (float) DB::valor('SELECT SUM(cantidad) FROM stock WHERE producto_id = ?', [$p]);
        $this->igual($fisico, $kardex, 'stock = suma del kardex');
    }

    public function testAnularHojaEnRepartoSinEntregas(): void
    {
        $p = $this->producto();
        StockService::mover($p, 1, 20, 'compra', 'prueba', 1);
        $ped = PedidoService::guardar(['cliente_id' => $this->cliente(), 'items' => [['producto_id' => $p, 'cantidad' => 8]], 'confirmar' => true]);
        $hr = $this->hojaDeRuta([$ped]);
        RepartoService::iniciar($hr);
        RepartoService::anular($hr, 'Se rompió el camión');
        $this->igual(20.0, (float) $this->stock($p)['cantidad'], 'la carga vuelve al depósito');
        $this->igual(8.0, (float) $this->stock($p)['reservado'], 'el pedido vuelve a reservar');
        $this->igual('preparado', DB::valor('SELECT estado FROM pedidos WHERE id = ?', [$ped]));
    }
}
