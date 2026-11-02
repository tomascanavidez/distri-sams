<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Services\ClienteService;
use App\Services\PedidoService;
use App\Support\Exportador;

final class PedidosController extends Controller
{
    private function filtros(): array
    {
        $where = [];
        $p = [];
        $estado = (string) Request::get('estado', '');
        if ($estado === 'pendientes') {
            $where[] = "pe.estado IN ('confirmado','en_preparacion','preparado')";
        } elseif ($estado !== '' && isset(PedidoService::ESTADOS[$estado])) {
            $where[] = 'pe.estado = ?';
            $p[] = $estado;
        }
        if (Request::get('revision')) {
            $where[] = "pe.revision_requerida = 1 AND pe.estado = 'borrador'";
        }
        if ($q = trim((string) Request::get('q', ''))) {
            $where[] = '(c.nombre_comercio LIKE ? OR c.codigo LIKE ? OR pe.numero = ?)';
            array_push($p, "%$q%", "%$q%", (int) $q);
        }
        foreach (['preventista_id' => 'pe.preventista_id', 'zona_id' => 'c.zona_id', 'cliente_id' => 'pe.cliente_id'] as $k => $col) {
            if ($v = (int) Request::get($k, 0)) {
                $where[] = "$col = ?";
                $p[] = $v;
            }
        }
        if ($d = parse_fecha(Request::get('desde'))) {
            $where[] = 'pe.fecha >= ?';
            $p[] = $d . ' 00:00:00';
        }
        if ($h = parse_fecha(Request::get('hasta'))) {
            $where[] = 'pe.fecha <= ?';
            $p[] = $h . ' 23:59:59';
        }
        if ($e = parse_fecha(Request::get('entrega'))) {
            $where[] = 'pe.fecha_entrega = ?';
            $p[] = $e;
        }
        // El preventista sólo ve sus pedidos
        $u = Auth::usuario();
        if (($u['rol_slug'] ?? '') === 'preventista' && $u['preventista_id']) {
            $where[] = 'pe.preventista_id = ?';
            $p[] = (int) $u['preventista_id'];
        }
        $sql = 'SELECT pe.*, c.nombre_comercio, c.codigo AS cliente_codigo, z.nombre AS zona, pr.nombre AS preventista, h.numero AS hr_numero
                FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id
                LEFT JOIN preventistas pr ON pr.id = pe.preventista_id LEFT JOIN hojas_ruta h ON h.id = pe.hoja_ruta_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        return [$sql, $p];
    }

    public function index(): void
    {
        [$sql, $p] = $this->filtros();
        $pag = new Paginador($sql, $p, ['numero' => 'pe.id', 'cliente' => 'c.nombre_comercio', 'entrega' => 'pe.fecha_entrega', 'total' => 'pe.total', 'estado' => 'pe.estado'], 'numero', 'desc');
        $this->vista('pedidos/index', [
            'titulo' => 'Pedidos', 'pag' => $pag,
            'preventistas' => DB::pares('SELECT id, nombre FROM preventistas ORDER BY nombre'),
            'zonas' => DB::pares('SELECT id, nombre FROM zonas WHERE activo = 1 ORDER BY nombre'),
        ]);
    }

    public function exportar(): void
    {
        [$sql, $p] = $this->filtros();
        $filas = DB::filas($sql . ' ORDER BY pe.id DESC LIMIT 5000', $p);
        foreach ($filas as &$f) {
            $f['estado'] = PedidoService::ESTADOS[$f['estado']] ?? $f['estado'];
        }
        Exportador::enviar((string) Request::get('formato', 'csv'), 'Pedidos', [
            'numero' => ['N°', 'entero'], 'fecha' => ['Fecha', 'fecha_hora'], 'cliente_codigo' => ['Código', 'texto'], 'nombre_comercio' => ['Cliente', 'texto'],
            'zona' => ['Zona', 'texto'], 'preventista' => ['Preventista', 'texto'], 'fecha_entrega' => ['Entrega', 'fecha'], 'total' => ['Total', 'money'], 'estado' => ['Estado', 'texto'],
        ], $filas, ['nombre_comercio' => 'TOTAL', 'total' => array_sum(array_column($filas, 'total'))]);
    }

