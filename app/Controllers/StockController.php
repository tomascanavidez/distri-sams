<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Services\PrecioService;
use App\Services\StockService;
use App\Support\Exportador;

final class StockController extends Controller
{
    private function depositos(bool $conVehiculos = true): array
    {
        return DB::pares('SELECT id, nombre FROM depositos WHERE activo = 1' . ($conVehiculos ? '' : " AND tipo = 'deposito'") . ' ORDER BY tipo, id');
    }

    /** Consulta de existencias con valorización a costo. */
    private function consultaExistencias(): array
    {
        $dep = (int) Request::get('deposito_id', 0);
        $w = ['p.activo = 1'];
        $p = [];
        if ($q = trim((string) Request::get('q', ''))) {
            $w[] = '(p.descripcion LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        if ($r = (int) Request::get('rubro_id', 0)) {
            $w[] = 'p.rubro_id = ?';
            $p[] = $r;
        }
        $filtroDep = $dep ? 's.deposito_id = ' . $dep : "s.deposito_id IN (SELECT id FROM depositos WHERE tipo = 'deposito')";
        $costo = Ajustes::get('costo_metodo') === 'promedio' ? 'IF(p.costo_promedio > 0, p.costo_promedio, p.costo)' : 'p.costo';
        $sql = "SELECT p.id, p.codigo, p.descripcion, p.unidad_base, p.stock_minimo, p.stock_maximo, p.ubicacion, r.nombre AS rubro, $costo AS costo,
                       COALESCE((SELECT SUM(s.cantidad) FROM stock s WHERE s.producto_id = p.id AND $filtroDep), 0) AS fisico,
                       COALESCE((SELECT SUM(s.reservado) FROM stock s WHERE s.producto_id = p.id AND $filtroDep), 0) AS reservado
                FROM productos p LEFT JOIN rubros r ON r.id = p.rubro_id WHERE " . implode(' AND ', $w);
        $having = match (Request::get('situacion')) {
            'bajo' => ' HAVING fisico - reservado < p.stock_minimo',
            'sin' => ' HAVING fisico - reservado <= 0',
            'exceso' => ' HAVING p.stock_maximo > 0 AND fisico > p.stock_maximo',
            default => '',
        };
        return [$sql . $having, $p];
    }

    public function index(): void
    {
        [$sql, $p] = $this->consultaExistencias();
        $pag = new Paginador($sql, $p, ['descripcion' => 'p.descripcion', 'fisico' => 'fisico', 'valor' => 'fisico * costo', 'ubicacion' => 'p.ubicacion'], 'descripcion');
        $valor = (float) DB::valor("SELECT COALESCE(SUM(t.fisico * t.costo), 0) FROM ($sql) t", $p);
        $this->vista('stock/index', [
            'titulo' => 'Existencias', 'pag' => $pag, 'valor' => $valor, 'depositos' => $this->depositos(),
            'rubros' => DB::pares('SELECT id, nombre FROM rubros WHERE activo = 1 ORDER BY nombre'),
        ]);
    }

    public function exportar(): void
    {
        [$sql, $p] = $this->consultaExistencias();
        $filas = DB::filas($sql . ' ORDER BY p.descripcion', $p);
        foreach ($filas as &$f) {
            $f['disponible'] = (float) $f['fisico'] - (float) $f['reservado'];
            $f['valor'] = round((float) $f['fisico'] * (float) $f['costo'], 2);
        }
        Exportador::enviar((string) Request::get('formato', 'csv'), 'Stock valorizado', [
            'codigo' => ['Código', 'texto'], 'descripcion' => ['Producto', 'texto'], 'rubro' => ['Rubro', 'texto'], 'ubicacion' => ['Ubicación', 'texto'],
            'fisico' => ['Físico', 'cant'], 'reservado' => ['Reservado', 'cant'], 'disponible' => ['Disponible', 'cant'], 'stock_minimo' => ['Mínimo', 'cant'],
            'costo' => ['Costo', 'money'], 'valor' => ['Valor', 'money'],
        ], $filas, ['descripcion' => 'TOTAL', 'valor' => array_sum(array_column($filas, 'valor'))], 'Valorizado a costo ' . (Ajustes::get('costo_metodo') === 'promedio' ? 'promedio' : 'último'));
    }

