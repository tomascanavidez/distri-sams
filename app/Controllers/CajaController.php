<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Services\CajaService;

final class CajaController extends Controller
{
    public const BILLETES = [20000, 10000, 2000, 1000, 500, 200, 100, 50, 20, 10];

    public function index(): void
    {
        $cajas = DB::filas('SELECT * FROM cajas WHERE activo = 1 ORDER BY id');
        foreach ($cajas as &$c) {
            $c['sesion'] = CajaService::sesionAbierta((int) $c['id']);
            if ($c['sesion']) {
                $c['sesion']['saldo'] = CajaService::saldoTeorico((int) $c['sesion']['id']);
                $c['sesion']['usuario'] = DB::valor('SELECT nombre FROM usuarios WHERE id = ?', [$c['sesion']['usuario_apertura_id']]);
            }
        }
        unset($c);
        $pag = new Paginador(
            'SELECT s.*, c.nombre AS caja, ua.nombre AS abrio, uc.nombre AS cerro FROM caja_sesiones s JOIN cajas c ON c.id = s.caja_id
             LEFT JOIN usuarios ua ON ua.id = s.usuario_apertura_id LEFT JOIN usuarios uc ON uc.id = s.usuario_cierre_id',
            [],
            ['fecha' => 's.id'],
            'fecha',
            'desc',
            15
        );
        $pendRendir = (float) DB::valor("SELECT COALESCE(SUM(rp.importe), 0) FROM recibo_pagos rp JOIN recibos r ON r.id = rp.recibo_id WHERE r.estado = 'emitido' AND rp.forma_pago = 'efectivo' AND rp.pendiente_rendicion = 1");
        $this->vista('caja/index', ['titulo' => 'Caja', 'cajas' => $cajas, 'pag' => $pag, 'pendRendir' => $pendRendir]);
    }

    public function abrir(): void
    {
        CajaService::abrir(Request::entero('caja_id'), Request::decimal('saldo_inicial'), Request::texto('observaciones'));
        $this->volver('Caja abierta.');
    }

    public function movimiento(): void
    {
        $sesion = DB::fila("SELECT * FROM caja_sesiones WHERE id = ? AND estado = 'abierta'", [Request::entero('sesion_id')]);
        if (!$sesion) {
            throw new ErrorNegocio('La caja no está abierta.');
        }
        $tipo = Request::texto('tipo') === 'egreso' ? 'egreso' : 'ingreso';
        $concepto = Request::texto('concepto');
        if (!$concepto) {
            throw new ErrorNegocio('Indicá el concepto del movimiento.');
        }
        $id = CajaService::movimiento((int) $sesion['id'], $tipo, $concepto, Request::decimal('importe'), 'manual');
        Auditoria::registrar('caja', 'movimiento', 'caja_movimiento', $id, null, ['tipo' => $tipo, 'importe' => Request::decimal('importe'), 'concepto' => $concepto], 'Movimiento manual de caja');
        $this->volver('Movimiento registrado.');
    }

    public function sesion(string $id): void
    {
        $s = DB::fila('SELECT s.*, c.nombre AS caja, ua.nombre AS abrio, uc.nombre AS cerro FROM caja_sesiones s JOIN cajas c ON c.id = s.caja_id
                       LEFT JOIN usuarios ua ON ua.id = s.usuario_apertura_id LEFT JOIN usuarios uc ON uc.id = s.usuario_cierre_id WHERE s.id = ?', [(int) $id]);
        if (!$s) {
            $this->buscarOFallar('caja_sesiones', (int) $id);
        }
        $movs = DB::filas('SELECT m.*, u.nombre AS usuario FROM caja_movimientos m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE m.caja_sesion_id = ? ORDER BY m.id', [$id]);
        $saldo = (float) $s['saldo_inicial'];
        foreach ($movs as &$m) {
            $saldo += $m['tipo'] === 'ingreso' ? (float) $m['importe'] : -(float) $m['importe'];
            $m['saldo'] = $saldo;
        }
        unset($m);
        $resumen = DB::filas("SELECT origen_tipo, tipo, SUM(importe) AS total, COUNT(*) AS n FROM caja_movimientos WHERE caja_sesion_id = ? GROUP BY origen_tipo, tipo", [$id]);
        $this->vista('caja/sesion', ['titulo' => 'Caja ' . $s['caja'], 's' => $s, 'movs' => $movs, 'saldo' => round($saldo, 2), 'resumen' => $resumen, 'billetes' => self::BILLETES]);
    }

    public function cerrar(string $id): void
    {
        $arqueo = [];
        $contado = 0.0;
        foreach (self::BILLETES as $b) {
            $n = (int) ($_POST['billete'][$b] ?? 0);
            if ($n > 0) {
                $arqueo[(string) $b] = $n;
                $contado += $n * $b;
            }
        }
        $monedas = Request::decimal('monedas');
        if ($monedas > 0) {
            $arqueo['monedas'] = $monedas;
            $contado += $monedas;
        }
        if (Request::texto('modo') === 'total') {
            $contado = Request::decimal('contado');
            $arqueo = null;
        }
        $r = CajaService::cerrar((int) $id, round($contado, 2), $arqueo, Request::texto('observaciones'));
        $msg = 'Caja cerrada. Teórico ' . money($r['teorico']) . ', contado ' . money($r['contado']);
        $msg .= abs($r['diferencia']) > 0.009 ? '. Diferencia: ' . money($r['diferencia']) . ($r['diferencia'] < 0 ? ' (faltante)' : ' (sobrante)') : '. Sin diferencias.';
        flash(abs($r['diferencia']) > 0.009 ? 'aviso' : 'exito', $msg);
        $this->redirigir('/caja/sesion/' . $id);
    }
}
