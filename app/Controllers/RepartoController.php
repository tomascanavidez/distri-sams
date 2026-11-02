<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Services\CajaService;
use App\Services\RepartoService;

final class RepartoController extends Controller
{
    public function index(): void
    {
        $w = [];
        $p = [];
        if ($e = Request::get('estado')) {
            $w[] = 'h.estado = ?';
            $p[] = (string) $e;
        }
        if ($f = parse_fecha(Request::get('fecha'))) {
            $w[] = 'h.fecha = ?';
            $p[] = $f;
        }
        $sql = "SELECT h.*, v.patente, ch.nombre AS chofer, z.nombre AS zona,
                       (SELECT COUNT(*) FROM hoja_ruta_paradas WHERE hoja_ruta_id = h.id) AS paradas,
                       (SELECT COUNT(*) FROM hoja_ruta_paradas WHERE hoja_ruta_id = h.id AND estado IN ('entregado','parcial')) AS entregadas,
                       (SELECT COALESCE(SUM(pe.total), 0) FROM hoja_ruta_paradas hp JOIN pedidos pe ON pe.id = hp.pedido_id WHERE hp.hoja_ruta_id = h.id) AS total
                FROM hojas_ruta h JOIN vehiculos v ON v.id = h.vehiculo_id JOIN choferes ch ON ch.id = h.chofer_id LEFT JOIN zonas z ON z.id = h.zona_id"
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        $pag = new Paginador($sql, $p, ['fecha' => 'h.id'], 'fecha', 'desc');
        $this->vista('reparto/index', ['titulo' => 'Hojas de ruta', 'pag' => $pag]);
    }

    public function nueva(): void
    {
        $w = ["pe.estado IN ('confirmado','en_preparacion','preparado')", 'pe.hoja_ruta_id IS NULL'];
        $p = [];
        if ($z = (int) Request::get('zona_id', 0)) {
            $w[] = 'c.zona_id = ?';
            $p[] = $z;
        }
        if ($e = parse_fecha(Request::get('entrega'))) {
            $w[] = 'pe.fecha_entrega <= ?';
            $p[] = $e;
        }
        $pedidos = DB::filas(
            'SELECT pe.id, pe.numero, pe.estado, pe.total, pe.fecha_entrega, c.nombre_comercio, c.direccion, c.orden_ruta, z.nombre AS zona,
                    (SELECT SUM(pi.cantidad_base + pi.bonificacion_base) FROM pedido_items pi WHERE pi.pedido_id = pe.id) AS unidades
             FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id
             WHERE ' . implode(' AND ', $w) . ' ORDER BY pe.fecha_entrega, z.nombre, c.orden_ruta LIMIT 300',
            $p
        );
        $this->vista('reparto/nueva', [
            'titulo' => 'Nueva hoja de ruta', 'pedidos' => $pedidos,
            'vehiculos' => DB::pares("SELECT id, CONCAT(patente, ' - ', COALESCE(descripcion, '')) FROM vehiculos WHERE activo = 1"),
            'choferes' => DB::pares('SELECT id, nombre FROM choferes WHERE activo = 1 ORDER BY nombre'),
            'zonas' => DB::pares('SELECT id, nombre FROM zonas WHERE activo = 1 ORDER BY nombre'),
        ]);
    }

    public function crear(): void
    {
        $id = RepartoService::crear([
            'fecha' => Request::texto('fecha'), 'vehiculo_id' => Request::entero('vehiculo_id'), 'chofer_id' => Request::entero('chofer_id'),
            'zona_id' => Request::entero('zona_id') ?: null, 'observaciones' => Request::texto('observaciones'),
            'pedidos' => (array) Request::input('pedidos', []),
        ]);
        // Orden inicial de paradas según el orden de ruta de los clientes
        $paradas = DB::filas('SELECT hp.id FROM hoja_ruta_paradas hp JOIN clientes c ON c.id = hp.cliente_id WHERE hp.hoja_ruta_id = ? ORDER BY c.zona_id, c.orden_ruta', [$id]);
        RepartoService::ordenar($id, array_combine(array_column($paradas, 'id'), range(1, count($paradas))));
        $this->redirigir('/reparto/' . $id, 'Hoja de ruta creada. Revisá el orden de las paradas y dale salida.');
    }