    public function movimientos(): void
    {
        $w = [];
        $p = [];
        if ($pid = (int) Request::get('producto_id', 0)) {
            $w[] = 'm.producto_id = ?';
            $p[] = $pid;
        }
        if ($d = (int) Request::get('deposito_id', 0)) {
            $w[] = 'm.deposito_id = ?';
            $p[] = $d;
        }
        if ($t = Request::get('tipo')) {
            $w[] = 'm.tipo = ?';
            $p[] = (string) $t;
        }
        if ($desde = parse_fecha(Request::get('desde'))) {
            $w[] = 'm.fecha >= ?';
            $p[] = $desde . ' 00:00:00';
        }
        if ($hasta = parse_fecha(Request::get('hasta'))) {
            $w[] = 'm.fecha <= ?';
            $p[] = $hasta . ' 23:59:59';
        }
        $sql = 'SELECT m.*, p.codigo, p.descripcion, d.nombre AS deposito, u.nombre AS usuario, l.lote, l.vencimiento FROM stock_movimientos m JOIN productos p ON p.id = m.producto_id
                JOIN depositos d ON d.id = m.deposito_id LEFT JOIN usuarios u ON u.id = m.usuario_id LEFT JOIN stock_lotes l ON l.id = m.lote_id'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
        if ($formato = Request::get('formato')) {
            $filas = DB::filas($sql . ' ORDER BY m.id LIMIT 20000', $p);
            foreach ($filas as &$f) {
                $f['tipo'] = StockService::TIPOS[$f['tipo']] ?? $f['tipo'];
            }
            Exportador::enviar((string) $formato, 'Kardex', [
                'fecha' => ['Fecha', 'fecha_hora'], 'codigo' => ['Código', 'texto'], 'descripcion' => ['Producto', 'texto'], 'deposito' => ['Depósito', 'texto'],
                'tipo' => ['Movimiento', 'texto'], 'motivo' => ['Detalle', 'texto'], 'lote' => ['Lote', 'texto'], 'cantidad' => ['Cantidad', 'cant'], 'saldo' => ['Saldo', 'cant'],
                'costo_unitario' => ['Costo', 'money'], 'usuario' => ['Usuario', 'texto'],
            ], $filas);
            return;
        }
        $pag = new Paginador($sql, $p, ['fecha' => 'm.id'], 'fecha', 'desc', 50);
        $producto = $pid ? DB::fila('SELECT id, descripcion FROM productos WHERE id = ?', [$pid]) : null;
        $this->vista('stock/movimientos', ['titulo' => 'Movimientos de stock (kardex)', 'pag' => $pag, 'producto' => $producto, 'depositos' => $this->depositos()]);
    }

    // ---------------- Ajustes ----------------

