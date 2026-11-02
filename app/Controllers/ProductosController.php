<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Paginador;
use App\Core\Request;
use App\Core\Validador;
use App\Services\PrecioService;
use App\Support\Consultas;
use App\Support\Exportador;
use App\Support\Imagenes;

final class ProductosController extends Controller
{
    public function index(): void
    {
        [$sql, $params] = $this->consultaListado();
        $ordenes = ['descripcion' => 'p.descripcion', 'codigo' => 'p.codigo', 'rubro' => 'rubro', 'costo' => 'p.costo', 'stock' => 'stock', 'precio' => 'precio'];
        $pag = new Paginador($sql, $params, $ordenes, 'descripcion');
        $this->vista('productos/index', [
            'titulo' => 'Productos', 'pag' => $pag,
            'rubros' => DB::pares('SELECT id, nombre FROM rubros WHERE activo = 1 ORDER BY nombre'),
            'marcas' => DB::pares('SELECT id, nombre FROM marcas WHERE activo = 1 ORDER BY nombre'),
            'proveedores' => DB::pares('SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social'),
            'listas' => DB::pares('SELECT id, nombre FROM listas_precios WHERE activo = 1 ORDER BY orden, id'),
        ]);
    }

    private function consultaListado(): array
    {
        $where = [];
        $params = [];
        $lista = (int) Request::get('lista_id', 0) ?: (int) DB::valor('SELECT id FROM listas_precios WHERE activo = 1 ORDER BY orden, id LIMIT 1');
        if ($q = trim((string) Request::get('q', ''))) {
            foreach (array_slice(preg_split('/\s+/', $q) ?: [], 0, 5) as $palabra) {
                $where[] = '(p.descripcion LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ? OR m.nombre LIKE ?)';
                $like = "%$palabra%";
                array_push($params, $like, $like, $like, $like);
            }
        }
        foreach (['rubro_id', 'marca_id', 'proveedor_id'] as $f) {
            if ($v = (int) Request::get($f, 0)) {
                $where[] = "p.$f = ?";
                $params[] = $v;
            }
        }
        $estado = Request::get('estado', 'activos');
        if ($estado === 'activos') {
            $where[] = 'p.activo = 1';
        } elseif ($estado === 'inactivos') {
            $where[] = 'p.activo = 0';
        }
        $having = Request::get('stock_bajo') ? ' HAVING stock < p.stock_minimo' : '';
        $sql = 'SELECT p.*, r.nombre AS rubro, m.nombre AS marca, ' . Consultas::stockTotal() . ' AS stock, ' . Consultas::stockDisponible() . ' AS disponible,
                       COALESCE(pp.precio, 0) AS precio
                FROM productos p LEFT JOIN rubros r ON r.id = p.rubro_id LEFT JOIN marcas m ON m.id = p.marca_id
                LEFT JOIN producto_precios pp ON pp.producto_id = p.id AND pp.lista_id = ' . $lista
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . $having;
        return [$sql, $params];
    }

    public function nuevo(): void
    {
        $this->formulario([
            'activo' => 1, 'iva_alicuota' => '21.00', 'unidad_base' => 'Unidad', 'codigo' => $this->proximoCodigo(),
            'presentaciones' => [['id' => 0, 'nombre' => 'Unidad', 'factor' => 1, 'codigo_barras' => '', 'es_venta' => 1, 'es_compra' => 1, 'predeterminada_venta' => 1, 'predeterminada_compra' => 0]],
        ], [], null);
    }

    public function editar(string $id): void
    {
        $p = $this->buscarOFallar('productos', (int) $id);
        $p['presentaciones'] = DB::filas('SELECT * FROM producto_presentaciones WHERE producto_id = ? AND activo = 1 ORDER BY factor', [$id]);
        $this->formulario($p, [], (int) $id);
    }