    public function nuevo(): void
    {
        $cliente = (int) Request::get('cliente_id', 0);
        $this->formulario(null, $cliente ? ['cliente_id' => $cliente] : []);
    }

    public function editar(string $id): void
    {
        $p = $this->buscarOFallar('pedidos', (int) $id);
        if (!in_array($p['estado'], ['borrador', 'confirmado', 'en_preparacion'], true)) {
            $this->redirigir('/pedidos/' . $id);
        }
        $this->formulario($p, $p);
    }

    private function formulario(?array $pedido, array $cab): void
    {
        $items = [];
        $deposito = (int) ($pedido['deposito_id'] ?? Ajustes::get('deposito_central', 1));
        if ($pedido) {
            $items = $this->itemsParaEditor((int) $pedido['id'], $deposito, (int) $pedido['lista_precio_id']);
        }
        $clienteTexto = !empty($cab['cliente_id']) ? DB::valor('SELECT nombre_comercio FROM clientes WHERE id = ?', [$cab['cliente_id']]) : '';
        $this->vista('pedidos/form', [
            'titulo' => $pedido ? 'Editar pedido N° ' . $pedido['numero'] : 'Nuevo pedido', 'pedido' => $pedido, 'items' => $items,
            'cab' => [
                'id' => $pedido['id'] ?? null, 'cliente_id' => $cab['cliente_id'] ?? null, 'descuento_porc' => (float) ($cab['descuento_porc'] ?? 0),
                'observaciones' => $cab['observaciones'] ?? '', 'fecha_entrega' => $cab['fecha_entrega'] ?? '',
            ],
            'clienteTexto' => $clienteTexto, 'deposito' => $deposito,
            'depositos' => DB::pares("SELECT id, nombre FROM depositos WHERE activo = 1 AND tipo = 'deposito' ORDER BY id"),
        ]);
    }

    /** Ítems de un pedido en el formato del editor JS. */
    private function itemsParaEditor(int $pedidoId, int $deposito, int $lista): array
    {
        $out = [];
        $filas = DB::filas(
            'SELECT pi.*, p.descripcion, p.codigo, p.unidad_base, p.iva_alicuota, p.costo, COALESCE(pp.precio, 0) AS precio_base,
                    COALESCE(s.cantidad - s.reservado, 0) + pi.reservado_base AS disponible
             FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id
             LEFT JOIN producto_precios pp ON pp.producto_id = p.id AND pp.lista_id = ?
             LEFT JOIN stock s ON s.producto_id = p.id AND s.deposito_id = ? WHERE pi.pedido_id = ?',
            [$lista, $deposito, $pedidoId]
        );
        foreach ($filas as $f) {
            $pres = DB::filas('SELECT id, nombre, factor + 0 AS factor, es_venta AS venta, es_compra AS compra, predeterminada_venta AS pv FROM producto_presentaciones WHERE producto_id = ? AND activo = 1 ORDER BY factor', [$f['producto_id']]);
            $out[] = [
                'producto_id' => (int) $f['producto_id'], 'descripcion' => $f['descripcion'], 'codigo' => $f['codigo'], 'unidad' => $f['unidad_base'],
                'presentaciones' => array_map(fn ($x) => ['id' => (int) $x['id'], 'nombre' => $x['nombre'], 'factor' => (float) $x['factor'], 'venta' => (int) $x['venta'], 'compra' => (int) $x['compra'], 'pv' => (int) $x['pv']], $pres),
                'presentacion_id' => (int) $f['presentacion_id'], 'factor' => (float) $f['factor'], 'presentacion_nombre' => $f['presentacion_nombre'],
                'cantidad' => (float) $f['cantidad'], 'precio' => (float) $f['precio_unitario'], 'precio_base' => (float) $f['precio_base'],
                'descuento' => (float) $f['descuento_porc'], 'bonificacion' => (float) $f['bonificacion_base'], 'iva' => (float) $f['iva_alicuota'],
                'disponible' => (float) $f['disponible'], 'costo_base' => (float) $f['costo'],
            ];
        }
        return $out;
    }