    public function ajustes(): void
    {
        $pag = new Paginador('SELECT a.*, d.nombre AS deposito, u.nombre AS usuario, (SELECT COUNT(*) FROM ajustes_stock_items WHERE ajuste_id = a.id) AS items,
                              (SELECT SUM(cantidad * costo_unitario) FROM ajustes_stock_items WHERE ajuste_id = a.id) AS valor
                              FROM ajustes_stock a JOIN depositos d ON d.id = a.deposito_id LEFT JOIN usuarios u ON u.id = a.usuario_id', [], ['fecha' => 'a.id'], 'fecha', 'desc');
        $this->vista('stock/ajustes', ['titulo' => 'Ajustes de stock', 'pag' => $pag]);
    }

    public function nuevoAjuste(): void
    {
        $this->vista('stock/ajuste_form', ['titulo' => 'Nuevo ajuste de stock', 'depositos' => $this->depositos(), 'deposito' => (int) Ajustes::get('deposito_central', 1)]);
    }

    public function guardarAjuste(): void
    {
        $d = Request::json();
        $motivo = (string) ($d['motivo'] ?? '');
        $signoMotivo = in_array($motivo, ['sobrante'], true) ? 1 : (in_array($motivo, ['rotura', 'vencimiento', 'faltante', 'consumo_interno'], true) ? -1 : 0);
        $items = [];
        foreach ($d['items'] ?? [] as $it) {
            $cant = parse_decimal($it['cantidad'] ?? 0) * (float) ($it['factor'] ?? 1);
            if ($signoMotivo !== 0) {
                $cant = $signoMotivo * abs($cant);
            }
            $items[] = [(int) $it['producto_id'], round($cant, 3), $it['lote'] ?: null, parse_fecha($it['vencimiento'] ?? '')];
        }
        $id = StockService::ajustar((int) ($d['deposito_id'] ?? 1), $motivo, $items, $d['observaciones'] ?? null);
        flash('exito', 'Ajuste de stock aplicado.');
        $this->json(['ok' => true, 'redirigir' => url('/stock/ajustes/' . $id)]);
    }

    public function verAjuste(string $id): void
    {
        $a = DB::fila('SELECT a.*, d.nombre AS deposito, u.nombre AS usuario FROM ajustes_stock a JOIN depositos d ON d.id = a.deposito_id LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = ?', [(int) $id]);
        if (!$a) {
            $this->buscarOFallar('ajustes_stock', (int) $id);
        }
        $items = DB::filas('SELECT i.*, p.codigo, p.descripcion, p.unidad_base FROM ajustes_stock_items i JOIN productos p ON p.id = i.producto_id WHERE i.ajuste_id = ?', [$id]);
        $this->vista('stock/ajuste_ver', ['titulo' => 'Ajuste N° ' . $a['id'], 'a' => $a, 'items' => $items]);
    }

    public function anularAjuste(string $id): void
    {
        $motivo = $this->motivoAnulacion();
        DB::transaccion(function () use ($id, $motivo) {
            $a = DB::fila('SELECT * FROM ajustes_stock WHERE id = ? FOR UPDATE', [(int) $id]);
            if (!$a || $a['estado'] !== 'aplicado') {
                throw new ErrorNegocio('El ajuste no se puede anular.');
            }
            StockService::revertirDocumento('ajuste', (int) $id, 'Anulación ajuste N° ' . $id);
            DB::actualizar('ajustes_stock', ['estado' => 'anulado', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => (int) $id]);
            Auditoria::registrar('stock', 'anular', 'ajuste', (int) $id, ['estado' => 'aplicado'], ['estado' => 'anulado', 'motivo' => $motivo]);
        });
        $this->volver('Ajuste anulado.');
    }

    // ---------------- Transferencias ----------------

    public function transferencias(): void
    {
        $pag = new Paginador('SELECT t.*, o.nombre AS origen, d.nombre AS destino, u.nombre AS usuario, h.numero AS hr_numero, (SELECT SUM(cantidad) FROM transferencias_items WHERE transferencia_id = t.id) AS unidades
                              FROM transferencias t JOIN depositos o ON o.id = t.deposito_origen_id JOIN depositos d ON d.id = t.deposito_destino_id
                              LEFT JOIN usuarios u ON u.id = t.usuario_id LEFT JOIN hojas_ruta h ON h.id = t.hoja_ruta_id', [], ['fecha' => 't.id'], 'fecha', 'desc');
        $this->vista('stock/transferencias', ['titulo' => 'Transferencias entre depósitos', 'pag' => $pag]);
    }

    public function nuevaTransferencia(): void
    {
        $this->vista('stock/transferencia_form', ['titulo' => 'Nueva transferencia', 'depositos' => $this->depositos()]);
    }

    public function guardarTransferencia(): void
    {
        $d = Request::json();
        $items = [];
        foreach ($d['items'] ?? [] as $it) {
            $items[] = [(int) $it['producto_id'], round(parse_decimal($it['cantidad'] ?? 0) * (float) ($it['factor'] ?? 1), 3)];
        }
        if (!$items) {
            throw new ErrorNegocio('Agregá productos a transferir.');
        }
        $id = StockService::transferir((int) ($d['deposito_id'] ?? 0), (int) ($d['deposito_destino_id'] ?? 0), $items, $d['observaciones'] ?? null);
        Auditoria::registrar('stock', 'transferencia', 'transferencia', $id, null, ['items' => count($items)], 'Transferencia entre depósitos');
        flash('exito', 'Transferencia registrada.');
        $this->json(['ok' => true, 'redirigir' => url('/stock/transferencias/' . $id)]);
    }

    public function verTransferencia(string $id): void
    {
        $t = DB::fila('SELECT t.*, o.nombre AS origen, d.nombre AS destino, u.nombre AS usuario FROM transferencias t JOIN depositos o ON o.id = t.deposito_origen_id
                       JOIN depositos d ON d.id = t.deposito_destino_id LEFT JOIN usuarios u ON u.id = t.usuario_id WHERE t.id = ?', [(int) $id]);
        if (!$t) {
            $this->buscarOFallar('transferencias', (int) $id);
        }
        $items = DB::filas('SELECT i.*, p.codigo, p.descripcion, p.unidad_base FROM transferencias_items i JOIN productos p ON p.id = i.producto_id WHERE i.transferencia_id = ?', [$id]);
        $this->vista('stock/transferencia_ver', ['titulo' => 'Transferencia N° ' . $t['id'], 't' => $t, 'items' => $items]);
    }

    public function anularTransferencia(string $id): void
    {
        $motivo = $this->motivoAnulacion();
        DB::transaccion(function () use ($id, $motivo) {
            $t = DB::fila('SELECT * FROM transferencias WHERE id = ? FOR UPDATE', [(int) $id]);
            if (!$t || $t['estado'] !== 'aplicada' || $t['hoja_ruta_id']) {
                throw new ErrorNegocio('La transferencia no se puede anular (las cargas de reparto se anulan desde la hoja de ruta).');
            }
            StockService::revertirDocumento('transferencia', (int) $id, 'Anulación transferencia N° ' . $id);
            DB::actualizar('transferencias', ['estado' => 'anulada', 'motivo_anulacion' => $motivo], 'id = :id', ['id' => (int) $id]);
            Auditoria::registrar('stock', 'anular', 'transferencia', (int) $id, ['estado' => 'aplicada'], ['estado' => 'anulada', 'motivo' => $motivo]);
        });
        $this->volver('Transferencia anulada.');
    }

    // ---------------- Inventario físico ----------------

    public function inventarios(): void
    {
        $pag = new Paginador('SELECT i.*, d.nombre AS deposito, r.nombre AS rubro, u.nombre AS usuario, (SELECT COUNT(*) FROM inventario_items WHERE inventario_id = i.id) AS items,
                              (SELECT COUNT(*) FROM inventario_items WHERE inventario_id = i.id AND cantidad_contada IS NOT NULL) AS contados
                              FROM inventarios i JOIN depositos d ON d.id = i.deposito_id LEFT JOIN rubros r ON r.id = i.rubro_id LEFT JOIN usuarios u ON u.id = i.usuario_id', [], ['fecha' => 'i.id'], 'fecha', 'desc');
        $this->vista('stock/inventarios', ['titulo' => 'Inventario físico', 'pag' => $pag, 'depositos' => $this->depositos(false), 'rubros' => DB::pares('SELECT id, nombre FROM rubros WHERE activo = 1 ORDER BY nombre')]);
    }

    /** Genera la planilla congelando el stock del sistema al momento de iniciar el conteo. */
    public function crearInventario(): void
    {
        $dep = Request::entero('deposito_id') ?: (int) Ajustes::get('deposito_central', 1);
        $rubro = Request::entero('rubro_id') ?: null;
        $id = DB::transaccion(function () use ($dep, $rubro) {
            $id = DB::insertar('inventarios', ['fecha' => ahora(), 'deposito_id' => $dep, 'rubro_id' => $rubro, 'descripcion' => Request::texto('descripcion'), 'estado' => 'abierto', 'usuario_id' => Auth::id()]);
            $sql = 'INSERT INTO inventario_items (inventario_id, producto_id, stock_sistema)
                    SELECT ?, p.id, COALESCE(s.cantidad, 0) FROM productos p LEFT JOIN stock s ON s.producto_id = p.id AND s.deposito_id = ? WHERE p.activo = 1';
            $p = [$id, $dep];
            if ($rubro) {
                $sql .= ' AND p.rubro_id = ?';
                $p[] = $rubro;
            }
            DB::query($sql, $p);
            Auditoria::registrar('stock', 'inventario', 'inventario', $id, null, ['deposito_id' => $dep, 'rubro_id' => $rubro], 'Inicio de inventario físico');
            return $id;
        });
        $this->redirigir('/stock/inventarios/' . $id, 'Planilla de inventario generada. Cargá las cantidades contadas.');
    }

    private function cargarInventario(int $id): array
    {
        $inv = DB::fila('SELECT i.*, d.nombre AS deposito, r.nombre AS rubro FROM inventarios i JOIN depositos d ON d.id = i.deposito_id LEFT JOIN rubros r ON r.id = i.rubro_id WHERE i.id = ?', [$id]);
        if (!$inv) {
            $this->buscarOFallar('inventarios', $id);
        }
        $costo = Ajustes::get('costo_metodo') === 'promedio' ? 'IF(p.costo_promedio > 0, p.costo_promedio, p.costo)' : 'p.costo';
        $items = DB::filas("SELECT ii.*, p.codigo, p.descripcion, p.unidad_base, p.ubicacion, $costo AS costo FROM inventario_items ii JOIN productos p ON p.id = ii.producto_id WHERE ii.inventario_id = ? ORDER BY p.ubicacion IS NULL, p.ubicacion, p.descripcion", [$id]);
        return [$inv, $items];
    }

    public function verInventario(string $id): void
    {
        [$inv, $items] = $this->cargarInventario((int) $id);
        $this->vista('stock/inventario_ver', ['titulo' => 'Inventario N° ' . $inv['id'], 'inv' => $inv, 'items' => $items]);
    }

    public function planillaInventario(string $id): void
    {
        [$inv, $items] = $this->cargarInventario((int) $id);
        $this->vista('impresion/inventario', ['titulo' => 'Planilla de inventario', 'inv' => $inv, 'items' => $items, 'conStock' => (bool) Request::get('con_stock')], 'layouts/impresion');
    }

    public function guardarConteo(string $id): void
    {
        $inv = $this->buscarOFallar('inventarios', (int) $id);
        if ($inv['estado'] !== 'abierto') {
            throw new ErrorNegocio('El inventario ya no está abierto.');
        }
        foreach ((array) Request::input('contado', []) as $itemId => $valor) {
            $valor = trim((string) $valor);
            DB::query('UPDATE inventario_items SET cantidad_contada = ? WHERE id = ? AND inventario_id = ?', [$valor === '' ? null : parse_decimal($valor), (int) $itemId, (int) $id]);
        }
        $this->volver('Conteo guardado.');
    }

    /** Aplica las diferencias (contado − sistema) como ajuste "inventario". */
    public function aplicarInventario(string $id): void
    {
        DB::transaccion(function () use ($id) {
            $inv = DB::fila('SELECT * FROM inventarios WHERE id = ? FOR UPDATE', [(int) $id]);
            if (!$inv || $inv['estado'] !== 'abierto') {
                throw new ErrorNegocio('El inventario no está abierto.');
            }
            // Diferencia = contado − stock del sistema congelado al generar la planilla. Así los movimientos
            // ocurridos después (ventas, recepciones) no distorsionan el ajuste.
            $items = [];
            foreach (DB::filas('SELECT * FROM inventario_items WHERE inventario_id = ? AND cantidad_contada IS NOT NULL', [(int) $id]) as $it) {
                $dif = round((float) $it['cantidad_contada'] - (float) $it['stock_sistema'], 3);
                if (abs($dif) >= 0.0005) {
                    $items[] = [(int) $it['producto_id'], $dif];
                }
            }
            $ajusteId = $items ? StockService::ajustar((int) $inv['deposito_id'], 'inventario', $items, 'Inventario físico N° ' . $id) : null;
            DB::actualizar('inventarios', ['estado' => 'aplicado', 'ajuste_id' => $ajusteId, 'aplicado_en' => ahora()], 'id = :id', ['id' => (int) $id]);
            Auditoria::registrar('stock', 'aplicar_inventario', 'inventario', (int) $id, null, ['diferencias' => count($items)], 'Inventario físico aplicado');
        });
        $this->volver('Inventario aplicado: las diferencias se registraron como ajuste de stock.');
    }

    public function anularInventario(string $id): void
    {
        $inv = $this->buscarOFallar('inventarios', (int) $id);
        if ($inv['estado'] !== 'abierto') {
            throw new ErrorNegocio('Sólo se puede descartar un inventario abierto (uno aplicado se revierte anulando su ajuste).');
        }
        DB::actualizar('inventarios', ['estado' => 'anulado'], 'id = :id', ['id' => (int) $id]);
        Auditoria::registrar('stock', 'anular', 'inventario', (int) $id, ['estado' => 'abierto'], ['estado' => 'anulado', 'motivo' => $this->motivoAnulacion()]);
        $this->volver('Inventario descartado.');
    }

    public function vencimientos(): void
    {
        $dias = (int) Request::get('dias', Ajustes::get('stock_alerta_vencimiento_dias', 30));
        $filas = DB::filas(
            "SELECT l.*, p.codigo, p.descripcion, p.unidad_base, p.costo, d.nombre AS deposito, DATEDIFF(l.vencimiento, CURDATE()) AS dias
             FROM stock_lotes l JOIN productos p ON p.id = l.producto_id JOIN depositos d ON d.id = l.deposito_id
             WHERE l.cantidad > 0 AND l.vencimiento IS NOT NULL AND l.vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY l.vencimiento",
            [$dias]
        );
        if ($f = Request::get('formato')) {
            Exportador::enviar((string) $f, 'Productos próximos a vencer', [
                'vencimiento' => ['Vence', 'fecha'], 'codigo' => ['Código', 'texto'], 'descripcion' => ['Producto', 'texto'], 'lote' => ['Lote', 'texto'],
                'deposito' => ['Depósito', 'texto'], 'cantidad' => ['Cantidad', 'cant'], 'dias' => ['Días', 'entero'],
            ], $filas);
            return;
        }
        $this->vista('stock/vencimientos', ['titulo' => 'Vencimientos', 'filas' => $filas, 'dias' => $dias]);
    }
}
