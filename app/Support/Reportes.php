<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Ajustes;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Services\CajaService;

/**
 * Definición y cálculo de los reportes. Cada reporte devuelve columnas, filas, totales y,
 * opcionalmente, qué columnas graficar.
 */
final class Reportes
{
    /** clave => [título, grupo, descripción, filtros] */
    public static function catalogo(): array
    {
        return [
            'ventas' => ['Ventas', 'Ventas', 'Ventas por período, cliente, producto, rubro, marca, zona, preventista o tipo de comprobante.', ['fechas', 'agrupar_ventas']],
            'cobranzas' => ['Cobranzas por forma de pago', 'Ventas', 'Cobros recibidos por forma de pago (efectivo, transferencia, Mercado Pago, tarjeta, cheque).', ['fechas', 'agrupar_cobranzas']],
            'rentabilidad' => ['Rentabilidad / margen bruto', 'Ventas', 'Venta neta sin impuestos menos costo de la mercadería vendida.', ['fechas', 'agrupar_rentabilidad']],
            'comisiones' => ['Comisiones de preventistas', 'Ventas', 'Ventas netas, cobranzas y comisión calculada por preventista.', ['fechas']],
            'compras' => ['Compras', 'Compras', 'Facturas de proveedores por proveedor o por mes.', ['fechas', 'agrupar_compras']],
            'stock_valorizado' => ['Stock valorizado', 'Stock', 'Existencias valorizadas a costo, por producto o por rubro.', ['agrupar_stock']],
            'kardex' => ['Kardex por producto', 'Stock', 'Movimientos de un producto con saldo.', ['fechas', 'producto']],
            'sin_movimiento' => ['Productos sin movimiento', 'Stock', 'Productos con stock que no se vendieron en los últimos N días.', ['dias']],
            'rotacion' => ['Rotación de inventario', 'Stock', 'Unidades vendidas, stock actual, rotación y días de cobertura.', ['fechas']],
            'cc_clientes' => ['Cuentas corrientes de clientes', 'Cuentas corrientes', 'Saldos y antigüedad de la deuda de clientes.', []],
            'cc_proveedores' => ['Cuentas corrientes de proveedores', 'Cuentas corrientes', 'Saldos y antigüedad de la deuda con proveedores.', []],
            'iva_ventas' => ['Libro IVA Ventas', 'Impuestos', 'Comprobantes fiscales emitidos con IVA discriminado (formato simple exportable).', ['fechas']],
            'iva_compras' => ['Libro IVA Compras', 'Impuestos', 'Comprobantes de proveedores con IVA y percepciones.', ['fechas']],
            'rendiciones' => ['Rendiciones de reparto', 'Reparto', 'Hojas de ruta rendidas con diferencias de dinero y mercadería.', ['fechas']],
            'rechazos' => ['Entregas rechazadas y motivos', 'Reparto', 'Paradas rechazadas o con cliente cerrado, con motivo.', ['fechas']],
            'flujo_caja' => ['Flujo de caja', 'Finanzas', 'Ingresos y egresos diarios de cajas y bancos.', ['fechas']],
            'resultado' => ['Resultado del mes', 'Finanzas', 'Ventas netas − costo de mercadería − gastos, mes a mes.', ['meses']],
        ];
    }

