<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Services\CajaService;
use App\Support\Consultas;

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (!Auth::puede('dashboard.ver')) {
            $inicio = Auth::pantallaInicio();
            if ($inicio !== '/') {
                $this->redirigir($inicio);
            }
            $this->vista('dashboard/basico', ['titulo' => 'Inicio']);
            return;
        }
        $hoy = hoy();
        $lunes = date('Y-m-d', strtotime('monday this week'));
        $mes = date('Y-m-01');
        $tv = Consultas::TIPOS_VENTA;
        $sg = Consultas::signo();

        $kpi = [
            'ventas_hoy' => Consultas::ventasEntre($hoy, $hoy),
            'ventas_semana' => Consultas::ventasEntre($lunes, $hoy),
            'ventas_mes' => Consultas::ventasEntre($mes, $hoy),
            'ventas_mes_anterior' => Consultas::ventasEntre(date('Y-m-01', strtotime('first day of last month')), date('Y-m-d', strtotime('-1 month'))),
            'pedidos_pendientes' => (int) DB::valor("SELECT COUNT(*) FROM pedidos WHERE estado IN ('confirmado','en_preparacion','preparado')"),
            'pedidos_revision' => (int) DB::valor("SELECT COUNT(*) FROM pedidos WHERE estado = 'borrador' AND revision_requerida = 1"),
            'hojas_en_reparto' => (int) DB::valor("SELECT COUNT(*) FROM hojas_ruta WHERE estado = 'en_reparto'"),
            'entregas_pendientes' => (int) DB::valor("SELECT COUNT(*) FROM hoja_ruta_paradas hp JOIN hojas_ruta h ON h.id = hp.hoja_ruta_id WHERE h.estado = 'en_reparto' AND hp.estado = 'pendiente'"),
            'cobranzas_hoy' => (float) DB::valor("SELECT COALESCE(SUM(total), 0) FROM recibos WHERE estado = 'emitido' AND fecha BETWEEN ? AND ?", [$hoy . ' 00:00:00', $hoy . ' 23:59:59']),
            'saldo_caja' => 0.0,
            'deuda_clientes' => (float) DB::valor('SELECT COALESCE(SUM(importe), 0) FROM cc_clientes'),
            'deuda_vencida' => (float) DB::valor('SELECT COALESCE(SUM(pendiente), 0) FROM cc_clientes WHERE importe > 0 AND pendiente > 0 AND vencimiento < CURDATE()'),
        ];
        foreach (DB::columna("SELECT id FROM caja_sesiones WHERE estado = 'abierta'") as $sid) {
            $kpi['saldo_caja'] += CajaService::saldoTeorico((int) $sid);
        }

        $stockBajo = DB::filas(
            'SELECT p.id, p.codigo, p.descripcion, p.stock_minimo, ' . Consultas::stockTotal() . ' AS stock
             FROM productos p WHERE p.activo = 1 AND p.stock_minimo > 0
             HAVING stock < p.stock_minimo ORDER BY (stock / p.stock_minimo), p.descripcion LIMIT 10'
        );
        $diasVenc = (int) Ajustes::get('stock_alerta_vencimiento_dias', 30);
        $vencimientos = DB::filas(
            "SELECT l.vencimiento, l.lote, l.cantidad, p.id, p.descripcion, d.nombre AS deposito, DATEDIFF(l.vencimiento, CURDATE()) AS dias
             FROM stock_lotes l JOIN productos p ON p.id = l.producto_id JOIN depositos d ON d.id = l.deposito_id
             WHERE l.cantidad > 0 AND l.vencimiento IS NOT NULL AND l.vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY l.vencimiento LIMIT 10",
            [$diasVenc]
        );
        $deudores = DB::filas(
            'SELECT c.id, c.nombre_comercio, z.nombre AS zona, ' . Consultas::vencidoCliente() . ' AS vencido, ' . Consultas::saldoCliente() . ' AS saldo
             FROM clientes c LEFT JOIN zonas z ON z.id = c.zona_id
             HAVING vencido > 0 ORDER BY vencido DESC LIMIT 10'
        );
        $topProductos = DB::filas(
            "SELECT p.id, p.descripcion, SUM($sg * ci.total) AS total, SUM($sg * (ci.cantidad_base)) AS unidades
             FROM comprobante_items ci JOIN comprobantes c ON c.id = ci.comprobante_id JOIN productos p ON p.id = ci.producto_id
             WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha >= ?
             GROUP BY p.id, p.descripcion ORDER BY total DESC LIMIT 8",
            [$mes . ' 00:00:00']
        );
        $topClientes = DB::filas(
            "SELECT cl.id, cl.nombre_comercio, SUM($sg * c.total) AS total, COUNT(*) AS comprobantes
             FROM comprobantes c JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha >= ? AND cl.id <> ?
             GROUP BY cl.id, cl.nombre_comercio ORDER BY total DESC LIMIT 8",
            [$mes . ' 00:00:00', (int) Ajustes::get('cliente_consumidor_final', 1)]
        );
        // Ventas por día (últimos 30 días)
        $porDia = DB::pares(
            "SELECT DATE(c.fecha) AS dia, SUM($sg * c.total) FROM comprobantes c
             WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha >= ? GROUP BY DATE(c.fecha)",
            [date('Y-m-d', strtotime('-29 days')) . ' 00:00:00']
        );
        $serieDias = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $serieDias[] = ['dia' => date('d/m', strtotime($d)), 'total' => round((float) ($porDia[$d] ?? 0), 2)];
        }
        $porZona = DB::filas(
            "SELECT COALESCE(z.nombre, 'Sin zona') AS zona, SUM($sg * c.total) AS total
             FROM comprobantes c JOIN clientes cl ON cl.id = c.cliente_id LEFT JOIN zonas z ON z.id = cl.zona_id
             WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha >= ?
             GROUP BY z.nombre ORDER BY total DESC",
            [$mes . ' 00:00:00']
        );

        $this->vista('dashboard/index', compact('kpi', 'stockBajo', 'vencimientos', 'deudores', 'topProductos', 'topClientes', 'serieDias', 'porZona') + ['titulo' => 'Panel principal']);
    }
}