    /** Guardado por JSON desde el editor. */
    public function guardar(): void
    {
        $id = (int) Request::input('id', 0) ?: null;
        if ($id && !Auth::puede('pedidos.editar') && !Auth::puede('pedidos.crear')) {
            throw new ErrorNegocio('No tenés permiso para modificar pedidos.');
        }
        $datos = Request::json();
        $datos['origen'] = 'oficina';
        $pid = PedidoService::guardar($datos, $id);
        $n = DB::valor('SELECT numero FROM pedidos WHERE id = ?', [$pid]);
        flash('exito', 'Pedido N° ' . $n . (!empty($datos['confirmar']) ? ' confirmado.' : ' guardado.'));
        $this->json(['ok' => true, 'id' => $pid, 'redirigir' => url('/pedidos/' . $pid)]);
    }

    public function ver(string $id): void
    {
        $p = DB::fila(
            'SELECT pe.*, c.nombre_comercio, c.codigo AS cliente_codigo, c.direccion, c.telefono, c.condicion_pago, z.nombre AS zona,
                    pr.nombre AS preventista, u.nombre AS usuario, l.nombre AS lista, d.nombre AS deposito, h.numero AS hr_numero
             FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id
             LEFT JOIN preventistas pr ON pr.id = pe.preventista_id LEFT JOIN usuarios u ON u.id = pe.usuario_id
             LEFT JOIN listas_precios l ON l.id = pe.lista_precio_id LEFT JOIN depositos d ON d.id = pe.deposito_id
             LEFT JOIN hojas_ruta h ON h.id = pe.hoja_ruta_id WHERE pe.id = ?',
            [(int) $id]
        );
        if (!$p) {
            $this->buscarOFallar('pedidos', (int) $id);
        }
        $items = DB::filas('SELECT pi.*, p.descripcion, p.codigo FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id WHERE pi.pedido_id = ? ORDER BY pi.id', [$id]);
        $comprobante = $p['comprobante_id'] ? DB::fila('SELECT * FROM comprobantes WHERE id = ?', [$p['comprobante_id']]) : null;
        $this->vista('pedidos/ver', [
            'titulo' => 'Pedido N° ' . $p['numero'], 'p' => $p, 'items' => $items, 'comprobante' => $comprobante,
            'credito' => ClienteService::problemasCredito((int) $p['cliente_id'], (float) $p['total']),
        ]);
    }

