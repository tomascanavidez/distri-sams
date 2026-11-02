<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Ajustes;
use App\Core\DB;
use App\Services\CompraService;
use App\Services\CuentaCorriente;
use App\Services\PrecioService;

/** Recepción contra orden (parcial/total), costo último y promedio ponderado, facturas, pagos y devoluciones. */
final class ComprasTest extends Prueba
{
    private function proveedor(): int
    {
        return DB::insertar('proveedores', ['razon_social' => 'Prov ' . uniqid(), 'condicion_iva' => 'RI', 'plazo_pago_dias' => 15, 'activo' => 1, 'creado_en' => ahora()]);
    }

    public function testRecepcionParcialYTotalConCostos(): void
    {
        $p = $this->producto(100, 21, ['Caja' => 10]);
        $prov = $this->proveedor();
        $oc = CompraService::guardarOrden(['proveedor_id' => $prov, 'items' => [['producto_id' => $p, 'presentacion_id' => $this->presentacion($p, 'Caja'), 'cantidad' => 5, 'costo_unitario' => 1000]], 'enviar' => true]);
        $item = (int) DB::valor('SELECT id FROM orden_compra_items WHERE orden_id = ?', [$oc]);
        // Llegan 2 cajas a $1.000 (= $100 por unidad)
        CompraService::recibir(['orden_id' => $oc, 'items' => [['orden_item_id' => $item, 'producto_id' => $p, 'presentacion_id' => $this->presentacion($p, 'Caja'), 'cantidad' => 2, 'costo_unitario' => 1000]]]);
        $this->igual('recibida_parcial', DB::valor('SELECT estado FROM ordenes_compra WHERE id = ?', [$oc]));
        $this->igual(20.0, (float) $this->stock($p)['cantidad']);
        // Llegan las 3 restantes más caras: $1.300 la caja ($130 por unidad)
        CompraService::recibir(['orden_id' => $oc, 'items' => [['orden_item_id' => $item, 'producto_id' => $p, 'presentacion_id' => $this->presentacion($p, 'Caja'), 'cantidad' => 3, 'costo_unitario' => 1300]]]);
        $this->igual('recibida', DB::valor('SELECT estado FROM ordenes_compra WHERE id = ?', [$oc]));
        $prod = DB::fila('SELECT costo, costo_promedio FROM productos WHERE id = ?', [$p]);
        $this->igual(130.0, (float) $prod['costo'], 'último costo');
        $this->igual(118.0, (float) $prod['costo_promedio'], 'promedio ponderado (20 × 100 + 30 × 130) / 50');
        Ajustes::set('costo_metodo', 'promedio');
        $this->igual(118.0, PrecioService::costoBase(DB::fila('SELECT * FROM productos WHERE id = ?', [$p])));
        Ajustes::set('costo_metodo', 'ultimo');
    }

    public function testAnularRecepcionDescuentaStockYReabreOrden(): void
    {
        $p = $this->producto();
        $prov = $this->proveedor();
        $oc = CompraService::guardarOrden(['proveedor_id' => $prov, 'items' => [['producto_id' => $p, 'cantidad' => 10, 'costo_unitario' => 100]], 'enviar' => true]);
        $item = (int) DB::valor('SELECT id FROM orden_compra_items WHERE orden_id = ?', [$oc]);
        $rec = CompraService::recibir(['orden_id' => $oc, 'items' => [['orden_item_id' => $item, 'producto_id' => $p, 'cantidad' => 10, 'costo_unitario' => 100]]]);
        CompraService::anularRecepcion($rec, 'Mercadería equivocada');
        $this->igual(0.0, (float) $this->stock($p)['cantidad']);
        $this->igual('enviada', DB::valor('SELECT estado FROM ordenes_compra WHERE id = ?', [$oc]));
    }

    public function testFacturaPagoYCuentaDelProveedor(): void
    {
        $prov = $this->proveedor();
        $this->cajaAbierta();
        $f = CompraService::registrarFactura(['proveedor_id' => $prov, 'tipo' => 'FA', 'punto_venta' => 3, 'numero' => 1234, 'fecha' => hoy(), 'neto_gravado' => 1000, 'iva_21' => 210, 'percepcion_iibb' => 30]);
        $this->igual(1240.0, (float) DB::valor('SELECT total FROM facturas_proveedor WHERE id = ?', [$f]));
        $this->lanza(fn () => CompraService::registrarFactura(['proveedor_id' => $prov, 'tipo' => 'FA', 'punto_venta' => 3, 'numero' => 1234, 'neto_gravado' => 1]), 'ya fue cargado');
        CompraService::registrarPago(['proveedor_id' => $prov, 'detalle' => [['forma' => 'efectivo', 'importe' => 240], ['forma' => 'transferencia', 'importe' => 1000]]]);
        $this->igual(0.0, CuentaCorriente::proveedores()->saldo($prov));
    }

    public function testDevolucionAProveedorConNotaDeCredito(): void
    {
        $p = $this->producto(50);
        $prov = $this->proveedor();
        CompraService::recibir(['proveedor_id' => $prov, 'items' => [['producto_id' => $p, 'cantidad' => 20, 'costo_unitario' => 50]]]);
        $dev = CompraService::devolucion(['proveedor_id' => $prov, 'motivo' => 'Vencidos', 'items' => [['producto_id' => $p, 'cantidad' => 4]], 'nc' => ['punto_venta' => 2, 'numero' => 77]]);
        $this->igual(16.0, (float) $this->stock($p)['cantidad']);
        $this->verdadero((int) DB::valor('SELECT nota_credito_id FROM devoluciones_proveedor WHERE id = ?', [$dev]) > 0, 'NC registrada');
        $this->igual(-242.0, CuentaCorriente::proveedores()->saldo($prov), 'NC A: 200 + IVA 21 %');
    }
}
