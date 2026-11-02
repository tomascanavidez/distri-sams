<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Services\ClienteService;
use App\Services\PrecioService;

/**
 * Búsquedas JSON para autocompletado (respuestas compactas, con LIMIT).
 */
final class ApiController extends Controller
{
    /**
     * Búsqueda tolerante de productos: cada palabra debe aparecer en la descripción, código,
     * código de barras, marca o rubro. Un código de barras exacto (de cualquier presentación) va primero.
     */
    public function productos(): void
    {
        $q = trim((string) Request::get('q', ''));
        $lista = (int) Request::get('lista_id', 0) ?: null;
        $deposito = (int) Request::get('deposito_id', 0) ?: (int) Ajustes::get('deposito_central', 1);
        $limite = min(30, max(5, (int) Request::get('limite', 15)));
        if ($q === '') {
            $this->json(['items' => []]);
            return;
        }
        // Coincidencia exacta por código de barras o código interno
        $exacto = DB::fila(
            'SELECT pp.producto_id, pp.id AS presentacion_id FROM producto_presentaciones pp JOIN productos p ON p.id = pp.producto_id
             WHERE pp.codigo_barras = ? AND pp.activo = 1 AND p.activo = 1 LIMIT 1',
            [$q]
        ) ?: DB::fila('SELECT id AS producto_id, NULL AS presentacion_id FROM productos WHERE (codigo_barras = ? OR codigo = ?) AND activo = 1 LIMIT 1', [$q, $q]);

        $ids = [];
        if ($exacto) {
            $ids[] = (int) $exacto['producto_id'];
        }
        $where = ['p.activo = 1'];
        $params = [];
        foreach (array_slice(preg_split('/\s+/', $q) ?: [], 0, 5) as $palabra) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $palabra) . '%';
            $where[] = '(p.descripcion LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ? OR m.nombre LIKE ? OR r.nombre LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (Request::get('rubro_id')) {
            $where[] = 'p.rubro_id = ?';
            $params[] = (int) Request::get('rubro_id');
        }
        $encontrados = DB::columna(
            'SELECT p.id FROM productos p LEFT JOIN marcas m ON m.id = p.marca_id LEFT JOIN rubros r ON r.id = p.rubro_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY (p.descripcion LIKE ?) DESC, p.descripcion LIMIT ' . $limite,
            array_merge($params, [$q . '%'])
        );
        $ids = array_values(array_unique(array_merge($ids, array_map('intval', $encontrados))));
        $items = $this->detalleProductos($ids, $lista, $deposito);
        if ($exacto && $exacto['presentacion_id']) {
            $items[0]['presentacion_id'] = (int) $exacto['presentacion_id'];
        }
        $this->json(['items' => $items, 'exacto' => (bool) $exacto]);
    }

    public function producto(string $id): void
    {
        $lista = (int) Request::get('lista_id', 0) ?: null;
        $deposito = (int) Request::get('deposito_id', 0) ?: (int) Ajustes::get('deposito_central', 1);
        $items = $this->detalleProductos([(int) $id], $lista, $deposito);
        $this->json(['item' => $items[0] ?? null]);
    }

    /** Datos completos de productos para los editores de documentos. */
    private function detalleProductos(array $ids, ?int $lista, int $deposito): array
    {
        if (!$ids) {
            return [];
        }
        $listaId = $lista ?: (int) DB::valor('SELECT id FROM listas_precios WHERE activo = 1 ORDER BY orden, id LIMIT 1');
        $m = DB::marcadores($ids);
        $filas = DB::filas(
            "SELECT p.id, p.codigo, p.codigo_barras, p.descripcion, p.unidad_base, p.iva_alicuota, p.impuesto_interno, p.costo, p.costo_promedio,
                    p.maneja_lotes, p.foto, m.nombre AS marca, r.nombre AS rubro,
                    COALESCE(pp.precio, 0) AS precio, COALESCE(s.cantidad, 0) AS stock, COALESCE(s.cantidad - s.reservado, 0) AS disponible
             FROM productos p
             LEFT JOIN marcas m ON m.id = p.marca_id LEFT JOIN rubros r ON r.id = p.rubro_id
             LEFT JOIN producto_precios pp ON pp.producto_id = p.id AND pp.lista_id = ?
             LEFT JOIN stock s ON s.producto_id = p.id AND s.deposito_id = ?
             WHERE p.id IN ($m)",
            array_merge([$listaId, $deposito], $ids)
        );
        $pres = [];
        foreach (DB::filas("SELECT id, producto_id, nombre, factor, codigo_barras, es_venta, es_compra, predeterminada_venta, predeterminada_compra FROM producto_presentaciones WHERE activo = 1 AND producto_id IN ($m) ORDER BY factor", $ids) as $pr) {
            $pres[(int) $pr['producto_id']][] = [
                'id' => (int) $pr['id'], 'nombre' => $pr['nombre'], 'factor' => (float) $pr['factor'], 'barras' => $pr['codigo_barras'],
                'venta' => (int) $pr['es_venta'], 'compra' => (int) $pr['es_compra'],
                'pv' => (int) $pr['predeterminada_venta'], 'pc' => (int) $pr['predeterminada_compra'],
            ];
        }
        $porId = [];
        foreach ($filas as $f) {
            $id = (int) $f['id'];
            $porId[$id] = [
                'id' => $id,
                'texto' => $f['descripcion'],
                'codigo' => $f['codigo'],
                'barras' => $f['codigo_barras'],
                'descripcion' => $f['descripcion'],
                'marca' => $f['marca'],
                'rubro' => $f['rubro'],
                'unidad' => $f['unidad_base'],
                'iva' => (float) $f['iva_alicuota'],
                'internos' => (float) $f['impuesto_interno'],
                'costo' => round(PrecioService::costoBase($f), 4),
                'precio' => (float) $f['precio'],
                'stock' => (float) $f['stock'],
                'disponible' => (float) $f['disponible'],
                'lotes' => (int) $f['maneja_lotes'],
                'foto' => $f['foto'] ? url('/uploads/productos/' . $f['foto']) : null,
                'presentaciones' => $pres[$id] ?? [['id' => 0, 'nombre' => $f['unidad_base'], 'factor' => 1.0, 'barras' => null, 'venta' => 1, 'compra' => 1, 'pv' => 1, 'pc' => 1]],
            ];
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($porId[$id])) {
                $out[] = $porId[$id];
            }
        }
        return $out;
    }

    public function clientes(): void
    {
        $q = trim((string) Request::get('q', ''));
        if ($q === '') {
            $this->json(['items' => []]);
            return;
        }
        $where = [];
        $params = [];
        foreach (array_slice(preg_split('/\s+/', $q) ?: [], 0, 4) as $palabra) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $palabra) . '%';
            $where[] = '(c.nombre_comercio LIKE ? OR c.titular LIKE ? OR c.codigo LIKE ? OR c.documento LIKE ? OR c.direccion LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!Request::get('todos')) {
            $where[] = "c.estado <> 'inactivo'";
        }
        $filas = DB::filas(
            'SELECT c.id, c.codigo, c.nombre_comercio, c.titular, c.direccion, c.localidad, c.condicion_iva, c.lista_precio_id,
                    c.estado, c.condicion_pago, c.preventista_id, z.nombre AS zona
             FROM clientes c LEFT JOIN zonas z ON z.id = c.zona_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.nombre_comercio LIMIT 15',
            $params
        );
        $items = array_map(fn ($c) => [
            'id' => (int) $c['id'],
            'texto' => $c['nombre_comercio'],
            'detalle' => trim($c['codigo'] . ' · ' . ($c['direccion'] ?? '') . ($c['zona'] ? ' · ' . $c['zona'] : ''), ' ·'),
            'lista_precio_id' => $c['lista_precio_id'] ? (int) $c['lista_precio_id'] : null,
            'condicion_iva' => $c['condicion_iva'],
            'estado' => $c['estado'],
            'condicion_pago' => $c['condicion_pago'],
        ], $filas);
        $this->json(['items' => $items]);
    }

    public function cliente(string $id): void
    {
        $c = DB::fila('SELECT c.*, l.nombre AS lista FROM clientes c LEFT JOIN listas_precios l ON l.id = c.lista_precio_id WHERE c.id = ?', [(int) $id]);
        if (!$c) {
            $this->json(['ok' => false, 'error' => 'Cliente inexistente'], 404);
            return;
        }
        $resumen = ClienteService::resumen((int) $id);
        $this->json(['item' => [
            'id' => (int) $c['id'], 'texto' => $c['nombre_comercio'], 'codigo' => $c['codigo'], 'titular' => $c['titular'],
            'condicion_iva' => $c['condicion_iva'], 'lista_precio_id' => $c['lista_precio_id'] ? (int) $c['lista_precio_id'] : null,
            'lista' => $c['lista'], 'estado' => $c['estado'], 'condicion_pago' => $c['condicion_pago'],
            'direccion' => $c['direccion'], 'resumen' => $resumen,
            'problemas' => ClienteService::problemasCredito((int) $id),
        ]]);
    }

    public function proveedores(): void
    {
        $q = '%' . trim((string) Request::get('q', '')) . '%';
        $filas = DB::filas(
            'SELECT id, razon_social, nombre_fantasia, cuit FROM proveedores WHERE activo = 1 AND (razon_social LIKE ? OR nombre_fantasia LIKE ? OR cuit LIKE ?) ORDER BY razon_social LIMIT 15',
            [$q, $q, $q]
        );
        $this->json(['items' => array_map(fn ($p) => ['id' => (int) $p['id'], 'texto' => $p['razon_social'], 'detalle' => trim(($p['nombre_fantasia'] ?? '') . ' ' . ($p['cuit'] ?? ''))], $filas)]);
    }

    /** Facturas/remitos de un cliente (para notas de crédito y devoluciones). */
    public function comprobantes(): void
    {
        $cliente = (int) Request::get('cliente_id', 0);
        $q = trim((string) Request::get('q', ''));
        $params = [];
        $where = "c.estado = 'emitido' AND c.tipo IN ('FA','FB','FC','RM')";
        if ($cliente) {
            $where .= ' AND c.cliente_id = ?';
            $params[] = $cliente;
        }
        if ($q !== '') {
            $where .= ' AND (c.numero = ? OR c.cliente_nombre LIKE ?)';
            $params[] = (int) preg_replace('/\D/', '', $q);
            $params[] = '%' . $q . '%';
        }
        $filas = DB::filas("SELECT c.id, c.tipo, c.punto_venta_id, c.numero, c.fecha, c.total, c.cliente_nombre FROM comprobantes c WHERE $where ORDER BY c.fecha DESC LIMIT 20", $params);
        $this->json(['items' => array_map(fn ($c) => [
            'id' => (int) $c['id'],
            'texto' => \App\Services\VentaService::numero($c),
            'detalle' => fecha($c['fecha']) . ' · ' . $c['cliente_nombre'] . ' · ' . money($c['total']),
        ], $filas)]);
    }
}
