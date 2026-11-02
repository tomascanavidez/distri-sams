<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Log;
use App\Core\Request;
use App\Services\RepartoService;

/**
 * App del chofer (PWA): entregas del día, cobro y registro offline con sincronización idempotente.
 */
final class ChoferController extends Controller
{
    public function app(): void
    {
        $this->vista('chofer/app', ['titulo' => 'Reparto — ' . ajuste('empresa_nombre')], 'layouts/movil');
    }

    /** Chofer del usuario (o todos si es administrador/encargado sin chofer asociado). */
    private function choferId(): ?int
    {
        $id = Auth::usuario()['chofer_id'] ?? null;
        return $id ? (int) $id : null;
    }

    public function datos(): void
    {
        $chofer = $this->choferId();
        $sql = "SELECT h.*, v.patente FROM hojas_ruta h JOIN vehiculos v ON v.id = h.vehiculo_id
                WHERE h.estado IN ('armado','en_reparto') AND h.fecha >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)";
        $p = [];
        if ($chofer) {
            $sql .= ' AND h.chofer_id = ?';
            $p[] = $chofer;
        }
        $hojas = DB::filas($sql . ' ORDER BY h.estado = \'en_reparto\' DESC, h.fecha DESC, h.id DESC', $p);
        $out = [];
        foreach ($hojas as $h) {
            $paradas = DB::filas(
                'SELECT hp.id, hp.orden, hp.estado, hp.motivo, hp.pedido_id, pe.numero AS pedido, pe.total, pe.descuento_porc, pe.observaciones,
                        c.id AS cliente_id, c.nombre_comercio, c.titular, c.direccion, c.localidad, c.latitud, c.longitud, c.telefono, c.whatsapp, c.horario_entrega, c.condicion_pago,
                        (SELECT COALESCE(SUM(importe), 0) FROM cc_clientes WHERE cliente_id = c.id) AS saldo
                 FROM hoja_ruta_paradas hp JOIN pedidos pe ON pe.id = hp.pedido_id JOIN clientes c ON c.id = hp.cliente_id WHERE hp.hoja_ruta_id = ? ORDER BY hp.orden',
                [$h['id']]
            );
            $items = [];
            foreach (DB::filas('SELECT pi.*, p.descripcion, p.unidad_base FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id JOIN hoja_ruta_paradas hp ON hp.pedido_id = pi.pedido_id WHERE hp.hoja_ruta_id = ? ORDER BY p.descripcion', [$h['id']]) as $it) {
                $items[$it['pedido_id']][] = [
                    'id' => (int) $it['id'], 'd' => $it['descripcion'], 'u' => $it['unidad_base'], 'cant' => (float) $it['cantidad'], 'pres' => $it['presentacion_nombre'],
                    'f' => (float) $it['factor'], 'base' => (float) $it['cantidad_base'], 'bon' => (float) $it['bonificacion_base'],
                    'precio' => (float) $it['precio_unitario'], 'desc' => (float) $it['descuento_porc'], 'sub' => (float) $it['subtotal'],
                ];
            }
            $out[] = [
                'id' => (int) $h['id'], 'numero' => (int) $h['numero'], 'fecha' => $h['fecha'], 'estado' => $h['estado'], 'patente' => $h['patente'],
                'paradas' => array_map(fn ($pa) => [
                    'id' => (int) $pa['id'], 'orden' => (int) $pa['orden'], 'estado' => $pa['estado'], 'motivo' => $pa['motivo'], 'pedido' => (int) $pa['pedido'],
                    'total' => (float) $pa['total'], 'desc' => (float) $pa['descuento_porc'], 'obs' => $pa['observaciones'],
                    'cliente' => [
                        'id' => (int) $pa['cliente_id'], 'n' => $pa['nombre_comercio'], 't' => $pa['titular'], 'dir' => $pa['direccion'], 'loc' => $pa['localidad'],
                        'lat' => $pa['latitud'] ? (float) $pa['latitud'] : null, 'lng' => $pa['longitud'] ? (float) $pa['longitud'] : null,
                        'tel' => $pa['telefono'], 'wa' => whatsapp_numero($pa['whatsapp'] ?: $pa['telefono']), 'hor' => $pa['horario_entrega'],
                        'cp' => $pa['condicion_pago'], 'saldo' => round((float) $pa['saldo'], 2),
                    ],
                    'items' => $items[$pa['pedido_id']] ?? [],
                ], $paradas),
            ];
        }
        $this->json([
            'ok' => true, 'version' => ahora(), 'hoy' => hoy(), 'usuario' => Auth::usuario()['nombre'], 'empresa' => ajuste('empresa_nombre'),
            'hojas' => $out, 'motivos' => RepartoService::MOTIVOS_RECHAZO,
            'formas' => array_diff_key(formas_pago(), ['retencion' => 1, 'cheque' => 1]),
        ]);
    }

    /**
     * Cola de eventos de entrega. Idempotente por evento_uuid (RepartoService).
     * Resultado por evento: ok | conflicto (ya registrada por otra vía) | error (reintentar)
     */
    public function sincronizar(): void
    {
        $chofer = $this->choferId();
        $resultados = [];
        foreach (array_slice((array) (Request::json()['items'] ?? []), 0, 100) as $ev) {
            $uuid = (string) ($ev['uuid'] ?? '');
            if (!es_uuid($uuid)) {
                $resultados[] = ['uuid' => $uuid, 'estado' => 'descartado', 'mensaje' => 'Identificador inválido.'];
                continue;
            }
            try {
                if (($ev['tipo'] ?? 'entrega') === 'salida') {
                    $hr = DB::fila('SELECT * FROM hojas_ruta WHERE id = ?', [(int) ($ev['hoja_ruta_id'] ?? 0)]);
                    if (!$hr || ($chofer && (int) $hr['chofer_id'] !== $chofer)) {
                        throw new ErrorNegocio('Hoja de ruta inexistente.');
                    }
                    if ($hr['estado'] === 'armado') {
                        RepartoService::iniciar((int) $hr['id']);
                    }
                    $resultados[] = ['uuid' => $uuid, 'estado' => 'ok'];
                    continue;
                }
                $paradaId = (int) ($ev['parada_id'] ?? 0);
                $pa = DB::fila('SELECT hp.*, h.chofer_id FROM hoja_ruta_paradas hp JOIN hojas_ruta h ON h.id = hp.hoja_ruta_id WHERE hp.id = ?', [$paradaId]);
                if (!$pa || ($chofer && (int) $pa['chofer_id'] !== $chofer)) {
                    throw new ErrorNegocio('La parada no pertenece a tus hojas de ruta.');
                }
                $r = RepartoService::registrarEntrega($paradaId, [
                    'resultado' => $ev['resultado'] ?? '', 'items' => (array) ($ev['items'] ?? []), 'motivo' => $ev['motivo'] ?? null,
                    'observaciones' => $ev['observaciones'] ?? null, 'cobros' => (array) ($ev['cobros'] ?? []), 'evento_uuid' => $uuid,
                ]);
                $resultados[] = ['uuid' => $uuid, 'estado' => 'ok', 'repetido' => $r['repetido']];
            } catch (ErrorNegocio $e) {
                $resultados[] = ['uuid' => $uuid, 'estado' => 'conflicto', 'mensaje' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::error('Sincronización chofer: ' . $e->getMessage(), ['uuid' => $uuid]);
                $resultados[] = ['uuid' => $uuid, 'estado' => 'error', 'mensaje' => 'No se pudo procesar ahora, se reintentará.'];
            }
        }
        $this->json(['ok' => true, 'resultados' => $resultados, 'hora' => ahora()]);
    }
}
