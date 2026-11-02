<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Services\CajaService;
use App\Services\CobranzaService;
use App\Services\CuentaCorriente;
use App\Support\Exportador;

final class CobranzasController extends Controller
{
    private function filtros(): array
    {
        $w = [];
        $p = [];
        if ($q = trim((string) Request::get('q', ''))) {
            $w[] = '(c.nombre_comercio LIKE ? OR r.numero = ?)';
            array_push($p, "%$q%", (int) $q);
        }
        if ($cl = (int) Request::get('cliente_id', 0)) {
            $w[] = 'r.cliente_id = ?';
            $p[] = $cl;
        }
        foreach (['origen', 'estado'] as $k) {
            if ($v = Request::get($k)) {
                $w[] = "r.$k = ?";
                $p[] = (string) $v;
            }
        }
        if ($d = parse_fecha(Request::get('desde'))) {
            $w[] = 'r.fecha >= ?';
            $p[] = $d . ' 00:00:00';
        }
        if ($h = parse_fecha(Request::get('hasta'))) {
            $w[] = 'r.fecha <= ?';
            $p[] = $h . ' 23:59:59';
        }
        $sql = "SELECT r.*, c.nombre_comercio, u.nombre AS usuario,
                       (SELECT GROUP_CONCAT(DISTINCT rp.forma_pago) FROM recibo_pagos rp WHERE rp.recibo_id = r.id) AS formas
                FROM recibos r JOIN clientes c ON c.id = r.cliente_id LEFT JOIN usuarios u ON u.id = r.usuario_id"
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        return [$sql, $p];
    }

    public function index(): void
    {
        [$sql, $p] = $this->filtros();
        $pag = new Paginador($sql, $p, ['fecha' => 'r.id', 'cliente' => 'c.nombre_comercio', 'total' => 'r.total'], 'fecha', 'desc');
        $total = (float) DB::valor("SELECT COALESCE(SUM(t.total), 0) FROM ($sql) t WHERE t.estado = 'emitido'", $p);
        $this->vista('cobranzas/index', ['titulo' => 'Cobranzas', 'pag' => $pag, 'total' => $total]);
    }

    public function exportar(): void
    {
        [$sql, $p] = $this->filtros();
        $filas = DB::filas($sql . ' ORDER BY r.fecha LIMIT 10000', $p);
        foreach ($filas as &$f) {
            $f['nro'] = CobranzaService::numero($f);
            $f['formas'] = implode(', ', array_map('forma_pago', explode(',', (string) $f['formas'])));
        }
        Exportador::enviar((string) Request::get('formato', 'csv'), 'Cobranzas', [
            'fecha' => ['Fecha', 'fecha_hora'], 'nro' => ['Recibo', 'texto'], 'nombre_comercio' => ['Cliente', 'texto'], 'origen' => ['Origen', 'texto'],
            'formas' => ['Formas de pago', 'texto'], 'total' => ['Total', 'money'], 'estado' => ['Estado', 'texto'],
        ], $filas, ['nro' => 'TOTAL', 'total' => array_sum(array_map(fn ($f) => $f['estado'] === 'emitido' ? (float) $f['total'] : 0, $filas))]);
    }

    public function nueva(): void
    {
        $cliente = (int) Request::get('cliente_id', 0);
        $this->vista('cobranzas/nueva', [
            'titulo' => 'Nueva cobranza',
            'cliente' => $cliente ? DB::fila('SELECT id, nombre_comercio AS texto FROM clientes WHERE id = ?', [$cliente]) : null,
            'cuentas' => DB::pares('SELECT id, nombre FROM cuentas_bancarias WHERE activo = 1 ORDER BY id'),
            'cajas' => DB::pares('SELECT id, nombre FROM cajas WHERE activo = 1 ORDER BY id'),
            'cajaUsuario' => CajaService::cajaDelUsuario(),
        ]);
    }

