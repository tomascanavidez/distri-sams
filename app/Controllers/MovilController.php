<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Log;
use App\Core\Request;
use App\Services\ClienteService;
use App\Services\PedidoService;
use App\Services\PrecioService;

/**
 * App del preventista (PWA). Los datos se descargan completos al celular (IndexedDB) para
 * trabajar sin señal; los pedidos y visitas se envían en lote al recuperar la conexión.
 * Cada operación lleva un UUID generado en el dispositivo: reenviarla no la duplica.
 */
final class MovilController extends Controller
{
    public function app(): void
    {
        $this->vista('movil/app', ['titulo' => 'Preventa — ' . ajuste('empresa_nombre')], 'layouts/movil');
    }

    /** Estado de sesión y token CSRF fresco (la app lo pide antes de sincronizar). */
    public function sesion(): void
    {
        $u = Auth::usuario();
        $this->json([
            'ok' => true, 'csrf' => Csrf::token(), 'hora' => ahora(),
            'usuario' => ['id' => (int) $u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol_slug'], 'preventista_id' => $u['preventista_id'] ? (int) $u['preventista_id'] : null, 'chofer_id' => $u['chofer_id'] ? (int) $u['chofer_id'] : null],
        ]);
    }

    /** Clientes que atiende el usuario (todos si no es preventista). */
    private function filtroClientes(): array
    {
        $prev = Auth::usuario()['preventista_id'] ?? null;
        if (!$prev) {
            return ["c.estado <> 'inactivo' AND c.id <> ?", [(int) Ajustes::get('cliente_consumidor_final', 1)]];
        }
        return ["c.estado <> 'inactivo' AND (c.preventista_id = ? OR c.zona_id IN (SELECT id FROM zonas WHERE preventista_id = ?))", [(int) $prev, (int) $prev]];
    }

    /** Descarga completa y compacta para trabajar offline. */
    public function datos(): void
    {
        [$where, $p] = $this->filtroClientes();
        $clientes = DB::filas(
            "SELECT c.id, c.codigo, c.nombre_comercio, c.titular, c.direccion, c.localidad, c.latitud, c.longitud, c.telefono, c.whatsapp,
                    c.lista_precio_id, c.limite_credito, c.estado, c.condicion_pago, c.dias_visita, c.horario_visita, c.ultima_visita, c.ultima_compra,
                    c.orden_ruta, c.condicion_iva, z.nombre AS zona, z.dias_visita AS zona_dias,
                    (SELECT COALESCE(SUM(importe), 0) FROM cc_clientes WHERE cliente_id = c.id) AS saldo,
                    (SELECT COALESCE(SUM(pendiente), 0) FROM cc_clientes WHERE cliente_id = c.id AND importe > 0 AND pendiente > 0 AND vencimiento < CURDATE()) AS vencido
             FROM clientes c LEFT JOIN zonas z ON z.id = c.zona_id WHERE $where ORDER BY c.orden_ruta, c.nombre_comercio",
            $p
        );
        $ids = array_map(fn ($c) => (int) $c['id'], $clientes) ?: [0];
        $m = DB::marcadores($ids);

        // Productos habituales (top 15 de los últimos 120 días) y último pedido de cada cliente
        $habituales = [];
        foreach (DB::filas(
            "SELECT c.cliente_id, ci.producto_id, SUM(ci.cantidad_base) AS u FROM comprobante_items ci JOIN comprobantes c ON c.id = ci.comprobante_id
             WHERE c.cliente_id IN ($m) AND c.estado = 'emitido' AND c.tipo IN ('FA','FB','FC','RM') AND c.fecha >= DATE_SUB(CURDATE(), INTERVAL 120 DAY) AND ci.producto_id IS NOT NULL
             GROUP BY c.cliente_id, ci.producto_id ORDER BY c.cliente_id, u DESC",
            $ids
        ) as $h) {
            if (count($habituales[$h['cliente_id']] ?? []) < 15) {
                $habituales[$h['cliente_id']][] = (int) $h['producto_id'];
            }
        }
        $ultimos = [];
        $pedidosRecientes = [];
        foreach (DB::filas("SELECT id, cliente_id, numero, fecha, total, estado FROM pedidos WHERE cliente_id IN ($m) AND estado <> 'anulado' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY fecha DESC", $ids) as $pe) {
            if (!isset($ultimos[$pe['cliente_id']])) {
                $ultimos[$pe['cliente_id']] = (int) $pe['id'];
            }
            if (count($pedidosRecientes[$pe['cliente_id']] ?? []) < 4) {
                $pedidosRecientes[$pe['cliente_id']][] = [(int) $pe['numero'], substr($pe['fecha'], 0, 10), (float) $pe['total'], $pe['estado']];
            }
        }
        $itemsUltimo = [];
        if ($ultimos) {
            $mu = DB::marcadores(array_values($ultimos));
            foreach (DB::filas("SELECT pedido_id, producto_id, presentacion_id, cantidad FROM pedido_items WHERE pedido_id IN ($mu)", array_values($ultimos)) as $it) {
                $itemsUltimo[$it['pedido_id']][] = [(int) $it['producto_id'], (int) $it['presentacion_id'], (float) $it['cantidad']];
            }
        }

        $listas = array_values(array_unique(array_filter(array_map(fn ($c) => (int) $c['lista_precio_id'], $clientes))));
        if (!$listas) {
            $listas = [(int) DB::valor('SELECT id FROM listas_precios WHERE activo = 1 ORDER BY orden LIMIT 1')];
        }
        $precios = [];
        foreach (DB::filas('SELECT producto_id, lista_id, precio FROM producto_precios WHERE lista_id IN (' . DB::marcadores($listas) . ')', $listas) as $pp) {
            $precios[$pp['producto_id']][$pp['lista_id']] = (float) $pp['precio'];
        }
        $pres = [];
        $barras = [];
        foreach (DB::filas('SELECT id, producto_id, nombre, factor, codigo_barras, predeterminada_venta FROM producto_presentaciones WHERE activo = 1 AND es_venta = 1 ORDER BY factor') as $pr) {
            $pres[$pr['producto_id']][] = [(int) $pr['id'], $pr['nombre'], (float) $pr['factor'], (int) $pr['predeterminada_venta']];
            if ($pr['codigo_barras']) {
                $barras[$pr['producto_id']][] = $pr['codigo_barras'];
            }
        }
        $dep = (int) Ajustes::get('deposito_central', 1);
        $productos = [];
        foreach (DB::filas(
            'SELECT p.id, p.codigo, p.codigo_barras, p.descripcion, p.rubro_id, p.unidad_base, p.foto, m.nombre AS marca, COALESCE(s.cantidad - s.reservado, 0) AS disp
             FROM productos p LEFT JOIN marcas m ON m.id = p.marca_id LEFT JOIN stock s ON s.producto_id = p.id AND s.deposito_id = ? WHERE p.activo = 1 ORDER BY p.descripcion',
            [$dep]
        ) as $pr) {
            $id = (int) $pr['id'];
            $productos[] = [
                'i' => $id, 'c' => $pr['codigo'], 'd' => $pr['descripcion'], 'm' => $pr['marca'], 'r' => (int) $pr['rubro_id'], 'u' => $pr['unidad_base'],
                'b' => array_values(array_unique(array_filter(array_merge([$pr['codigo_barras']], $barras[$id] ?? [])))),
                'f' => $pr['foto'] ? 't_' . $pr['foto'] : null, 's' => (float) $pr['disp'], 'p' => $precios[$id] ?? new \stdClass(),
                'pr' => $pres[$id] ?? [[0, $pr['unidad_base'], 1.0, 1]],
            ];
        }
        $visitasHoy = DB::columna('SELECT DISTINCT cliente_id FROM visitas WHERE usuario_id = ? AND fecha >= ?', [Auth::id(), hoy() . ' 00:00:00']);

        $this->json([
            'ok' => true, 'version' => ahora(), 'hoy' => hoy(), 'dia' => (int) date('N', strtotime(hoy())),
            'usuario' => Auth::usuario()['nombre'],
            'empresa' => ajuste('empresa_nombre'),
            'listas' => DB::pares('SELECT id, nombre FROM listas_precios WHERE id IN (' . DB::marcadores($listas) . ')', $listas),
            'rubros' => DB::filas('SELECT id, nombre FROM rubros WHERE activo = 1 ORDER BY nombre'),
            'fotos' => url('/uploads/productos/'),
            'credito_modo' => Ajustes::get('credito_modo'),
            'productos' => $productos,
            'clientes' => array_map(fn ($c) => [
                'i' => (int) $c['id'], 'c' => $c['codigo'], 'n' => $c['nombre_comercio'], 't' => $c['titular'], 'dir' => $c['direccion'], 'loc' => $c['localidad'],
                'lat' => $c['latitud'] ? (float) $c['latitud'] : null, 'lng' => $c['longitud'] ? (float) $c['longitud'] : null,
                'tel' => $c['telefono'], 'wa' => whatsapp_numero($c['whatsapp'] ?: $c['telefono']), 'z' => $c['zona'], 'l' => (int) $c['lista_precio_id'],
                'sal' => round((float) $c['saldo'], 2), 'ven' => round((float) $c['vencido'], 2), 'lim' => (float) $c['limite_credito'], 'est' => $c['estado'],
                'cp' => $c['condicion_pago'], 'dv' => $c['dias_visita'] ?: $c['zona_dias'], 'hv' => $c['horario_visita'], 'uv' => $c['ultima_visita'], 'uc' => $c['ultima_compra'],
                'o' => (int) $c['orden_ruta'], 'iva' => $c['condicion_iva'], 'hab' => $habituales[$c['id']] ?? [],
                'ult' => isset($ultimos[$c['id']]) ? ($itemsUltimo[$ultimos[$c['id']]] ?? []) : [], 'peds' => $pedidosRecientes[$c['id']] ?? [],
            ], $clientes),
            'visitas_hoy' => array_map('intval', $visitasHoy),
        ]);
    }

    /** Pedidos recientes del preventista (para ver su estado). */
    public function misPedidos(): void
    {
        $prev = Auth::usuario()['preventista_id'] ?? null;
        $filas = DB::filas(
            'SELECT pe.uuid, pe.numero, pe.fecha, pe.total, pe.estado, pe.revision_requerida, c.nombre_comercio FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id
             WHERE ' . ($prev ? 'pe.preventista_id = ?' : 'pe.usuario_id = ?') . ' AND pe.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY pe.id DESC LIMIT 60',
            [$prev ?: Auth::id()]
        );
        $this->json(['ok' => true, 'items' => $filas]);
    }

    /**
     * Recibe la cola del dispositivo. Cada elemento se procesa por separado y de forma idempotente.
     * Respuesta por elemento: estado ok | revision | error (reintentar más tarde).
     */
    public function sincronizar(): void
    {
        $items = Request::json()['items'] ?? [];
        $resultados = [];
        foreach (array_slice((array) $items, 0, 100) as $item) {
            $uuid = (string) ($item['uuid'] ?? '');
            if (!es_uuid($uuid)) {
                $resultados[] = ['uuid' => $uuid, 'estado' => 'descartado', 'mensaje' => 'Identificador inválido.'];
                continue;
            }
            try {
                $resultados[] = match ($item['tipo'] ?? '') {
                    'pedido' => $this->sincronizarPedido($uuid, (array) ($item['datos'] ?? []), (string) ($item['creado'] ?? '')),
                    'visita' => $this->sincronizarVisita($uuid, (array) ($item['datos'] ?? []), (string) ($item['creado'] ?? '')),
                    default => ['uuid' => $uuid, 'estado' => 'descartado', 'mensaje' => 'Tipo desconocido.'],
                };
            } catch (\Throwable $e) {
                Log::error('Sincronización móvil: ' . $e->getMessage(), ['uuid' => $uuid, 'usuario' => Auth::id()]);
                $resultados[] = ['uuid' => $uuid, 'estado' => 'error', 'mensaje' => 'No se pudo procesar ahora, se reintentará.'];
            }
        }
        $this->json(['ok' => true, 'resultados' => $resultados, 'hora' => ahora()]);
    }

    private function fechaDispositivo(string $creado): string
    {
        $t = strtotime($creado);
        // Se acepta la hora del celular sólo si es razonable (últimos 7 días y no futura)
        if (!$t || $t > time() + 300 || $t < time() - 7 * 86400) {
            return ahora();
        }
        return date('Y-m-d H:i:s', $t);
    }

    private function sincronizarVisita(string $uuid, array $d, string $creado): array
    {
        if (DB::valor('SELECT id FROM visitas WHERE uuid = ?', [$uuid])) {
            return ['uuid' => $uuid, 'estado' => 'ok', 'repetido' => true];
        }
        $cliente = (int) ($d['cliente_id'] ?? 0);
        if (!DB::valor('SELECT id FROM clientes WHERE id = ?', [$cliente])) {
            return ['uuid' => $uuid, 'estado' => 'descartado', 'mensaje' => 'El cliente no existe.'];
        }
        $fecha = $this->fechaDispositivo($creado);
        DB::insertar('visitas', [
            'uuid' => $uuid, 'cliente_id' => $cliente, 'preventista_id' => Auth::usuario()['preventista_id'] ?? null, 'usuario_id' => Auth::id(),
            'fecha' => $fecha, 'resultado' => 'sin_pedido', 'motivo' => mb_substr((string) ($d['motivo'] ?? 'otro'), 0, 30),
            'observaciones' => isset($d['observaciones']) ? mb_substr((string) $d['observaciones'], 0, 255) : null,
            'latitud' => is_numeric($d['lat'] ?? null) ? (float) $d['lat'] : null, 'longitud' => is_numeric($d['lng'] ?? null) ? (float) $d['lng'] : null,
        ]);
        DB::query('UPDATE clientes SET ultima_visita = GREATEST(COALESCE(ultima_visita, ?), ?) WHERE id = ?', [$fecha, $fecha, $cliente]);
        return ['uuid' => $uuid, 'estado' => 'ok'];
    }

    private function sincronizarPedido(string $uuid, array $d, string $creado): array
    {
        $existente = DB::fila('SELECT id, numero, estado, revision_requerida FROM pedidos WHERE uuid = ?', [$uuid]);
        if ($existente) {
            return ['uuid' => $uuid, 'estado' => (int) $existente['revision_requerida'] && $existente['estado'] === 'borrador' ? 'revision' : 'ok', 'numero' => (int) $existente['numero'], 'repetido' => true];
        }
        $cliente = DB::fila('SELECT * FROM clientes WHERE id = ?', [(int) ($d['cliente_id'] ?? 0)]);
        if (!$cliente) {
            return ['uuid' => $uuid, 'estado' => 'descartado', 'mensaje' => 'El cliente no existe.'];
        }
        $problemas = [];
        if ($cliente['estado'] !== 'activo') {
            $problemas[] = "El cliente está {$cliente['estado']}.";
        }
        $lista = (int) $cliente['lista_precio_id'] ?: null;
        $dep = (int) Ajustes::get('deposito_central', 1);
        $items = [];
        $total = 0.0;
        foreach ((array) ($d['items'] ?? []) as $it) {
            $pid = (int) ($it['producto_id'] ?? 0);
            $prod = DB::fila('SELECT id, descripcion, activo FROM productos WHERE id = ?', [$pid]);
            if (!$prod) {
                $problemas[] = "Un producto (#$pid) ya no existe y se quitó del pedido.";
                continue;
            }
            if (!(int) $prod['activo']) {
                $problemas[] = "\"{$prod['descripcion']}\" fue dado de baja.";
            }
            $factor = (float) ($it['factor'] ?? 1) ?: 1.0;
            $cant = parse_decimal($it['cantidad'] ?? 0);
            $precioServidor = round(PrecioService::precioLista($pid, $lista) * $factor, 2);
            $precioCelular = round(parse_decimal($it['precio'] ?? 0), 2);
            if (abs($precioServidor - $precioCelular) > 0.009) {
                $problemas[] = sprintf('El precio de "%s" cambió: en el celular figuraba %s y ahora es %s.', $prod['descripcion'], money($precioCelular), money($precioServidor));
            }
            $disp = (float) DB::valor('SELECT COALESCE(cantidad - reservado, 0) FROM stock WHERE producto_id = ? AND deposito_id = ?', [$pid, $dep]);
            if ($cant * $factor > $disp + 0.0005 && Ajustes::get('stock_permitir_negativo') !== '1') {
                $problemas[] = sprintf('Stock insuficiente de "%s": hay %s, se piden %s.', $prod['descripcion'], cant(max(0, $disp)), cant($cant * $factor));
            }
            $items[] = [
                'producto_id' => $pid, 'presentacion_id' => (int) ($it['presentacion_id'] ?? 0) ?: null, 'factor' => $factor,
                'presentacion_nombre' => $it['presentacion_nombre'] ?? null, 'cantidad' => $cant, 'descuento_porc' => 0,
            ];
            $total += $precioServidor * $cant;
        }
        if (!$items) {
            return ['uuid' => $uuid, 'estado' => 'descartado', 'mensaje' => 'El pedido no tiene productos.'];
        }
        if ($cliente['condicion_pago'] === 'cta_cte' && Ajustes::get('credito_modo') === 'bloquear') {
            $problemas = array_merge($problemas, array_filter(ClienteService::problemasCredito((int) $cliente['id'], $total), fn ($x) => !str_contains($x, 'bloqueado')));
        }
        $base = [
            'cliente_id' => $cliente['id'], 'items' => $items, 'observaciones' => $d['observaciones'] ?? null, 'uuid' => $uuid,
            'origen' => 'movil', 'fecha' => $this->fechaDispositivo($creado), 'aplicar_promociones' => true,
        ];
        $id = null;
        if (!$problemas && Ajustes::get('pedido_movil_auto_confirmar', '1') === '1') {
            try {
                $id = PedidoService::guardar($base + ['confirmar' => true]);
            } catch (ErrorNegocio $e) {
                $problemas[] = $e->getMessage();
            }
        }
        if (!$id) {
            $id = PedidoService::guardar($base + ['revision_detalle' => $problemas ? json_encode(array_values(array_unique($problemas)), JSON_UNESCAPED_UNICODE) : null]);
        }
        $pedido = DB::fila('SELECT numero, estado FROM pedidos WHERE id = ?', [$id]);
        // Visita con pedido (UUID derivado del pedido)
        $h = md5('visita:' . $uuid);
        $uv = sprintf('%s-%s-4%s-a%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), substr($h, 17, 3), substr($h, 20, 12));
        if (!DB::valor('SELECT id FROM visitas WHERE uuid = ?', [$uv])) {
            DB::insertar('visitas', [
                'uuid' => $uv, 'cliente_id' => $cliente['id'], 'preventista_id' => Auth::usuario()['preventista_id'] ?? null, 'usuario_id' => Auth::id(),
                'fecha' => $base['fecha'], 'resultado' => 'pedido', 'pedido_id' => $id,
                'latitud' => is_numeric($d['lat'] ?? null) ? (float) $d['lat'] : null, 'longitud' => is_numeric($d['lng'] ?? null) ? (float) $d['lng'] : null,
            ]);
            DB::query('UPDATE clientes SET ultima_visita = ? WHERE id = ?', [$base['fecha'], $cliente['id']]);
        }
        return [
            'uuid' => $uuid, 'estado' => $problemas ? 'revision' : 'ok', 'numero' => (int) $pedido['numero'], 'pedido_estado' => $pedido['estado'],
            'mensajes' => array_values(array_unique($problemas)),
        ];
    }
}
