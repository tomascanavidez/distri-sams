<?php

declare(strict_types=1);

namespace Tests;

use App\Core\DB;
use App\Services\CajaService;
use App\Services\CobranzaService;
use App\Services\CompraService;
use App\Services\CuentaCorriente;
use App\Services\PedidoService;
use App\Services\RepartoService;
use App\Support\Reportes;

/**
 * Prueba de punta a punta del ciclo comercial completo, verificando que stock,
 * cuentas corrientes y caja cierren correctamente al final.
 */
final class CicloCompletoTest extends Prueba
{
    public function testCicloCompleto(): void
    {
        $sesion = $this->cajaAbierta();
        $cajaInicial = CajaService::saldoTeorico($sesion);
        $p = $this->producto(500, 21, ['Caja' => 12]);
        $caja = $this->presentacion($p, 'Caja');
        $prov = DB::insertar('proveedores', ['razon_social' => 'Proveedor ciclo', 'condicion_iva' => 'RI', 'plazo_pago_dias' => 30, 'activo' => 1, 'creado_en' => ahora()]);

        // 1) Compra y recepción: 10 cajas a $6.000 (= $500 por unidad)
        $oc = CompraService::guardarOrden(['proveedor_id' => $prov, 'items' => [['producto_id' => $p, 'presentacion_id' => $caja, 'cantidad' => 10, 'costo_unitario' => 6000]], 'enviar' => true]);
        $item = (int) DB::valor('SELECT id FROM orden_compra_items WHERE orden_id = ?', [$oc]);
        $rec = CompraService::recibir(['orden_id' => $oc, 'items' => [['orden_item_id' => $item, 'producto_id' => $p, 'presentacion_id' => $caja, 'cantidad' => 10, 'costo_unitario' => 6000]]]);
        CompraService::registrarFactura(['proveedor_id' => $prov, 'tipo' => 'FA', 'punto_venta' => 5, 'numero' => 991, 'neto_gravado' => 60000, 'iva_21' => 12600, 'recepcion_id' => $rec]);
        $this->igual(120.0, (float) $this->stock($p)['cantidad']);
        $this->igual(72600.0, CuentaCorriente::proveedores()->saldo($prov));

        // 2) Pedido del preventista (cliente de cuenta corriente): 3 cajas
        $cli = $this->cliente('MONO', 'cta_cte', 0, 7);
        $ped = PedidoService::guardar(['cliente_id' => $cli, 'origen' => 'movil', 'uuid' => uuid4(), 'items' => [['producto_id' => $p, 'presentacion_id' => $caja, 'cantidad' => 3]], 'confirmar' => true]);
        $this->igual(36.0, (float) $this->stock($p)['reservado']);
        $precioCaja = (float) DB::valor('SELECT precio_unitario FROM pedido_items WHERE pedido_id = ?', [$ped]);

        // 3) Preparación y hoja de ruta
        PedidoService::cambiarEstado($ped, 'en_preparacion');
        PedidoService::cambiarEstado($ped, 'preparado');
        $veh = DB::insertar('vehiculos', ['patente' => 'CIC' . random_int(100, 999), 'activo' => 1]);
        $ch = DB::insertar('choferes', ['nombre' => 'Chofer ciclo', 'activo' => 1]);
        $hr = RepartoService::crear(['vehiculo_id' => $veh, 'chofer_id' => $ch, 'pedidos' => [$ped]]);
        RepartoService::iniciar($hr);
        $this->igual(84.0, (float) $this->stock($p)['cantidad'], 'quedan 7 cajas en el depósito');

        // 4) Entrega parcial: 2 cajas + 6 unidades (30 de 36); cobra $10.000 en efectivo
        $parada = (int) DB::valor('SELECT id FROM hoja_ruta_paradas WHERE hoja_ruta_id = ?', [$hr]);
        $pi = (int) DB::valor('SELECT id FROM pedido_items WHERE pedido_id = ?', [$ped]);
        RepartoService::registrarEntrega($parada, ['resultado' => 'parcial', 'items' => [$pi => 30], 'motivo' => 'faltante', 'evento_uuid' => uuid4(), 'cobros' => [['forma' => 'efectivo', 'importe' => 10000]]]);
        $facturado = round(round($precioCaja / 12, 2) * 30, 2);
        $this->igual($facturado - 10000, CuentaCorriente::clientes()->saldo($cli), 'deuda = facturado − cobrado');

        // 5) Cobranza en la oficina del resto (transferencia)
        $resto = CuentaCorriente::clientes()->saldo($cli);
        CobranzaService::crearRecibo(['cliente_id' => $cli, 'pagos' => [['forma' => 'transferencia', 'importe' => $resto, 'referencia' => 'Transf. ciclo']]]);
        $this->igual(0.0, CuentaCorriente::clientes()->saldo($cli), 'cliente al día');

        // 6) Rendición: vuelven las 6 unidades no entregadas; el chofer rinde los $10.000
        RepartoService::rendir($hr, ['devuelto' => [$p => 6], 'rendido' => ['efectivo' => 10000], 'caja_id' => 1]);
        $this->igual(90.0, (float) $this->stock($p)['cantidad'], '120 − 30 vendidas');
        $this->igual(0.0, (float) $this->stock($p)['reservado']);
        $this->igual($cajaInicial + 10000, CajaService::saldoTeorico($sesion), 'la caja recibe el efectivo del chofer');

        // 7) Consistencia global
        $kardex = (float) DB::valor('SELECT SUM(cantidad) FROM stock_movimientos WHERE producto_id = ?', [$p]);
        $this->igual(90.0, $kardex, 'kardex = stock');
        $this->igual('rendida', DB::valor('SELECT estado FROM hojas_ruta WHERE id = ?', [$hr]));
        $this->igual('entregado_parcial', DB::valor('SELECT estado FROM pedidos WHERE id = ?', [$ped]));

        // 8) Reportes reflejan la operación
        $ventas = Reportes::generar('ventas', ['desde' => hoy(), 'hasta' => hoy(), 'agrupar' => 'producto']);
        $fila = array_values(array_filter($ventas['filas'], fn ($f) => str_starts_with((string) $f['grupo'], 'Producto de prueba') && (float) $f['unidades'] === 30.0));
        $this->verdadero(count($fila) === 1, 'la venta aparece en el reporte por producto');
        $rent = Reportes::generar('rentabilidad', ['desde' => hoy(), 'hasta' => hoy()]);
        $this->verdadero((float) $rent['totales']['margen'] > 0, 'hay margen bruto positivo');
        $rend = Reportes::generar('rendiciones', ['desde' => hoy(), 'hasta' => hoy()]);
        $this->verdadero(count($rend['filas']) >= 1, 'la rendición aparece en el reporte');
        $iva = Reportes::generar('iva_ventas', ['desde' => hoy(), 'hasta' => hoy()]);
        $this->verdadero((float) $iva['totales']['total'] > 0, 'libro IVA ventas');
    }
}