    public function pendientes(string $clienteId): void
    {
        $cc = CuentaCorriente::clientes();
        $this->json([
            'saldo' => $cc->saldo((int) $clienteId),
            'credito' => $cc->creditoDisponible((int) $clienteId),
            'items' => array_map(fn ($f) => [
                'id' => (int) $f['id'], 'descripcion' => $f['descripcion'], 'fecha' => fecha($f['fecha']), 'vencimiento' => fecha($f['vencimiento']),
                'importe' => (float) $f['importe'], 'pendiente' => (float) $f['pendiente'], 'dias' => (int) $f['dias_vencido'],
            ], $cc->pendientes((int) $clienteId)),
        ]);
    }

    public function guardar(): void
    {
        $d = Request::json();
        $imput = [];
        foreach ($d['imputaciones'] ?? [] as $i) {
            if (parse_decimal($i['importe'] ?? 0) > 0) {
                $imput[(int) $i['id']] = parse_decimal($i['importe']);
            }
        }
        if (empty($d['cliente_id'])) {
            throw new ErrorNegocio('Elegí el cliente.');
        }
        $id = CobranzaService::crearRecibo([
            'cliente_id' => (int) $d['cliente_id'], 'pagos' => $d['pagos'] ?? [], 'imputaciones' => $imput,
            'origen' => 'cobranza', 'observaciones' => $d['observaciones'] ?? null, 'caja_id' => !empty($d['caja_id']) ? (int) $d['caja_id'] : null,
        ]);
        flash('exito', 'Recibo registrado.');
        $this->json(['ok' => true, 'id' => $id, 'redirigir' => url('/cobranzas/' . $id)]);
    }

    private function cargar(int $id): array
    {
        $r = DB::fila('SELECT r.*, c.nombre_comercio, c.codigo AS cliente_codigo, c.documento, c.tipo_documento, c.direccion, u.nombre AS usuario, h.numero AS hr_numero
                       FROM recibos r JOIN clientes c ON c.id = r.cliente_id LEFT JOIN usuarios u ON u.id = r.usuario_id LEFT JOIN hojas_ruta h ON h.id = r.hoja_ruta_id WHERE r.id = ?', [$id]);
        if (!$r) {
            $this->buscarOFallar('recibos', $id);
        }
        $r['nro'] = CobranzaService::numero($r);
        $r['pagos'] = DB::filas('SELECT rp.*, cb.nombre AS cuenta, ch.numero AS cheque_numero, ch.banco AS cheque_banco, ch.fecha_pago AS cheque_fecha, ch.estado AS cheque_estado
                                 FROM recibo_pagos rp LEFT JOIN cuentas_bancarias cb ON cb.id = rp.cuenta_bancaria_id LEFT JOIN cheques ch ON ch.id = rp.cheque_id WHERE rp.recibo_id = ?', [$id]);
        $cc = DB::valor("SELECT id FROM cc_clientes WHERE documento_tipo = 'recibo' AND documento_id = ? AND tipo = 'recibo'", [$id]);
        $r['imputaciones'] = $cc ? DB::filas('SELECT i.importe, d.descripcion, d.fecha FROM cc_clientes_imputaciones i JOIN cc_clientes d ON d.id = i.debito_id WHERE i.credito_id = ?', [$cc]) : [];
        $r['a_cuenta'] = $cc ? (float) DB::valor('SELECT pendiente FROM cc_clientes WHERE id = ?', [$cc]) : 0;
        return $r;
    }

    public function ver(string $id): void
    {
        $r = $this->cargar((int) $id);
        $this->vista('cobranzas/ver', ['titulo' => $r['nro'], 'r' => $r]);
    }

    public function imprimir(string $id): void
    {
        $r = $this->cargar((int) $id);
        $this->vista('impresion/recibo', ['titulo' => $r['nro'], 'r' => $r, 'autoImprimir' => (bool) Request::get('auto')], 'layouts/impresion');
    }

    public function anular(string $id): void
    {
        CobranzaService::anular((int) $id, $this->motivoAnulacion());
        $this->volver('Recibo anulado. Se revirtieron la caja/banco y la cuenta corriente.');
    }
}