    private function cargar(int $id): array
    {
        $hr = DB::fila('SELECT h.*, v.patente, v.descripcion AS vehiculo, ch.nombre AS chofer, ch.telefono AS chofer_tel, z.nombre AS zona, d.nombre AS deposito
                        FROM hojas_ruta h JOIN vehiculos v ON v.id = h.vehiculo_id JOIN choferes ch ON ch.id = h.chofer_id LEFT JOIN zonas z ON z.id = h.zona_id
                        JOIN depositos d ON d.id = h.deposito_origen_id WHERE h.id = ?', [$id]);
        if (!$hr) {
            $this->buscarOFallar('hojas_ruta', $id);
        }
        $paradas = DB::filas(
            'SELECT hp.*, pe.numero AS pedido_numero, pe.total, pe.estado AS pedido_estado, c.nombre_comercio, c.direccion, c.localidad, c.telefono, c.latitud, c.longitud,
                    c.horario_entrega, c.condicion_pago, (SELECT COALESCE(SUM(importe), 0) FROM cc_clientes WHERE cliente_id = c.id) AS saldo,
                    cp.total AS comprobante_total, r.total AS recibo_total
             FROM hoja_ruta_paradas hp JOIN pedidos pe ON pe.id = hp.pedido_id JOIN clientes c ON c.id = hp.cliente_id
             LEFT JOIN comprobantes cp ON cp.id = hp.comprobante_id LEFT JOIN recibos r ON r.id = hp.recibo_id
             WHERE hp.hoja_ruta_id = ? ORDER BY hp.orden',
            [$id]
        );
        return [$hr, $paradas];
    }

    public function ver(string $id): void
    {
        [$hr, $paradas] = $this->cargar((int) $id);
        $cobrado = DB::filas("SELECT rp.forma_pago, SUM(rp.importe) AS importe FROM recibos r JOIN recibo_pagos rp ON rp.recibo_id = r.id WHERE r.hoja_ruta_id = ? AND r.estado = 'emitido' GROUP BY rp.forma_pago", [$id]);
        $rendicion = DB::fila('SELECT * FROM rendiciones WHERE hoja_ruta_id = ?', [$id]);
        $disponibles = $hr['estado'] === 'armado' ? DB::pares(
            "SELECT pe.id, CONCAT('N° ', pe.numero, ' - ', c.nombre_comercio) FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id
             WHERE pe.estado IN ('confirmado','en_preparacion','preparado') AND pe.hoja_ruta_id IS NULL AND pe.deposito_id = ? ORDER BY c.nombre_comercio",
            [$hr['deposito_origen_id']]
        ) : [];
        $items = [];
        if ($hr['estado'] === 'en_reparto') {
            foreach (DB::filas('SELECT pi.id, pi.pedido_id, pi.cantidad, pi.cantidad_base, pi.presentacion_nombre, p.descripcion, p.unidad_base FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id JOIN hoja_ruta_paradas hp ON hp.pedido_id = pi.pedido_id WHERE hp.hoja_ruta_id = ? AND hp.estado = \'pendiente\'', [$id]) as $it) {
                $items[$it['pedido_id']][] = $it;
            }
        }
        $this->vista('reparto/ver', ['items' => $items, 'titulo' => 'Hoja de ruta N° ' . $hr['numero'], 'hr' => $hr, 'paradas' => $paradas, 'cobrado' => $cobrado, 'rendicion' => $rendicion, 'disponibles' => $disponibles]);
    }

    public function imprimir(string $id): void
    {
        [$hr, $paradas] = $this->cargar((int) $id);
        $items = [];
        foreach (DB::filas('SELECT pi.*, p.descripcion, p.codigo FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id JOIN hoja_ruta_paradas hp ON hp.pedido_id = pi.pedido_id WHERE hp.hoja_ruta_id = ? ORDER BY p.descripcion', [$id]) as $it) {
            $items[$it['pedido_id']][] = $it;
        }
        $this->vista('impresion/hoja_ruta', ['titulo' => 'Hoja de ruta N° ' . $hr['numero'], 'hr' => $hr, 'paradas' => $paradas, 'items' => $items, 'carga' => RepartoService::cargaConsolidada((int) $id)], 'layouts/impresion');
    }

    public function carga(string $id): void
    {
        [$hr] = $this->cargar((int) $id);
        $carga = RepartoService::cargaConsolidada((int) $id);
        foreach ($carga as &$c) {
            $c['bulto'] = DB::fila('SELECT nombre, factor FROM producto_presentaciones WHERE producto_id = ? AND activo = 1 ORDER BY factor DESC LIMIT 1', [$c['producto_id']]);
        }
        $this->vista('impresion/carga', ['titulo' => 'Carga del camión HR ' . $hr['numero'], 'hr' => $hr, 'carga' => $carga], 'layouts/impresion');
    }

    /** Ordenar, agregar o quitar paradas (sólo en armado). */
    public function paradas(string $id): void
    {
        $accion = Request::texto('accion');
        if ($accion === 'ordenar') {
            RepartoService::ordenar((int) $id, array_map('intval', (array) Request::input('orden', [])));
            $this->volver('Orden de paradas guardado.');
        }
        DB::transaccion(function () use ($id, $accion) {
            if ($accion === 'agregar') {
                RepartoService::agregarPedidos((int) $id, array_map('intval', (array) Request::input('pedidos', [])));
            } elseif ($accion === 'quitar') {
                RepartoService::quitarPedido((int) $id, Request::entero('pedido_id'));
            } else {
                throw new ErrorNegocio('Acción inválida.');
            }
        });
        $this->volver('Hoja de ruta actualizada.');
    }

    public function iniciar(string $id): void
    {
        RepartoService::iniciar((int) $id);
        $this->volver('¡En reparto! La mercadería se transfirió al depósito del camión.');
    }

    public function anular(string $id): void
    {
        RepartoService::anular((int) $id, $this->motivoAnulacion());
        $this->volver('Hoja de ruta anulada. Los pedidos quedaron disponibles nuevamente.');
    }

    /** Registro de una entrega desde la oficina (cuando el chofer no usa la app). */
    public function entrega(string $paradaId): void
    {
        $d = Request::json() ?: Request::todos();
        $cobros = [];
        foreach (($d['cobros'] ?? []) as $c) {
            if (parse_decimal($c['importe'] ?? 0) > 0) {
                $cobros[] = $c;
            }
        }
        $d['cobros'] = $cobros;
        $d['evento_uuid'] = $d['evento_uuid'] ?? uuid4();
        $r = RepartoService::registrarEntrega((int) $paradaId, $d);
        if (Request::esAjax()) {
            $this->json(['ok' => true] + $r);
            return;
        }
        $this->volver('Entrega registrada.');
    }

    public function rendicion(string $id): void
    {
        $datos = RepartoService::datosRendicion((int) $id);
        if ($datos['hr']['estado'] !== 'en_reparto') {
            $this->redirigir('/reparto/' . $id);
        }
        $this->vista('reparto/rendicion', [
            'titulo' => 'Rendición HR N° ' . $datos['hr']['numero'], 'd' => $datos,
            'cajas' => DB::pares('SELECT id, nombre FROM cajas WHERE activo = 1'), 'cajaUsuario' => CajaService::cajaDelUsuario(),
            'cajasAbiertas' => DB::columna("SELECT caja_id FROM caja_sesiones WHERE estado = 'abierta'"),
        ]);
    }

    public function rendir(string $id): void
    {
        RepartoService::rendir((int) $id, [
            'devuelto' => (array) Request::input('devuelto', []), 'rendido' => (array) Request::input('rendido', []),
            'reprogramar' => (array) Request::input('reprogramar', []), 'caja_id' => Request::entero('caja_id') ?: null,
            'observaciones' => Request::texto('observaciones'),
        ]);
        $this->redirigir('/reparto/' . $id, 'Rendición registrada. La mercadería no entregada volvió al depósito.');
    }
}
