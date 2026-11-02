<?php

declare(strict_types=1);

namespace Tests;

use App\Core\DB;
use App\Services\CobranzaService;
use App\Services\CuentaCorriente;

/** Cuenta corriente: imputaciones, FIFO, antigüedad y anulaciones con contra-asiento. */
final class CuentaCorrienteTest extends Prueba
{
    public function testImputacionYSaldo(): void
    {
        $c = $this->cliente();
        $cc = CuentaCorriente::clientes();
        $f1 = $cc->registrar($c, 'comprobante', 1000, 'Factura 1', 'prueba', 1, date('Y-m-d', strtotime('-40 days')));
        $f2 = $cc->registrar($c, 'comprobante', 500, 'Factura 2', 'prueba', 2, date('Y-m-d', strtotime('+5 days')));
        $this->igual(1500.0, $cc->saldo($c));
        // Un pago "a cuenta" se aplica a lo más viejo primero (FIFO)
        $cc->registrar($c, 'recibo', -1200, 'Recibo', 'prueba', 3);
        $this->igual(300.0, $cc->saldo($c));
        $this->igual(0.0, (float) DB::valor('SELECT pendiente FROM cc_clientes WHERE id = ?', [$f1]));
        $this->igual(300.0, (float) DB::valor('SELECT pendiente FROM cc_clientes WHERE id = ?', [$f2]));
        $ant = $cc->antiguedad($c);
        $this->igual(300.0, $ant['a_vencer']);
        $this->igual(0.0, $cc->deudaVencida($c));
    }

    public function testAntiguedadDeDeuda(): void
    {
        $c = $this->cliente();
        $cc = CuentaCorriente::clientes();
        $cc->registrar($c, 'comprobante', 100, 'F vieja', 'prueba', 10, date('Y-m-d', strtotime('-100 days')));
        $cc->registrar($c, 'comprobante', 200, 'F media', 'prueba', 11, date('Y-m-d', strtotime('-45 days')));
        $cc->registrar($c, 'comprobante', 300, 'F nueva', 'prueba', 12, date('Y-m-d', strtotime('-3 days')));
        $a = $cc->antiguedad($c);
        $this->igual(100.0, $a['mas90']);
        $this->igual(200.0, $a['d60']);
        $this->igual(300.0, $a['d30']);
        $this->igual(600.0, $cc->deudaVencida($c));
    }

    public function testImputacionExplicitaAUnComprobante(): void
    {
        $c = $this->cliente();
        $cc = CuentaCorriente::clientes();
        $f1 = $cc->registrar($c, 'comprobante', 1000, 'F1', 'prueba', 20, date('Y-m-d', strtotime('-10 days')));
        $f2 = $cc->registrar($c, 'comprobante', 800, 'F2', 'prueba', 21);
        $this->cajaAbierta();
        // El cliente decide pagar la factura más nueva
        CobranzaService::crearRecibo(['cliente_id' => $c, 'pagos' => [['forma' => 'efectivo', 'importe' => 800]], 'imputaciones' => [$f2 => 800]]);
        $this->igual(1000.0, (float) DB::valor('SELECT pendiente FROM cc_clientes WHERE id = ?', [$f1]));
        $this->igual(0.0, (float) DB::valor('SELECT pendiente FROM cc_clientes WHERE id = ?', [$f2]));
    }

    public function testAnulacionDeReciboDevuelveLaDeuda(): void
    {
        $c = $this->cliente();
        $cc = CuentaCorriente::clientes();
        $f = $cc->registrar($c, 'comprobante', 1000, 'F', 'prueba', 30);
        $this->cajaAbierta();
        $r = CobranzaService::crearRecibo(['cliente_id' => $c, 'pagos' => [['forma' => 'efectivo', 'importe' => 1000]]]);
        $this->igual(0.0, $cc->saldo($c));
        CobranzaService::anular($r, 'Error de carga');
        $this->igual(1000.0, $cc->saldo($c));
        $this->igual(1000.0, (float) DB::valor('SELECT pendiente FROM cc_clientes WHERE id = ?', [$f]), 'la factura vuelve a estar pendiente');
        $this->igual('anulado', DB::valor('SELECT estado FROM recibos WHERE id = ?', [$r]));
        $contra = (int) DB::valor("SELECT COUNT(*) FROM cc_clientes WHERE tipo = 'anulacion' AND documento_tipo = 'recibo' AND documento_id = ?", [$r]);
        $this->igual(1, $contra, 'se registra contra-asiento (no se borra nada)');
    }

    public function testAnularFacturaPagadaDejaSaldoAFavor(): void
    {
        $c = $this->cliente();
        $cc = CuentaCorriente::clientes();
        $cc->registrar($c, 'comprobante', 700, 'F', 'factura_x', 40);
        $cc->registrar($c, 'recibo', -700, 'R', 'recibo_x', 41);
        $cc->anularDocumento('factura_x', 40, 'Anulación');
        $this->igual(-700.0, $cc->saldo($c));
        $this->igual(700.0, $cc->creditoDisponible($c));
        // El crédito a favor se aplica solo a la próxima deuda
        $nueva = $cc->registrar($c, 'comprobante', 500, 'F nueva', 'prueba', 42);
        $this->igual(0.0, (float) DB::valor('SELECT pendiente FROM cc_clientes WHERE id = ?', [$nueva]));
        $this->igual(200.0, $cc->creditoDisponible($c));
    }

    public function testCuentaCorrienteDeProveedores(): void
    {
        $prov = DB::insertar('proveedores', ['razon_social' => 'Proveedor prueba', 'condicion_iva' => 'RI', 'plazo_pago_dias' => 30, 'activo' => 1, 'creado_en' => ahora()]);
        $cc = CuentaCorriente::proveedores();
        $cc->registrar($prov, 'factura', 5000, 'FA prov', 'fp', 1);
        $cc->registrar($prov, 'pago', -2000, 'Pago', 'pp', 1);
        $this->igual(3000.0, $cc->saldo($prov));
    }
}