    public static function generar(string $clave, array $f): array
    {
        $desde = parse_fecha($f['desde'] ?? '') ?? date('Y-m-01');
        $hasta = parse_fecha($f['hasta'] ?? '') ?? hoy();
        $r1 = $desde . ' 00:00:00';
        $r2 = $hasta . ' 23:59:59';
        $tv = Consultas::TIPOS_VENTA;
        $sg = Consultas::signo();
        $sub = 'Del ' . fecha($desde) . ' al ' . fecha($hasta);

        switch ($clave) {
            case 'ventas':
                $agr = $f['agrupar'] ?? 'dia';
                $porItem = in_array($agr, ['producto', 'rubro', 'marca'], true);
                [$expr, $etq] = match ($agr) {
                    'mes' => ["DATE_FORMAT(c.fecha, '%Y-%m')", 'Mes'],
                    'cliente' => ['c.cliente_nombre', 'Cliente'],
                    'zona' => ["COALESCE(z.nombre, 'Sin zona')", 'Zona'],
                    'preventista' => ["COALESCE(pr.nombre, 'Sin preventista')", 'Preventista'],
                    'tipo' => ['c.tipo', 'Comprobante'],
                    'producto' => ['p.descripcion', 'Producto'],
                    'rubro' => ["COALESCE(r.nombre, 'Sin rubro')", 'Rubro'],
                    'marca' => ["COALESCE(m.nombre, 'Sin marca')", 'Marca'],
                    default => ['DATE(c.fecha)', 'Día'],
                };
                if ($porItem) {
                    $filas = DB::filas(
                        "SELECT $expr AS grupo, SUM($sg * ci.cantidad_base) AS unidades, SUM($sg * ci.neto) AS neto, SUM($sg * ci.iva) AS iva, SUM($sg * ci.total) AS total, COUNT(DISTINCT c.id) AS comprobantes
                         FROM comprobante_items ci JOIN comprobantes c ON c.id = ci.comprobante_id JOIN productos p ON p.id = ci.producto_id
                         LEFT JOIN rubros r ON r.id = p.rubro_id LEFT JOIN marcas m ON m.id = p.marca_id
                         WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha BETWEEN ? AND ? GROUP BY grupo ORDER BY total DESC",
                        [$r1, $r2]
                    );
                    $col = ['grupo' => [$etq, 'texto'], 'unidades' => ['Unidades', 'cant'], 'comprobantes' => ['Comprob.', 'entero'], 'neto' => ['Neto', 'money'], 'iva' => ['IVA', 'money'], 'total' => ['Total', 'money']];
                } else {
                    $filas = DB::filas(
                        "SELECT $expr AS grupo, COUNT(*) AS comprobantes, SUM($sg * (c.neto_21 + c.neto_105 + c.neto_exento)) AS neto, SUM($sg * (c.iva_21 + c.iva_105)) AS iva,
                                SUM($sg * c.impuestos_internos) AS internos, SUM($sg * c.total) AS total
                         FROM comprobantes c JOIN clientes cl ON cl.id = c.cliente_id LEFT JOIN zonas z ON z.id = cl.zona_id LEFT JOIN preventistas pr ON pr.id = c.preventista_id
                         WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha BETWEEN ? AND ? GROUP BY grupo ORDER BY " . (in_array($agr, ['dia', 'mes'], true) ? 'grupo' : 'total DESC'),
                        [$r1, $r2]
                    );
                    if ($agr === 'dia') {
                        foreach ($filas as &$x) {
                            $x['grupo'] = fecha($x['grupo']);
                        }
                        unset($x);
                    }
                    $col = ['grupo' => [$etq, 'texto'], 'comprobantes' => ['Comprob.', 'entero'], 'neto' => ['Neto', 'money'], 'iva' => ['IVA', 'money'], 'internos' => ['Internos', 'money'], 'total' => ['Total', 'money']];
                }
                return self::resultado($col, $filas, ['grupo'], $sub . ' · por ' . mb_strtolower($etq), ['grupo', 'total']);

            case 'cobranzas':
                $agr = $f['agrupar'] ?? 'forma';
                $expr = $agr === 'dia' ? 'DATE(r.fecha)' : ($agr === 'origen' ? 'r.origen' : 'rp.forma_pago');
                $filas = DB::filas(
                    "SELECT $expr AS grupo, COUNT(DISTINCT r.id) AS recibos, SUM(rp.importe) AS total FROM recibos r JOIN recibo_pagos rp ON rp.recibo_id = r.id
                     WHERE r.estado = 'emitido' AND r.fecha BETWEEN ? AND ? GROUP BY grupo ORDER BY " . ($agr === 'dia' ? 'grupo' : 'total DESC'),
                    [$r1, $r2]
                );
                foreach ($filas as &$x) {
                    $x['grupo'] = $agr === 'dia' ? fecha($x['grupo']) : ($agr === 'origen' ? ucfirst($x['grupo']) : forma_pago($x['grupo']));
                }
                unset($x);
                return self::resultado(['grupo' => [$agr === 'dia' ? 'Día' : ($agr === 'origen' ? 'Origen' : 'Forma de pago'), 'texto'], 'recibos' => ['Recibos', 'entero'], 'total' => ['Total', 'money']], $filas, ['grupo'], $sub, ['grupo', 'total']);

            case 'rentabilidad':
                $agr = $f['agrupar'] ?? 'producto';
                [$expr, $etq] = match ($agr) {
                    'rubro' => ["COALESCE(r.nombre, 'Sin rubro')", 'Rubro'],
                    'cliente' => ['c.cliente_nombre', 'Cliente'],
                    'mes' => ["DATE_FORMAT(c.fecha, '%Y-%m')", 'Mes'],
                    default => ['p.descripcion', 'Producto'],
                };
                $filas = DB::filas(
                    "SELECT $expr AS grupo, SUM($sg * ci.cantidad_base) AS unidades, SUM($sg * ci.neto) AS venta_neta,
                            SUM(CASE WHEN c.tipo LIKE 'NC%' THEN IF(ci.reingresa_stock = 1, -1, 0) ELSE 1 END * ci.costo_unitario * (ci.cantidad_base + ci.bonificacion_base)) AS costo
                     FROM comprobante_items ci JOIN comprobantes c ON c.id = ci.comprobante_id JOIN productos p ON p.id = ci.producto_id LEFT JOIN rubros r ON r.id = p.rubro_id
                     WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha BETWEEN ? AND ?
                     GROUP BY grupo ORDER BY " . ($agr === 'mes' ? 'grupo' : 'venta_neta DESC'),
                    [$r1, $r2]
                );
                foreach ($filas as &$x) {
                    $x['margen'] = round((float) $x['venta_neta'] - (float) $x['costo'], 2);
                    $x['margen_porc'] = (float) $x['venta_neta'] > 0 ? round($x['margen'] * 100 / (float) $x['venta_neta'], 1) : 0;
                    $x['markup'] = (float) $x['costo'] > 0 ? round($x['margen'] * 100 / (float) $x['costo'], 1) : 0;
                }
                unset($x);
                $res = self::resultado(['grupo' => [$etq, 'texto'], 'unidades' => ['Unidades', 'cant'], 'venta_neta' => ['Venta neta', 'money'], 'costo' => ['Costo (CMV)', 'money'], 'margen' => ['Margen $', 'money'], 'margen_porc' => ['Margen %', 'porc'], 'markup' => ['Markup %', 'porc']], $filas, ['grupo', 'margen_porc', 'markup'], $sub . ' · venta neta sin IVA ni internos', ['grupo', 'margen']);
                $t = &$res['totales'];
                $t['margen_porc'] = (float) $t['venta_neta'] > 0 ? round((float) $t['margen'] * 100 / (float) $t['venta_neta'], 1) : 0;
                $t['markup'] = (float) $t['costo'] > 0 ? round((float) $t['margen'] * 100 / (float) $t['costo'], 1) : 0;
                return $res;

            case 'comisiones':
                $filas = DB::filas(
                    "SELECT p.nombre, p.comision_porc, p.comision_base,
                            COALESCE((SELECT SUM($sg * (c.total - c.iva_21 - c.iva_105 - c.impuestos_internos)) FROM comprobantes c WHERE c.preventista_id = p.id AND c.estado = 'emitido' AND c.tipo IN ('FA','FB','FC','RM','NCA','NCB','NCC') AND c.fecha BETWEEN ? AND ?), 0) AS ventas,
                            COALESCE((SELECT SUM(r.total) FROM recibos r WHERE r.preventista_id = p.id AND r.estado = 'emitido' AND r.fecha BETWEEN ? AND ?), 0) AS cobranzas,
                            (SELECT COUNT(*) FROM visitas v WHERE v.preventista_id = p.id AND v.fecha BETWEEN ? AND ?) AS visitas
                     FROM preventistas p ORDER BY p.nombre",
                    [$r1, $r2, $r1, $r2, $r1, $r2]
                );
                foreach ($filas as &$x) {
                    $x['base'] = $x['comision_base'] === 'cobranzas' ? 'Cobranzas' : 'Ventas';
                    $x['comision'] = round(($x['comision_base'] === 'cobranzas' ? (float) $x['cobranzas'] : (float) $x['ventas']) * (float) $x['comision_porc'] / 100, 2);
                }
                unset($x);
                return self::resultado(['nombre' => ['Preventista', 'texto'], 'visitas' => ['Visitas', 'entero'], 'ventas' => ['Ventas netas', 'money'], 'cobranzas' => ['Cobranzas', 'money'], 'base' => ['Base', 'texto'], 'comision_porc' => ['%', 'porc'], 'comision' => ['Comisión', 'money']], $filas, ['nombre', 'base', 'comision_porc'], $sub, ['nombre', 'comision']);

            case 'compras':
                $agr = $f['agrupar'] ?? 'proveedor';
                $expr = $agr === 'mes' ? "DATE_FORMAT(f.fecha, '%Y-%m')" : 'pr.razon_social';
                $filas = DB::filas(
                    "SELECT $expr AS grupo, COUNT(*) AS comprobantes, SUM(CASE WHEN f.tipo LIKE 'NC%' THEN -1 ELSE 1 END * (f.neto_gravado + f.no_gravado)) AS neto,
                            SUM(CASE WHEN f.tipo LIKE 'NC%' THEN -1 ELSE 1 END * (f.iva_21 + f.iva_105 + f.iva_27)) AS iva,
                            SUM(CASE WHEN f.tipo LIKE 'NC%' THEN -1 ELSE 1 END * (f.percepcion_iva + f.percepcion_iibb)) AS percepciones,
                            SUM(CASE WHEN f.tipo LIKE 'NC%' THEN -1 ELSE 1 END * f.total) AS total
                     FROM facturas_proveedor f JOIN proveedores pr ON pr.id = f.proveedor_id WHERE f.estado = 'registrada' AND f.fecha BETWEEN ? AND ? GROUP BY grupo ORDER BY " . ($agr === 'mes' ? 'grupo' : 'total DESC'),
                    [$desde, $hasta]
                );
                return self::resultado(['grupo' => [$agr === 'mes' ? 'Mes' : 'Proveedor', 'texto'], 'comprobantes' => ['Comprob.', 'entero'], 'neto' => ['Neto', 'money'], 'iva' => ['IVA', 'money'], 'percepciones' => ['Percepciones', 'money'], 'total' => ['Total', 'money']], $filas, ['grupo'], $sub, ['grupo', 'total']);

            case 'stock_valorizado':
                $costo = Ajustes::get('costo_metodo') === 'promedio' ? 'IF(p.costo_promedio > 0, p.costo_promedio, p.costo)' : 'p.costo';
                $stock = "(SELECT COALESCE(SUM(s.cantidad), 0) FROM stock s JOIN depositos d ON d.id = s.deposito_id WHERE s.producto_id = p.id AND d.tipo = 'deposito')";
                if (($f['agrupar'] ?? 'producto') === 'rubro') {
                    $filas = DB::filas("SELECT COALESCE(r.nombre, 'Sin rubro') AS grupo, COUNT(*) AS productos, SUM($stock) AS unidades, SUM($stock * $costo) AS valor FROM productos p LEFT JOIN rubros r ON r.id = p.rubro_id WHERE p.activo = 1 GROUP BY grupo ORDER BY valor DESC");
                    return self::resultado(['grupo' => ['Rubro', 'texto'], 'productos' => ['Productos', 'entero'], 'unidades' => ['Unidades', 'cant'], 'valor' => ['Valor a costo', 'money']], $filas, ['grupo'], 'Al ' . fecha(hoy()), ['grupo', 'valor']);
                }
                $filas = DB::filas("SELECT p.codigo, p.descripcion AS grupo, r.nombre AS rubro, $stock AS unidades, $costo AS costo, $stock * $costo AS valor FROM productos p LEFT JOIN rubros r ON r.id = p.rubro_id WHERE p.activo = 1 ORDER BY valor DESC");
                return self::resultado(['codigo' => ['Código', 'texto'], 'grupo' => ['Producto', 'texto'], 'rubro' => ['Rubro', 'texto'], 'unidades' => ['Unidades', 'cant'], 'costo' => ['Costo', 'money'], 'valor' => ['Valor', 'money']], $filas, ['codigo', 'grupo', 'rubro', 'costo'], 'Al ' . fecha(hoy()));

            case 'kardex':
                $pid = (int) ($f['producto_id'] ?? 0);
                if (!$pid) {
                    return self::resultado(['fecha' => ['Fecha', 'fecha_hora']], [], [], 'Elegí un producto');
                }
                $filas = DB::filas(
                    "SELECT m.fecha, d.nombre AS deposito, m.tipo, m.motivo, m.cantidad, m.saldo, m.costo_unitario FROM stock_movimientos m JOIN depositos d ON d.id = m.deposito_id
                     WHERE m.producto_id = ? AND m.fecha BETWEEN ? AND ? ORDER BY m.id",
                    [$pid, $r1, $r2]
                );
                foreach ($filas as &$x) {
                    $x['tipo'] = \App\Services\StockService::TIPOS[$x['tipo']] ?? $x['tipo'];
                    $x['entrada'] = (float) $x['cantidad'] > 0 ? $x['cantidad'] : null;
                    $x['salida'] = (float) $x['cantidad'] < 0 ? -$x['cantidad'] : null;
                }
                unset($x);
                $desc = DB::valor('SELECT descripcion FROM productos WHERE id = ?', [$pid]);
                return self::resultado(['fecha' => ['Fecha', 'fecha_hora'], 'deposito' => ['Depósito', 'texto'], 'tipo' => ['Movimiento', 'texto'], 'motivo' => ['Detalle', 'texto'], 'entrada' => ['Entrada', 'cant'], 'salida' => ['Salida', 'cant'], 'saldo' => ['Saldo', 'cant'], 'costo_unitario' => ['Costo', 'money']], $filas, ['fecha', 'deposito', 'tipo', 'motivo', 'saldo', 'costo_unitario'], $desc . ' · ' . $sub);

            case 'sin_movimiento':
                $dias = max(1, (int) ($f['dias'] ?? 30));
                $costo = 'p.costo';
                $filas = DB::filas(
                    "SELECT p.codigo, p.descripcion, r.nombre AS rubro, (SELECT COALESCE(SUM(s.cantidad), 0) FROM stock s WHERE s.producto_id = p.id) AS unidades,
                            (SELECT MAX(m.fecha) FROM stock_movimientos m WHERE m.producto_id = p.id AND m.tipo = 'venta') AS ultima_venta, $costo AS costo
                     FROM productos p LEFT JOIN rubros r ON r.id = p.rubro_id WHERE p.activo = 1
                     HAVING unidades > 0 AND (ultima_venta IS NULL OR ultima_venta < DATE_SUB(NOW(), INTERVAL ? DAY)) ORDER BY ultima_venta",
                    [$dias]
                );
                foreach ($filas as &$x) {
                    $x['valor'] = round((float) $x['unidades'] * (float) $x['costo'], 2);
                }
                unset($x);
                return self::resultado(['codigo' => ['Código', 'texto'], 'descripcion' => ['Producto', 'texto'], 'rubro' => ['Rubro', 'texto'], 'unidades' => ['Stock', 'cant'], 'ultima_venta' => ['Última venta', 'fecha'], 'valor' => ['Valor inmovilizado', 'money']], $filas, ['codigo', 'descripcion', 'rubro', 'ultima_venta'], "Sin ventas en los últimos $dias días");

            case 'rotacion':
                $dias = max(1, (int) ((strtotime($hasta) - strtotime($desde)) / 86400) + 1);
                $filas = DB::filas(
                    "SELECT p.codigo, p.descripcion, COALESCE(SUM(-m.cantidad), 0) AS vendido,
                            (SELECT COALESCE(SUM(s.cantidad), 0) FROM stock s JOIN depositos d ON d.id = s.deposito_id WHERE s.producto_id = p.id AND d.tipo = 'deposito') AS stock
                     FROM productos p LEFT JOIN stock_movimientos m ON m.producto_id = p.id AND m.tipo = 'venta' AND m.fecha BETWEEN ? AND ?
                     WHERE p.activo = 1 GROUP BY p.id, p.codigo, p.descripcion ORDER BY vendido DESC",
                    [$r1, $r2]
                );
                foreach ($filas as &$x) {
                    $x['diario'] = round((float) $x['vendido'] / $dias, 2);
                    $x['rotacion'] = (float) $x['stock'] > 0 ? round((float) $x['vendido'] / (float) $x['stock'], 2) : null;
                    $x['cobertura'] = $x['diario'] > 0 ? round((float) $x['stock'] / $x['diario']) : null;
                }
                unset($x);
                return self::resultado(['codigo' => ['Código', 'texto'], 'descripcion' => ['Producto', 'texto'], 'vendido' => ['Vendido', 'cant'], 'diario' => ['Venta/día', 'num'], 'stock' => ['Stock', 'cant'], 'rotacion' => ['Rotación', 'num'], 'cobertura' => ['Días de cobertura', 'entero']], $filas, ['codigo', 'descripcion', 'diario', 'rotacion', 'cobertura'], $sub);

            case 'cc_clientes':
            case 'cc_proveedores':
                $esCli = $clave === 'cc_clientes';
                $tabla = $esCli ? 'cc_clientes' : 'cc_proveedores';
                $col = $esCli ? 'cliente_id' : 'proveedor_id';
                $ent = $esCli ? 'clientes' : 'proveedores';
                $nombre = $esCli ? 'e.nombre_comercio' : 'e.razon_social';
                $filas = DB::filas(
                    "SELECT $nombre AS nombre, SUM(cc.importe) AS saldo,
                        SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) <= 0 THEN cc.pendiente ELSE 0 END) AS a_vencer,
                        SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) BETWEEN 1 AND 30 THEN cc.pendiente ELSE 0 END) AS d30,
                        SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) BETWEEN 31 AND 60 THEN cc.pendiente ELSE 0 END) AS d60,
                        SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) BETWEEN 61 AND 90 THEN cc.pendiente ELSE 0 END) AS d90,
                        SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) > 90 THEN cc.pendiente ELSE 0 END) AS mas90
                     FROM $tabla cc JOIN $ent e ON e.id = cc.$col GROUP BY e.id, nombre HAVING ABS(saldo) > 0.009 ORDER BY saldo DESC"
                );
                return self::resultado(['nombre' => [$esCli ? 'Cliente' : 'Proveedor', 'texto'], 'saldo' => ['Saldo', 'money'], 'a_vencer' => ['A vencer', 'money'], 'd30' => ['1-30 días', 'money'], 'd60' => ['31-60', 'money'], 'd90' => ['61-90', 'money'], 'mas90' => ['+90', 'money']], $filas, ['nombre'], 'Al ' . fecha(hoy()));

            case 'iva_ventas':
                $filas = DB::filas(
                    "SELECT c.fecha, c.tipo, pv.numero AS pv, c.numero, c.cliente_nombre, c.cliente_documento, c.cliente_condicion_iva, c.estado,
                            $sg * c.neto_21 AS neto_21, $sg * c.neto_105 AS neto_105, $sg * c.neto_exento AS exento, $sg * c.iva_21 AS iva_21, $sg * c.iva_105 AS iva_105,
                            $sg * c.impuestos_internos AS internos, $sg * c.total AS total, c.cae
                     FROM comprobantes c JOIN puntos_venta pv ON pv.id = c.punto_venta_id
                     WHERE c.tipo IN ('FA','FB','FC','NCA','NCB','NCC','NDA','NDB','NDC') AND c.fecha BETWEEN ? AND ? ORDER BY c.fecha, c.tipo, c.numero",
                    [$r1, $r2]
                );
                foreach ($filas as &$x) {
                    $x['comprobante'] = $x['tipo'] . ' ' . sprintf('%04d-%08d', $x['pv'], $x['numero']);
                    $x['cond'] = $x['cliente_condicion_iva'];
                    if ($x['estado'] === 'anulado') {
                        foreach (['neto_21', 'neto_105', 'exento', 'iva_21', 'iva_105', 'internos', 'total'] as $k) {
                            $x[$k] = 0;
                        }
                        $x['cliente_nombre'] = 'ANULADO - ' . $x['cliente_nombre'];
                    }
                }
                unset($x);
                return self::resultado(['fecha' => ['Fecha', 'fecha'], 'comprobante' => ['Comprobante', 'texto'], 'cliente_nombre' => ['Cliente', 'texto'], 'cliente_documento' => ['CUIT/DNI', 'texto'], 'cond' => ['IVA', 'texto'],
                    'neto_21' => ['Neto 21%', 'money'], 'neto_105' => ['Neto 10,5%', 'money'], 'exento' => ['Exento/No grav.', 'money'], 'iva_21' => ['IVA 21%', 'money'], 'iva_105' => ['IVA 10,5%', 'money'], 'internos' => ['Internos', 'money'], 'total' => ['Total', 'money']],
                    $filas, ['fecha', 'comprobante', 'cliente_nombre', 'cliente_documento', 'cond'], $sub . ' · Emisor CUIT ' . Ajustes::get('empresa_cuit'));

            case 'iva_compras':
                $filas = DB::filas(
                    "SELECT f.fecha, f.tipo, f.punto_venta, f.numero, pr.razon_social, pr.cuit, CASE WHEN f.tipo LIKE 'NC%' THEN -1 ELSE 1 END AS s,
                            f.neto_gravado, f.no_gravado, f.iva_21, f.iva_105, f.iva_27, f.percepcion_iva, f.percepcion_iibb, f.impuestos_internos, f.otros, f.total
                     FROM facturas_proveedor f JOIN proveedores pr ON pr.id = f.proveedor_id WHERE f.estado = 'registrada' AND f.fecha BETWEEN ? AND ? ORDER BY f.fecha",
                    [$desde, $hasta]
                );
                foreach ($filas as &$x) {
                    $x['comprobante'] = $x['tipo'] . ' ' . sprintf('%05d-%08d', $x['punto_venta'], $x['numero']);
                    foreach (['neto_gravado', 'no_gravado', 'iva_21', 'iva_105', 'iva_27', 'percepcion_iva', 'percepcion_iibb', 'impuestos_internos', 'otros', 'total'] as $k) {
                        $x[$k] = (int) $x['s'] * (float) $x[$k];
                    }
                    $x['iva'] = $x['iva_21'] + $x['iva_105'] + $x['iva_27'];
                }
                unset($x);
                return self::resultado(['fecha' => ['Fecha', 'fecha'], 'comprobante' => ['Comprobante', 'texto'], 'razon_social' => ['Proveedor', 'texto'], 'cuit' => ['CUIT', 'texto'],
                    'neto_gravado' => ['Neto gravado', 'money'], 'no_gravado' => ['No gravado', 'money'], 'iva' => ['IVA', 'money'], 'percepcion_iva' => ['Perc. IVA', 'money'], 'percepcion_iibb' => ['Perc. IIBB', 'money'],
                    'impuestos_internos' => ['Internos', 'money'], 'otros' => ['Otros', 'money'], 'total' => ['Total', 'money']], $filas, ['fecha', 'comprobante', 'razon_social', 'cuit'], $sub);

            case 'rendiciones':
                $filas = DB::filas(
                    "SELECT h.numero, h.fecha, ch.nombre AS chofer, v.patente, (SELECT COUNT(*) FROM hoja_ruta_paradas WHERE hoja_ruta_id = h.id) AS paradas,
                            (SELECT COUNT(*) FROM hoja_ruta_paradas WHERE hoja_ruta_id = h.id AND estado IN ('entregado','parcial')) AS entregadas,
                            r.efectivo_esperado, r.efectivo_rendido, r.diferencia_efectivo, r.otros_medios_esperado, r.otros_medios_confirmado, r.diferencia_mercaderia
                     FROM rendiciones r JOIN hojas_ruta h ON h.id = r.hoja_ruta_id JOIN choferes ch ON ch.id = h.chofer_id JOIN vehiculos v ON v.id = h.vehiculo_id
                     WHERE r.fecha BETWEEN ? AND ? ORDER BY h.fecha",
                    [$r1, $r2]
                );
                return self::resultado(['numero' => ['HR', 'entero'], 'fecha' => ['Fecha', 'fecha'], 'chofer' => ['Chofer', 'texto'], 'paradas' => ['Paradas', 'entero'], 'entregadas' => ['Entregadas', 'entero'],
                    'efectivo_esperado' => ['Efectivo esperado', 'money'], 'efectivo_rendido' => ['Rendido', 'money'], 'diferencia_efectivo' => ['Dif. efectivo', 'money'],
                    'otros_medios_esperado' => ['Otros medios', 'money'], 'diferencia_mercaderia' => ['Dif. mercadería', 'money']], $filas, ['numero', 'fecha', 'chofer'], $sub);

            case 'rechazos':
                $filas = DB::filas(
                    "SELECT h.fecha, h.numero AS hr, c.nombre_comercio, z.nombre AS zona, hp.estado, COALESCE(hp.motivo, '—') AS motivo, pe.total, hp.reprogramado
                     FROM hoja_ruta_paradas hp JOIN hojas_ruta h ON h.id = hp.hoja_ruta_id JOIN clientes c ON c.id = hp.cliente_id LEFT JOIN zonas z ON z.id = c.zona_id JOIN pedidos pe ON pe.id = hp.pedido_id
                     WHERE hp.estado IN ('rechazado','cerrado','parcial') AND h.fecha BETWEEN ? AND ? ORDER BY h.fecha",
                    [$desde, $hasta]
                );
                foreach ($filas as &$x) {
                    $x['estado'] = ['rechazado' => 'Rechazado', 'cerrado' => 'Cliente cerrado', 'parcial' => 'Entrega parcial'][$x['estado']];
                    $x['reprogramado'] = (int) $x['reprogramado'] ? 'Sí' : 'No';
                }
                unset($x);
                $res = self::resultado(['fecha' => ['Fecha', 'fecha'], 'hr' => ['HR', 'entero'], 'nombre_comercio' => ['Cliente', 'texto'], 'zona' => ['Zona', 'texto'], 'estado' => ['Resultado', 'texto'], 'motivo' => ['Motivo', 'texto'], 'total' => ['Pedido', 'money'], 'reprogramado' => ['Reprogramado', 'texto']], $filas, ['fecha', 'hr', 'nombre_comercio', 'zona', 'estado', 'motivo', 'reprogramado'], $sub);
                $res['resumen'] = DB::filas("SELECT COALESCE(hp.motivo, hp.estado) AS motivo, COUNT(*) AS cantidad FROM hoja_ruta_paradas hp JOIN hojas_ruta h ON h.id = hp.hoja_ruta_id WHERE hp.estado IN ('rechazado','cerrado','parcial') AND h.fecha BETWEEN ? AND ? GROUP BY 1 ORDER BY 2 DESC", [$desde, $hasta]);
                $res['grafico'] = ['etiquetas' => array_column($res['resumen'], 'motivo'), 'valores' => array_map('floatval', array_column($res['resumen'], 'cantidad')), 'titulo' => 'Cantidad por motivo'];
                return $res;

            case 'flujo_caja':
                $filas = DB::filas(
                    "SELECT dia, SUM(caja_in) AS caja_ingresos, SUM(caja_out) AS caja_egresos, SUM(banco_in) AS banco_ingresos, SUM(banco_out) AS banco_egresos FROM (
                        SELECT DATE(m.fecha) AS dia, IF(m.tipo = 'ingreso', m.importe, 0) AS caja_in, IF(m.tipo = 'egreso', m.importe, 0) AS caja_out, 0 AS banco_in, 0 AS banco_out FROM caja_movimientos m WHERE m.fecha BETWEEN ? AND ?
                        UNION ALL
                        SELECT DATE(b.fecha), 0, 0, IF(b.tipo = 'ingreso', b.importe, 0), IF(b.tipo = 'egreso', b.importe, 0) FROM banco_movimientos b WHERE b.fecha BETWEEN ? AND ?
                     ) t GROUP BY dia ORDER BY dia",
                    [$r1, $r2, $r1, $r2]
                );
                foreach ($filas as &$x) {
                    $x['neto'] = round((float) $x['caja_ingresos'] - (float) $x['caja_egresos'] + (float) $x['banco_ingresos'] - (float) $x['banco_egresos'], 2);
                    $x['dia_txt'] = fecha($x['dia']);
                }
                unset($x);
                $res = self::resultado(['dia_txt' => ['Día', 'texto'], 'caja_ingresos' => ['Caja ingresos', 'money'], 'caja_egresos' => ['Caja egresos', 'money'], 'banco_ingresos' => ['Bancos ingresos', 'money'], 'banco_egresos' => ['Bancos egresos', 'money'], 'neto' => ['Flujo neto', 'money']], $filas, ['dia_txt'], $sub . ' (incluye movimientos internos entre caja y banco)', ['dia_txt', 'neto']);
                $saldoCajas = 0.0;
                foreach (DB::columna("SELECT id FROM caja_sesiones WHERE estado = 'abierta'") as $s) {
                    $saldoCajas += CajaService::saldoTeorico((int) $s);
                }
                $saldoBancos = 0.0;
                foreach (DB::columna('SELECT id FROM cuentas_bancarias WHERE activo = 1') as $c) {
                    $saldoBancos += CajaService::saldoBanco((int) $c);
                }
                $res['nota'] = 'Saldo actual en cajas abiertas: ' . money($saldoCajas) . ' · en bancos y billeteras: ' . money($saldoBancos);
                return $res;

            case 'resultado':
                $meses = max(1, min(24, (int) ($f['meses'] ?? 6)));
                $filas = [];
                for ($i = $meses - 1; $i >= 0; $i--) {
                    $ini = date('Y-m-01', strtotime("first day of -$i month"));
                    $fin = date('Y-m-t', strtotime($ini));
                    $v = DB::fila("SELECT COALESCE(SUM($sg * (c.total - c.iva_21 - c.iva_105 - c.impuestos_internos)), 0) AS ventas, COALESCE(SUM($sg * c.costo_total), 0) AS cmv
                                   FROM comprobantes c WHERE c.estado = 'emitido' AND c.tipo IN $tv AND c.fecha BETWEEN ? AND ?", [$ini . ' 00:00:00', $fin . ' 23:59:59']);
                    // Las NC sin reingreso a stock no recuperan costo
                    $cmvNoRecuperado = (float) DB::valor("SELECT COALESCE(SUM(ci.costo_unitario * ci.cantidad_base), 0) FROM comprobante_items ci JOIN comprobantes c ON c.id = ci.comprobante_id WHERE c.estado = 'emitido' AND c.tipo LIKE 'NC%' AND ci.reingresa_stock = 0 AND c.fecha BETWEEN ? AND ?", [$ini . ' 00:00:00', $fin . ' 23:59:59']);
                    $gastos = (float) DB::valor("SELECT COALESCE(SUM(importe), 0) FROM gastos WHERE estado = 'registrado' AND fecha BETWEEN ? AND ?", [$ini, $fin]);
                    $ventas = (float) $v['ventas'];
                    $cmv = (float) $v['cmv'] + $cmvNoRecuperado;
                    $filas[] = ['mes' => date('m/Y', strtotime($ini)), 'ventas' => round($ventas, 2), 'cmv' => round($cmv, 2), 'bruto' => round($ventas - $cmv, 2), 'gastos' => round($gastos, 2), 'resultado' => round($ventas - $cmv - $gastos, 2),
                        'margen' => $ventas > 0 ? round(($ventas - $cmv - $gastos) * 100 / $ventas, 1) : 0];
                }
                $res = self::resultado(['mes' => ['Mes', 'texto'], 'ventas' => ['Ventas netas', 'money'], 'cmv' => ['Costo mercadería', 'money'], 'bruto' => ['Resultado bruto', 'money'], 'gastos' => ['Gastos', 'money'], 'resultado' => ['Resultado', 'money'], 'margen' => ['Margen neto %', 'porc']], $filas, ['mes', 'margen'], 'Últimos ' . $meses . ' meses (no incluye impuestos sobre ganancias ni amortizaciones)', ['mes', 'resultado']);
                $res['totales']['margen'] = (float) $res['totales']['ventas'] > 0 ? round((float) $res['totales']['resultado'] * 100 / (float) $res['totales']['ventas'], 1) : 0;
                return $res;
        }
        throw new ErrorNegocio('Reporte inexistente.');
    }

    /** Arma la respuesta estándar sumando las columnas numéricas (salvo las excluidas). */
    private static function resultado(array $columnas, array $filas, array $sinTotal, string $subtitulo, ?array $grafico = null): array
    {
        $totales = [];
        $primera = array_key_first($columnas);
        foreach ($columnas as $k => [, $tipo]) {
            if (in_array($tipo, ['money', 'num', 'cant', 'entero'], true) && !in_array($k, $sinTotal, true)) {
                $totales[$k] = round(array_sum(array_map(fn ($f) => (float) ($f[$k] ?? 0), $filas)), 2);
            }
        }
        $totales[$primera] = 'TOTAL';
        $res = ['columnas' => $columnas, 'filas' => $filas, 'totales' => $filas ? $totales : null, 'subtitulo' => $subtitulo];
        if ($grafico && $filas) {
            [$e, $v] = $grafico;
            $top = array_slice($filas, 0, 31);
            $res['grafico'] = ['etiquetas' => array_map(fn ($f) => (string) $f[$e], $top), 'valores' => array_map(fn ($f) => round((float) $f[$v], 2), $top), 'titulo' => $columnas[$v][0]];
        }
        return $res;
    }
}