    public function imprimir(string $id): void
    {
        $p = DB::fila('SELECT pe.*, c.nombre_comercio, c.codigo AS cliente_codigo, c.direccion, c.localidad, c.telefono, z.nombre AS zona, pr.nombre AS preventista
                       FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id LEFT JOIN preventistas pr ON pr.id = pe.preventista_id WHERE pe.id = ?', [(int) $id]);
        if (!$p) {
            $this->buscarOFallar('pedidos', (int) $id);
        }
        $items = DB::filas('SELECT pi.*, p.descripcion, p.codigo, p.ubicacion FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id WHERE pi.pedido_id = ? ORDER BY p.ubicacion, p.descripcion', [$id]);
        $this->vista('impresion/pedido', ['titulo' => 'Pedido N° ' . $p['numero'], 'p' => $p, 'items' => $items], 'layouts/impresion');
    }

    public function confirmar(string $id): void
    {
        PedidoService::confirmar((int) $id, (bool) Request::input('forzar') && Auth::puede('clientes.editar'));
        $this->volver('Pedido confirmado y stock reservado.');
    }

    public function cambiarEstado(string $id): void
    {
        PedidoService::cambiarEstado((int) $id, (string) Request::texto('estado'));
        $this->volver('Estado actualizado.');
    }

    public function anular(string $id): void
    {
        PedidoService::anular((int) $id, $this->motivoAnulacion());
        $this->volver('Pedido anulado. Se liberó el stock reservado.');
    }

    public function facturar(string $id): void
    {
        $pagos = [];
        if (($imp = Request::decimal('pago_importe')) > 0) {
            $pagos[] = ['forma' => Request::texto('pago_forma', 'efectivo'), 'importe' => $imp];
        }
        $compId = PedidoService::facturar((int) $id, [
            'clase' => Request::texto('clase') === 'remito' ? 'remito' : 'factura', 'pagos' => $pagos,
            'forzar_credito' => (bool) Request::input('forzar'),
        ]);
        $this->redirigir('/ventas/' . $compId, 'Pedido convertido en venta.');
    }

    public function ultimo(string $clienteId): void
    {
        $this->json(['items' => PedidoService::ultimoPedido((int) $clienteId)]);
    }

    // ---------------- Preparación / picking ----------------

    public function preparacion(): void
    {
        $where = ["pe.estado IN ('confirmado','en_preparacion','preparado')", 'pe.hoja_ruta_id IS NULL'];
        $p = [];
        if ($e = parse_fecha(Request::get('entrega'))) {
            $where[] = 'pe.fecha_entrega <= ?';
            $p[] = $e;
        }
        if ($z = (int) Request::get('zona_id', 0)) {
            $where[] = 'c.zona_id = ?';
            $p[] = $z;
        }
        $pedidos = DB::filas(
            'SELECT pe.*, c.nombre_comercio, z.nombre AS zona, (SELECT COUNT(*) FROM pedido_items WHERE pedido_id = pe.id) AS lineas
             FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY pe.fecha_entrega, z.nombre, c.orden_ruta LIMIT 300',
            $p
        );
        $this->vista('pedidos/preparacion', ['titulo' => 'Preparación de pedidos', 'pedidos' => $pedidos, 'zonas' => DB::pares('SELECT id, nombre FROM zonas WHERE activo = 1 ORDER BY nombre')]);
    }

    public function marcarPreparacion(): void
    {
        $ids = array_map('intval', (array) Request::input('ids', []));
        $estado = (string) Request::texto('estado');
        if (!$ids) {
            throw new ErrorNegocio('Seleccioná al menos un pedido.');
        }
        $ok = 0;
        $errores = [];
        foreach ($ids as $id) {
            try {
                $actual = DB::valor('SELECT estado FROM pedidos WHERE id = ?', [$id]);
                if ($estado === 'preparado' && $actual === 'confirmado') {
                    PedidoService::cambiarEstado($id, 'en_preparacion');
                }
                PedidoService::cambiarEstado($id, $estado);
                $ok++;
            } catch (ErrorNegocio $e) {
                $errores[] = $e->getMessage();
            }
        }
        if ($errores) {
            flash('aviso', implode("\n", array_slice($errores, 0, 10)));
        }
        $this->volver("$ok pedido(s) actualizados.");
    }

    /** Hoja de preparación: consolidado por ubicación y detalle por pedido. */
    public function picking(): void
    {
        $ids = array_filter(array_map('intval', (array) Request::get('ids', [])));
        if (!$ids) {
            throw new ErrorNegocio('Seleccioná los pedidos a preparar.');
        }
        $m = DB::marcadores($ids);
        $consolidado = DB::filas(
            "SELECT p.codigo, p.descripcion, p.ubicacion, p.unidad_base, r.nombre AS rubro, SUM(pi.cantidad_base + pi.bonificacion_base) AS unidades,
                    (SELECT CONCAT(pp.nombre, '|', pp.factor + 0) FROM producto_presentaciones pp WHERE pp.producto_id = p.id AND pp.activo = 1 ORDER BY pp.factor DESC LIMIT 1) AS bulto
             FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id LEFT JOIN rubros r ON r.id = p.rubro_id
             WHERE pi.pedido_id IN ($m) GROUP BY p.id, p.codigo, p.descripcion, p.ubicacion, p.unidad_base, r.nombre
             ORDER BY p.ubicacion IS NULL, p.ubicacion, r.nombre, p.descripcion",
            $ids
        );
        $pedidos = DB::filas("SELECT pe.*, c.nombre_comercio, c.direccion, z.nombre AS zona FROM pedidos pe JOIN clientes c ON c.id = pe.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id WHERE pe.id IN ($m) ORDER BY z.nombre, c.orden_ruta", $ids);
        $items = [];
        foreach (DB::filas("SELECT pi.*, p.descripcion, p.codigo, p.ubicacion FROM pedido_items pi JOIN productos p ON p.id = pi.producto_id WHERE pi.pedido_id IN ($m) ORDER BY p.ubicacion, p.descripcion", $ids) as $it) {
            $items[$it['pedido_id']][] = $it;
        }
        $this->vista('impresion/picking', ['titulo' => 'Hoja de preparación', 'consolidado' => $consolidado, 'pedidos' => $pedidos, 'items' => $items], 'layouts/impresion');
    }
}