    private function formulario(array $datos, array $errores, ?int $id): void
    {
        $this->vista('productos/form', [
            'titulo' => $id ? 'Editar producto' : 'Nuevo producto', 'datos' => $datos, 'errores' => $errores, 'id' => $id,
            'rubros' => DB::pares('SELECT id, nombre FROM rubros WHERE activo = 1 ORDER BY nombre'),
            'marcas' => DB::pares('SELECT id, nombre FROM marcas WHERE activo = 1 ORDER BY nombre'),
            'proveedores' => DB::pares('SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social'),
            'precios' => DB::filas('SELECT l.id AS lista_id, l.nombre, l.margen_porc AS margen_lista, pp.modo, pp.margen_porc, pp.precio FROM listas_precios l LEFT JOIN producto_precios pp ON pp.lista_id = l.id AND pp.producto_id = ? WHERE l.activo = 1 ORDER BY l.orden, l.id', [$id ?? 0]),
        ]);
    }

    private function proximoCodigo(): string
    {
        $max = (int) DB::valor("SELECT MAX(CAST(codigo AS UNSIGNED)) FROM productos WHERE codigo REGEXP '^[0-9]+$'");
        return str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    public function guardar(?string $id = null): void
    {
        $id = $id !== null ? (int) $id : null;
        $antes = $id ? $this->buscarOFallar('productos', $id) : null;
        $d = [
            'codigo' => Request::texto('codigo'), 'codigo_barras' => Request::texto('codigo_barras'),
            'descripcion' => Request::texto('descripcion'), 'rubro_id' => Request::entero('rubro_id') ?: null,
            'marca_id' => Request::entero('marca_id') ?: null, 'proveedor_id' => Request::entero('proveedor_id') ?: null,
            'unidad_base' => Request::texto('unidad_base', 'Unidad'), 'costo' => Request::input('costo', '0'),
            'iva_alicuota' => Request::texto('iva_alicuota', '21.00'), 'impuesto_interno' => Request::input('impuesto_interno', '0'),
            'stock_minimo' => Request::input('stock_minimo', '0'), 'stock_maximo' => Request::input('stock_maximo', '0'),
            'maneja_lotes' => Request::entero('maneja_lotes'), 'ubicacion' => Request::texto('ubicacion'), 'activo' => Request::entero('activo', 1),
        ];
        $v = Validador::make($d, [
            'codigo' => 'requerido|max:30|unico:productos,codigo' . ($id ? ",$id" : ''),
            'descripcion' => 'requerido|max:200', 'codigo_barras' => 'max:30', 'unidad_base' => 'requerido|max:20',
            'costo' => 'numerico|min:0', 'iva_alicuota' => 'en:21.00,10.50,0.00', 'impuesto_interno' => 'numerico|min:0',
            'stock_minimo' => 'numerico|min:0', 'stock_maximo' => 'numerico|min:0',
        ]);
        $presentaciones = $this->leerPresentaciones($d['unidad_base']);
        $errores = $v->errores();
        foreach ($presentaciones as $pr) {
            if ($pr['codigo_barras'] && DB::valor('SELECT pp.id FROM producto_presentaciones pp WHERE pp.codigo_barras = ? AND pp.producto_id <> ? AND pp.activo = 1', [$pr['codigo_barras'], $id ?? 0])) {
                $errores['presentaciones'] = 'El código de barras ' . $pr['codigo_barras'] . ' ya está usado en otro producto.';
            }
        }
        if ($errores) {
            $this->formulario(array_merge($antes ?? [], $d, ['presentaciones' => $presentaciones]), $errores, $id);
            return;
        }
        foreach (['costo', 'impuesto_interno', 'stock_minimo', 'stock_maximo'] as $c) {
            $d[$c] = parse_decimal($d[$c]);
        }
        $id = DB::transaccion(function () use ($d, $id, $antes, $presentaciones) {
            $costoNuevo = (float) $d['costo'];
            if ($id) {
                unset($d['costo']);
                DB::actualizar('productos', $d + ['actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
                Auditoria::registrar('productos', 'editar', 'producto', $id, $antes, $d, 'Edición de producto ' . $d['codigo']);
            } else {
                $id = DB::insertar('productos', $d + ['costo_promedio' => $costoNuevo, 'creado_en' => ahora()]);
                Auditoria::registrar('productos', 'crear', 'producto', $id, null, $d, 'Alta de producto ' . $d['codigo']);
            }
            $this->guardarPresentaciones($id, $presentaciones);
            if ($antes && abs((float) $antes['costo'] - $costoNuevo) > 0.00005) {
                PrecioService::actualizarCosto($id, $costoNuevo, 'Edición manual del producto');
                Auditoria::registrar('productos', 'costo', 'producto', $id, ['costo' => $antes['costo']], ['costo' => $costoNuevo], 'Cambio de costo');
            }
            PrecioService::recalcularProducto($id, $antes ? 'Edición del producto' : 'Alta del producto');
            // Márgenes / precios por lista cargados en el mismo formulario
            if (isset($_POST['modo']) && Auth::puede('productos.precios')) {
                $this->aplicarPrecios(DB::fila('SELECT * FROM productos WHERE id = ?', [$id]));
            }
            if ($f = Request::archivo('foto')) {
                $nombre = Imagenes::guardarProducto($f, $id);
                DB::actualizar('productos', ['foto' => $nombre], 'id = :id', ['id' => $id]);
            }
            return $id;
        });
        $this->redirigir('/productos/' . $id, 'Producto guardado.');
    }

    private function leerPresentaciones(string $unidadBase): array
    {
        $out = [];
        $nombres = (array) Request::input('pres_nombre', []);
        foreach ($nombres as $i => $nombre) {
            $nombre = trim((string) $nombre);
            $factor = parse_decimal($_POST['pres_factor'][$i] ?? 1);
            if ($nombre === '' || $factor <= 0) {
                continue;
            }
            $out[] = [
                'id' => (int) ($_POST['pres_id'][$i] ?? 0), 'nombre' => mb_substr($nombre, 0, 40), 'factor' => $factor,
                'codigo_barras' => trim((string) ($_POST['pres_barras'][$i] ?? '')) ?: null,
                'es_venta' => (int) !empty($_POST['pres_venta'][$i]), 'es_compra' => (int) !empty($_POST['pres_compra'][$i]),
                'predeterminada_venta' => (int) ((string) ($_POST['pres_pv'] ?? '') === (string) $i),
                'predeterminada_compra' => (int) ((string) ($_POST['pres_pc'] ?? '') === (string) $i),
            ];
        }
        if (!array_filter($out, fn ($p) => abs($p['factor'] - 1) < 0.0001)) {
            array_unshift($out, ['id' => 0, 'nombre' => $unidadBase, 'factor' => 1, 'codigo_barras' => null, 'es_venta' => 1, 'es_compra' => 1, 'predeterminada_venta' => 0, 'predeterminada_compra' => 0]);
        }
        return $out;
    }

    private function guardarPresentaciones(int $productoId, array $presentaciones): void
    {
        $vigentes = [];
        foreach ($presentaciones as $pr) {
            $fila = $pr;
            unset($fila['id']);
            if ($pr['id'] && DB::valor('SELECT id FROM producto_presentaciones WHERE id = ? AND producto_id = ?', [$pr['id'], $productoId])) {
                DB::actualizar('producto_presentaciones', $fila + ['activo' => 1], 'id = :id', ['id' => $pr['id']]);
                $vigentes[] = $pr['id'];
            } else {
                $vigentes[] = DB::insertar('producto_presentaciones', $fila + ['producto_id' => $productoId, 'activo' => 1]);
            }
        }
        // Las que se quitaron se desactivan (los documentos viejos las referencian)
        DB::query('UPDATE producto_presentaciones SET activo = 0 WHERE producto_id = ? AND id NOT IN (' . DB::marcadores($vigentes) . ')', array_merge([$productoId], $vigentes));
    }

    public function ver(string $id): void
    {
        $p = DB::fila(
            'SELECT p.*, r.nombre AS rubro, m.nombre AS marca, pr.razon_social AS proveedor FROM productos p
             LEFT JOIN rubros r ON r.id = p.rubro_id LEFT JOIN marcas m ON m.id = p.marca_id LEFT JOIN proveedores pr ON pr.id = p.proveedor_id WHERE p.id = ?',
            [(int) $id]
        );
        if (!$p) {
            $this->buscarOFallar('productos', (int) $id);
        }
        $this->vista('productos/ver', [
            'titulo' => $p['descripcion'], 'p' => $p,
            'presentaciones' => DB::filas('SELECT * FROM producto_presentaciones WHERE producto_id = ? AND activo = 1 ORDER BY factor', [$id]),
            'precios' => DB::filas(
                'SELECT l.id AS lista_id, l.nombre, l.margen_porc AS margen_lista, l.redondeo, pp.modo, pp.margen_porc, pp.precio, pp.actualizado_en
                 FROM listas_precios l LEFT JOIN producto_precios pp ON pp.lista_id = l.id AND pp.producto_id = ? WHERE l.activo = 1 ORDER BY l.orden, l.id',
                [$id]
            ),
            'stock' => DB::filas('SELECT d.nombre, d.tipo, s.cantidad, s.reservado FROM stock s JOIN depositos d ON d.id = s.deposito_id WHERE s.producto_id = ? ORDER BY d.tipo, d.nombre', [$id]),
            'lotes' => DB::filas('SELECT l.*, d.nombre AS deposito FROM stock_lotes l JOIN depositos d ON d.id = l.deposito_id WHERE l.producto_id = ? AND l.cantidad <> 0 ORDER BY l.vencimiento', [$id]),
            'movimientos' => DB::filas('SELECT m.*, d.nombre AS deposito FROM stock_movimientos m JOIN depositos d ON d.id = m.deposito_id WHERE m.producto_id = ? ORDER BY m.id DESC LIMIT 15', [$id]),
            'historial' => DB::filas('SELECT h.*, l.nombre AS lista, u.nombre AS usuario FROM precios_historial h LEFT JOIN listas_precios l ON l.id = h.lista_id LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.producto_id = ? ORDER BY h.id DESC LIMIT 20', [$id]),
            'promos' => DB::filas('SELECT * FROM promociones WHERE producto_id = ? AND activo = 1', [$id]),
        ]);
    }

    /** Guarda los precios por lista (modo margen con margen propio o precio fijo). */
    public function guardarPrecios(string $id): void
    {
        $p = $this->buscarOFallar('productos', (int) $id);
        DB::transaccion(fn () => $this->aplicarPrecios($p));
        $this->volver('Precios actualizados.');
    }

    /**
     * Aplica lo enviado en modo[lista], margen[lista] y precio[lista]: por margen (vacío = margen de la lista) o precio fijo.
     */
    private function aplicarPrecios(array $p): void
    {
        $modoRed = (string) \App\Core\Ajustes::get('redondeo_modo', 'arriba');
        foreach (DB::filas('SELECT * FROM listas_precios WHERE activo = 1') as $l) {
            if (!isset($_POST['modo'][$l['id']])) {
                continue;
            }
            $modo = $_POST['modo'][$l['id']] === 'fijo' ? 'fijo' : 'margen';
            $actual = DB::fila('SELECT * FROM producto_precios WHERE producto_id = ? AND lista_id = ?', [$p['id'], $l['id']]);
            $anterior = $actual ? (float) $actual['precio'] : null;
            if ($modo === 'fijo') {
                $precio = round(parse_decimal($_POST['precio'][$l['id']] ?? 0), 2);
                if ($precio <= 0) {
                    throw new ErrorNegocio('El precio fijo de la lista ' . $l['nombre'] . ' debe ser mayor a cero.');
                }
                PrecioService::guardarPrecio((int) $p['id'], (int) $l['id'], $precio, $anterior, 'Precio fijo manual', 'fijo', null, true);
            } else {
                $margenTxt = trim((string) ($_POST['margen'][$l['id']] ?? ''));
                $margen = $margenTxt === '' ? null : parse_decimal($margenTxt);
                if ($margen !== null && ($margen < 0 || $margen > 1000)) {
                    throw new ErrorNegocio('El margen de la lista ' . $l['nombre'] . ' debe estar entre 0 y 1000 %.');
                }
                $precio = PrecioService::calcularPrecio(PrecioService::costoBase($p), $margen ?? (float) $l['margen_porc'], (float) $p['iva_alicuota'], (float) $p['impuesto_interno'], (float) $l['redondeo'], $modoRed);
                PrecioService::guardarPrecio((int) $p['id'], (int) $l['id'], $precio, $anterior, 'Cambio de margen', 'margen', $margen, true);
            }
        }
        Auditoria::registrar('productos', 'precios', 'producto', (int) $p['id'], null, ['modos' => $_POST['modo'] ?? [], 'margenes' => $_POST['margen'] ?? []], 'Actualización de precios de ' . $p['descripcion']);
    }

    public function alternarEstado(string $id): void
    {
        $p = $this->buscarOFallar('productos', (int) $id);
        $nuevo = (int) $p['activo'] ? 0 : 1;
        DB::actualizar('productos', ['activo' => $nuevo, 'actualizado_en' => ahora()], 'id = :id', ['id' => (int) $id]);
        Auditoria::registrar('productos', $nuevo ? 'activar' : 'desactivar', 'producto', (int) $id, ['activo' => $p['activo']], ['activo' => $nuevo]);
        $this->volver($nuevo ? 'Producto activado.' : 'Producto desactivado.');
    }

    // ---------------- Actualización masiva e historial ----------------

    public function masiva(): void
    {
        $this->vista('productos/masiva', [
            'titulo' => 'Actualización masiva de precios',
            'rubros' => DB::pares('SELECT id, nombre FROM rubros WHERE activo = 1 ORDER BY nombre'),
            'marcas' => DB::pares('SELECT id, nombre FROM marcas WHERE activo = 1 ORDER BY nombre'),
            'proveedores' => DB::pares('SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social'),
            'listas' => DB::pares('SELECT id, nombre FROM listas_precios WHERE activo = 1 ORDER BY orden, id'),
        ]);
    }

    public function aplicarMasiva(): void
    {
        $filtros = ['rubro_id' => Request::entero('rubro_id'), 'marca_id' => Request::entero('marca_id'), 'proveedor_id' => Request::entero('proveedor_id')];
        if (Request::texto('tipo') === 'margen') {
            // Margen propio para un grupo de productos en una lista (vacío = volver al margen de la lista)
            $lista = Request::entero('lista_id');
            if (!$lista) {
                throw new ErrorNegocio('Para fijar un margen elegí la lista de precios.');
            }
            $texto = Request::texto('margen');
            $margen = $texto === null ? null : parse_decimal($texto);
            $n = PrecioService::fijarMargen($filtros, $lista, $margen, Request::texto('motivo') ?? ($margen === null ? 'Vuelve al margen de la lista' : 'Margen propio ' . num($margen) . ' %'));
            Auditoria::registrar('productos', 'fijar_margen', null, null, null, $filtros + ['lista_id' => $lista, 'margen' => $margen ?? 'de la lista'], "Margen fijado en {$n} productos");
            $this->redirigir('/productos/historial-precios', "Margen aplicado a $n productos.");
        }
        $tipo = Request::texto('tipo') === 'precio' ? 'precio' : 'costo';
        $porc = Request::decimal('porcentaje');
        $motivo = Request::texto('motivo') ?? ('Actualización masiva ' . ($porc > 0 ? '+' : '') . num($porc) . ' %');
        $n = PrecioService::actualizacionMasiva($filtros, $tipo, $porc, Request::entero('lista_id') ?: null, $motivo);
        Auditoria::registrar('productos', 'actualizacion_masiva', null, null, null, $filtros + ['tipo' => $tipo, 'porcentaje' => $porc], "Actualización masiva de {$tipo}s: {$n} productos");
        $this->redirigir('/productos/historial-precios', "Se actualizaron $n productos.");
    }

    public function historial(): void
    {
        $where = [];
        $params = [];
        if ($q = trim((string) Request::get('q', ''))) {
            $where[] = '(p.descripcion LIKE ? OR p.codigo LIKE ?)';
            array_push($params, "%$q%", "%$q%");
        }
        if ($d = parse_fecha(Request::get('desde'))) {
            $where[] = 'h.fecha >= ?';
            $params[] = $d . ' 00:00:00';
        }
        $sql = 'SELECT h.*, p.codigo, p.descripcion, COALESCE(l.nombre, \'Costo\') AS lista, u.nombre AS usuario
                FROM precios_historial h JOIN productos p ON p.id = h.producto_id LEFT JOIN listas_precios l ON l.id = h.lista_id LEFT JOIN usuarios u ON u.id = h.usuario_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $pag = new Paginador($sql, $params, ['fecha' => 'h.id'], 'fecha', 'desc', 50);
        $this->vista('productos/historial', ['titulo' => 'Historial de precios y costos', 'pag' => $pag]);
    }

    // ---------------- Importación / exportación CSV ----------------

    public const COLUMNAS_CSV = ['codigo', 'codigo_barras', 'descripcion', 'rubro', 'marca', 'proveedor_cuit', 'unidad_base', 'costo', 'iva', 'impuesto_interno', 'stock_minimo', 'stock_maximo', 'ubicacion', 'presentaciones', 'activo'];

    public function importar(): void
    {
        $this->vista('productos/importar', ['titulo' => 'Importar / exportar productos', 'resultado' => $_SESSION['import_resultado'] ?? null]);
        unset($_SESSION['import_resultado']);
    }

    public function plantilla(): void
    {
        $csv = "\xEF\xBB\xBF" . implode(';', self::COLUMNAS_CSV) . "\n"
            . "00999;7790000000001;Alfajor ejemplo 50 g;Golosinas;Marca Ejemplo;30-12345678-9;Unidad;150,50;21;0;24;120;A-01;Display:24:7790000000002|Caja:288:;1\n";
        \App\Core\Response::descarga($csv, 'plantilla_productos.csv', 'text/csv; charset=utf-8');
    }

    public function exportar(): void
    {
        $filas = [];
        $sql = 'SELECT p.*, r.nombre AS rubro, m.nombre AS marca, pr.cuit AS proveedor_cuit FROM productos p
                LEFT JOIN rubros r ON r.id = p.rubro_id LEFT JOIN marcas m ON m.id = p.marca_id LEFT JOIN proveedores pr ON pr.id = p.proveedor_id ORDER BY p.codigo';
        $pres = [];
        foreach (DB::filas('SELECT producto_id, nombre, factor, codigo_barras FROM producto_presentaciones WHERE activo = 1 AND factor <> 1 ORDER BY factor') as $x) {
            $pres[$x['producto_id']][] = $x['nombre'] . ':' . cant($x['factor']) . ':' . $x['codigo_barras'];
        }
        $formato = (string) Request::get('formato', 'csv');
        if ($formato === 'csv') {
            $fh = fopen('php://temp', 'r+');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, self::COLUMNAS_CSV, ';');
            foreach (DB::filas($sql) as $p) {
                fputcsv($fh, [
                    $p['codigo'], $p['codigo_barras'], $p['descripcion'], $p['rubro'], $p['marca'], $p['proveedor_cuit'], $p['unidad_base'],
                    number_format((float) $p['costo'], 2, ',', ''), rtrim(rtrim(number_format((float) $p['iva_alicuota'], 2, ',', ''), '0'), ','),
                    number_format((float) $p['impuesto_interno'], 2, ',', ''), cant($p['stock_minimo']), cant($p['stock_maximo']), $p['ubicacion'],
                    implode('|', $pres[$p['id']] ?? []), $p['activo'],
                ], ';');
            }
            rewind($fh);
            \App\Core\Response::descarga((string) stream_get_contents($fh), Exportador::nombreArchivo('productos') . '.csv', 'text/csv; charset=utf-8');
            return;
        }
        // Lista de precios completa (Excel / PDF / impresión)
        $listas = DB::pares('SELECT id, nombre FROM listas_precios WHERE activo = 1 ORDER BY orden, id');
        $columnas = ['codigo' => ['Código', 'texto'], 'descripcion' => ['Descripción', 'texto'], 'rubro' => ['Rubro', 'texto'], 'costo' => ['Costo', 'money']];
        foreach ($listas as $lid => $nombre) {
            $columnas['l' . $lid] = [$nombre, 'money'];
        }
        $precios = [];
        foreach (DB::filas('SELECT producto_id, lista_id, precio FROM producto_precios') as $x) {
            $precios[$x['producto_id']]['l' . $x['lista_id']] = $x['precio'];
        }
        $filas = [];
        foreach (DB::filas($sql . '') as $p) {
            if (!(int) $p['activo']) {
                continue;
            }
            $filas[] = ['codigo' => $p['codigo'], 'descripcion' => $p['descripcion'], 'rubro' => $p['rubro'], 'costo' => $p['costo']] + ($precios[$p['id']] ?? []);
        }
        Exportador::enviar($formato, 'Lista de precios', $columnas, $filas, null, 'Precios finales por unidad, IVA incluido');
    }

    public function procesarImportacion(): void
    {
        $f = Request::archivo('archivo');
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            throw new ErrorNegocio('Elegí un archivo CSV.');
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            throw new ErrorNegocio('El archivo supera los 5 MB.');
        }
        $contenido = (string) file_get_contents($f['tmp_name']);
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $lineas = preg_split('/\r\n|\n|\r/', trim($contenido));
        $sep = substr_count($lineas[0] ?? '', ';') >= substr_count($lineas[0] ?? '', ',') ? ';' : ',';
        $encabezado = array_map(fn ($c) => strtolower(trim($c)), str_getcsv(array_shift($lineas), $sep));
        if (!in_array('codigo', $encabezado, true) || !in_array('descripcion', $encabezado, true)) {
            throw new ErrorNegocio('El archivo debe tener al menos las columnas "codigo" y "descripcion". Descargá la plantilla.');
        }
        $res = ['creados' => 0, 'actualizados' => 0, 'errores' => []];
        DB::transaccion(function () use ($lineas, $sep, $encabezado, &$res) {
            foreach ($lineas as $n => $linea) {
                if (trim($linea) === '') {
                    continue;
                }
                $vals = str_getcsv($linea, $sep);
                $r = [];
                foreach ($encabezado as $i => $col) {
                    $r[$col] = trim((string) ($vals[$i] ?? ''));
                }
                $fila = $n + 2;
                if ($r['codigo'] === '' || $r['descripcion'] === '') {
                    $res['errores'][] = "Fila $fila: falta código o descripción.";
                    continue;
                }
                $iva = isset($r['iva']) && $r['iva'] !== '' ? parse_decimal($r['iva']) : 21.0;
                if (!in_array(round($iva, 2), [21.0, 10.5, 0.0], true)) {
                    $res['errores'][] = "Fila $fila: alícuota de IVA inválida ({$r['iva']}).";
                    continue;
                }
                $datos = [
                    'codigo' => mb_substr($r['codigo'], 0, 30), 'descripcion' => mb_substr($r['descripcion'], 0, 200),
                    'codigo_barras' => ($r['codigo_barras'] ?? '') ?: null,
                    'rubro_id' => !empty($r['rubro']) ? $this->idPorNombre('rubros', $r['rubro']) : null,
                    'marca_id' => !empty($r['marca']) ? $this->idPorNombre('marcas', $r['marca']) : null,
                    'proveedor_id' => !empty($r['proveedor_cuit']) ? (DB::valor('SELECT id FROM proveedores WHERE REPLACE(cuit, "-", "") = ?', [preg_replace('/\D/', '', $r['proveedor_cuit'])]) ?: null) : null,
                    'unidad_base' => ($r['unidad_base'] ?? '') ?: 'Unidad',
                    'iva_alicuota' => $iva,
                    'impuesto_interno' => parse_decimal($r['impuesto_interno'] ?? 0),
                    'stock_minimo' => parse_decimal($r['stock_minimo'] ?? 0),
                    'stock_maximo' => parse_decimal($r['stock_maximo'] ?? 0),
                    'ubicacion' => ($r['ubicacion'] ?? '') ?: null,
                    'activo' => ($r['activo'] ?? '1') === '0' ? 0 : 1,
                ];
                $costo = parse_decimal($r['costo'] ?? 0);
                $id = DB::valor('SELECT id FROM productos WHERE codigo = ?', [$datos['codigo']]);
                if ($id) {
                    DB::actualizar('productos', $datos + ['actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
                    PrecioService::actualizarCosto((int) $id, $costo, 'Importación CSV', false);
                    $res['actualizados']++;
                } else {
                    $id = DB::insertar('productos', $datos + ['costo' => $costo, 'costo_promedio' => $costo, 'creado_en' => ahora()]);
                    $res['creados']++;
                }
                $pres = [['id' => 0, 'nombre' => $datos['unidad_base'], 'factor' => 1, 'codigo_barras' => $datos['codigo_barras'], 'es_venta' => 1, 'es_compra' => 1, 'predeterminada_venta' => 1, 'predeterminada_compra' => 0]];
                $actuales = DB::pares('SELECT CONCAT(nombre, "|", factor + 0), id FROM producto_presentaciones WHERE producto_id = ?', [$id]);
                $pres[0]['id'] = (int) ($actuales[$datos['unidad_base'] . '|1'] ?? 0);
                foreach (array_filter(explode('|', (string) ($r['presentaciones'] ?? ''))) as $txt) {
                    [$nom, $fac, $bar] = array_pad(explode(':', $txt), 3, '');
                    if (trim($nom) !== '' && parse_decimal($fac) > 0) {
                        $pres[] = ['id' => (int) ($actuales[trim($nom) . '|' . (parse_decimal($fac) + 0)] ?? 0), 'nombre' => trim($nom), 'factor' => parse_decimal($fac), 'codigo_barras' => trim($bar) ?: null, 'es_venta' => 1, 'es_compra' => 1, 'predeterminada_venta' => 0, 'predeterminada_compra' => 1];
                    }
                }
                $this->guardarPresentaciones((int) $id, $pres);
                PrecioService::recalcularProducto((int) $id, 'Importación CSV');
            }
        });
        Auditoria::registrar('productos', 'importar', null, null, null, ['creados' => $res['creados'], 'actualizados' => $res['actualizados']], 'Importación de productos por CSV');
        $_SESSION['import_resultado'] = $res;
        $this->redirigir('/productos/importar', "Importación terminada: {$res['creados']} nuevos, {$res['actualizados']} actualizados.");
    }

    private function idPorNombre(string $tabla, string $nombre): int
    {
        $id = DB::valor("SELECT id FROM $tabla WHERE nombre = ?", [$nombre]);
        return $id ? (int) $id : DB::insertar($tabla, ['nombre' => mb_substr($nombre, 0, 80), 'activo' => 1]);
    }
}
