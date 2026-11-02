<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Cálculo de precios de venta a partir del costo, margen, IVA, internos y redondeo,
 * y actualización (individual y masiva) con historial.
 *
 * Convención: los precios de lista son FINALES (IVA incluido) por UNIDAD BASE.
 * El precio de una presentación = precio unitario × factor.
 */
final class PrecioService
{
    /**
     * Precio final = (costo × (1 + margen%)) × (1 + IVA%) + impuestos internos, redondeado.
     */
    public static function calcularPrecio(
        float $costo,
        float $margenPorc,
        float $ivaPorc,
        float $internos = 0.0,
        float $redondeo = 0.0,
        string $modo = 'arriba'
    ): float {
        $neto = $costo * (1 + $margenPorc / 100);
        $final = $neto * (1 + $ivaPorc / 100) + $internos;
        return self::redondear($final, $redondeo, $modo);
    }

    /** Redondea al múltiplo indicado (0 = sólo a centavos). */
    public static function redondear(float $valor, float $multiplo, string $modo = 'arriba'): float
    {
        $valor = round($valor, 4);
        if ($multiplo <= 0) {
            return round($valor, 2);
        }
        $cociente = $valor / $multiplo;
        $entero = match ($modo) {
            'arriba' => ceil(round($cociente, 6)),
            'abajo' => floor(round($cociente, 6)),
            default => round($cociente),
        };
        return round($entero * $multiplo, 2);
    }

    /** Margen % implícito de un precio final dado. */
    public static function margenDesdePrecio(float $precioFinal, float $costo, float $ivaPorc, float $internos = 0.0): float
    {
        if ($costo <= 0) {
            return 0.0;
        }
        $neto = ($precioFinal - $internos) / (1 + $ivaPorc / 100);
        return round(($neto / $costo - 1) * 100, 2);
    }

    /** Costo que se usa para precios y valorización según configuración (último o promedio). */
    public static function costoBase(array $producto): float
    {
        $metodo = Ajustes::get('costo_metodo', 'ultimo');
        $promedio = (float) ($producto['costo_promedio'] ?? 0);
        return $metodo === 'promedio' && $promedio > 0 ? $promedio : (float) $producto['costo'];
    }

    /**
     * Recalcula los precios en modo "margen" del producto en todas las listas activas
     * (crea los renglones faltantes). Registra los cambios en el historial.
     */
    public static function recalcularProducto(int $productoId, string $motivo = 'Recálculo por costo'): void
    {
        $p = DB::fila('SELECT * FROM productos WHERE id = ?', [$productoId]);
        if (!$p) {
            return;
        }
        $costo = self::costoBase($p);
        $modo = (string) Ajustes::get('redondeo_modo', 'arriba');
        $listas = DB::filas('SELECT * FROM listas_precios WHERE activo = 1');
        foreach ($listas as $l) {
            $fila = DB::fila('SELECT * FROM producto_precios WHERE producto_id = ? AND lista_id = ?', [$productoId, $l['id']]);
            if (!$fila) {
                $precio = self::calcularPrecio($costo, (float) $l['margen_porc'], (float) $p['iva_alicuota'], (float) $p['impuesto_interno'], (float) $l['redondeo'], $modo);
                DB::insertar('producto_precios', [
                    'producto_id' => $productoId, 'lista_id' => $l['id'], 'modo' => 'margen',
                    'margen_porc' => null, 'precio' => $precio, 'actualizado_en' => ahora(),
                ]);
                continue;
            }
            if ($fila['modo'] !== 'margen') {
                continue;
            }
            $margen = $fila['margen_porc'] !== null ? (float) $fila['margen_porc'] : (float) $l['margen_porc'];
            $precio = self::calcularPrecio($costo, $margen, (float) $p['iva_alicuota'], (float) $p['impuesto_interno'], (float) $l['redondeo'], $modo);
            self::guardarPrecio($productoId, (int) $l['id'], $precio, (float) $fila['precio'], $motivo);
        }
    }

    /** Guarda un precio de lista si cambió, con historial. */
    public static function guardarPrecio(int $productoId, int $listaId, float $nuevo, ?float $anterior, string $motivo, ?string $modo = null, ?float $margen = null, bool $tocarMargen = false): void
    {
        $datos = ['precio' => $nuevo, 'actualizado_en' => ahora()];
        if ($modo !== null) {
            $datos['modo'] = $modo;
        }
        if ($tocarMargen) {
            $datos['margen_porc'] = $margen;
        }
        $existe = DB::valor('SELECT COUNT(*) FROM producto_precios WHERE producto_id = ? AND lista_id = ?', [$productoId, $listaId]);
        if ($existe) {
            DB::actualizar('producto_precios', $datos, 'producto_id = :p AND lista_id = :l', ['p' => $productoId, 'l' => $listaId]);
        } else {
            DB::insertar('producto_precios', $datos + ['producto_id' => $productoId, 'lista_id' => $listaId, 'modo' => $modo ?? 'margen']);
        }
        if ($anterior === null || abs($anterior - $nuevo) >= 0.005) {
            DB::insertar('precios_historial', [
                'fecha' => ahora(), 'producto_id' => $productoId, 'lista_id' => $listaId,
                'valor_anterior' => $anterior ?? 0, 'valor_nuevo' => $nuevo,
                'motivo' => mb_substr($motivo, 0, 150), 'usuario_id' => Auth::id(),
            ]);
        }
    }

    /** Cambia el costo (último) de un producto, con historial y recálculo de precios. */
    public static function actualizarCosto(int $productoId, float $nuevoCosto, string $motivo, bool $recalcular = true): void
    {
        $p = DB::fila('SELECT costo FROM productos WHERE id = ? FOR UPDATE', [$productoId]);
        if (!$p) {
            throw new ErrorNegocio('Producto inexistente.');
        }
        $anterior = (float) $p['costo'];
        $nuevoCosto = round($nuevoCosto, 4);
        if (abs($anterior - $nuevoCosto) < 0.00005) {
            return;
        }
        DB::actualizar('productos', ['costo' => $nuevoCosto, 'actualizado_en' => ahora()], 'id = :id', ['id' => $productoId]);
        DB::insertar('precios_historial', [
            'fecha' => ahora(), 'producto_id' => $productoId, 'lista_id' => null,
            'valor_anterior' => $anterior, 'valor_nuevo' => $nuevoCosto,
            'motivo' => mb_substr($motivo, 0, 150), 'usuario_id' => Auth::id(),
        ]);
        if ($recalcular) {
            self::recalcularProducto($productoId, $motivo);
        }
    }

    /**
     * Actualización masiva por % filtrando por rubro, marca o proveedor.
     * $tipo = 'costo' (recalcula listas en modo margen) | 'precio' (sube precios de una lista o de todas).
     * Devuelve la cantidad de productos afectados.
     */
    public static function actualizacionMasiva(array $filtros, string $tipo, float $porcentaje, ?int $listaId, string $motivo): int
    {
        if (abs($porcentaje) < 0.001) {
            throw new ErrorNegocio('Indicá un porcentaje distinto de cero.');
        }
        if ($porcentaje <= -100) {
            throw new ErrorNegocio('El porcentaje no puede ser -100 % o menor.');
        }
        $where = ['activo = 1'];
        $params = [];
        foreach (['rubro_id', 'marca_id', 'proveedor_id'] as $campo) {
            if (!empty($filtros[$campo])) {
                $where[] = "$campo = ?";
                $params[] = (int) $filtros[$campo];
            }
        }
        $ids = DB::columna('SELECT id FROM productos WHERE ' . implode(' AND ', $where), $params);
        if (!$ids) {
            throw new ErrorNegocio('No hay productos que coincidan con el filtro.');
        }
        $factor = 1 + $porcentaje / 100;
        $modoRed = (string) Ajustes::get('redondeo_modo', 'arriba');

        DB::transaccion(function () use ($ids, $tipo, $factor, $listaId, $motivo, $modoRed) {
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($tipo === 'costo') {
                    $c = (float) DB::valor('SELECT costo FROM productos WHERE id = ?', [$id]);
                    self::actualizarCosto($id, $c * $factor, $motivo);
                    continue;
                }
                $p = DB::fila('SELECT * FROM productos WHERE id = ?', [$id]);
                $sql = 'SELECT pp.*, l.margen_porc AS margen_lista, l.redondeo FROM producto_precios pp JOIN listas_precios l ON l.id = pp.lista_id WHERE pp.producto_id = ? AND l.activo = 1';
                $prm = [$id];
                if ($listaId) {
                    $sql .= ' AND pp.lista_id = ?';
                    $prm[] = $listaId;
                }
                foreach (DB::filas($sql, $prm) as $fila) {
                    if ($fila['modo'] === 'fijo') {
                        $nuevo = self::redondear((float) $fila['precio'] * $factor, (float) $fila['redondeo'], $modoRed);
                        self::guardarPrecio($id, (int) $fila['lista_id'], $nuevo, (float) $fila['precio'], $motivo);
                    } else {
                        // En modo margen se ajusta el margen para que el precio suba el % indicado
                        $margen = $fila['margen_porc'] !== null ? (float) $fila['margen_porc'] : (float) $fila['margen_lista'];
                        $nuevoMargen = round(((1 + $margen / 100) * $factor - 1) * 100, 2);
                        $nuevo = self::calcularPrecio(self::costoBase($p), $nuevoMargen, (float) $p['iva_alicuota'], (float) $p['impuesto_interno'], (float) $fila['redondeo'], $modoRed);
                        self::guardarPrecio($id, (int) $fila['lista_id'], $nuevo, (float) $fila['precio'], $motivo, null, $nuevoMargen, true);
                    }
                }
            }
        });
        return count($ids);
    }

    /**
     * Cambia el margen y/o redondeo por defecto de una lista y recalcula los precios de todos los productos
     * que usan el margen de la lista. Si $pisarPropios es true, además se quitan los márgenes propios de
     * los productos y los precios fijos de esa lista (todos pasan a usar el margen de la lista).
     * Devuelve la cantidad de productos recalculados.
     */
    public static function configurarLista(int $listaId, float $margen, float $redondeo, bool $pisarPropios, string $motivo): int
    {
        $lista = DB::fila('SELECT * FROM listas_precios WHERE id = ?', [$listaId]);
        if (!$lista) {
            throw new ErrorNegocio('Lista de precios inexistente.');
        }
        if ($margen < 0 || $margen > 1000) {
            throw new ErrorNegocio('El margen de la lista "' . $lista['nombre'] . '" debe estar entre 0 y 1000 %.');
        }
        $cambio = abs((float) $lista['margen_porc'] - $margen) > 0.001 || abs((float) $lista['redondeo'] - $redondeo) > 0.001;
        if (!$cambio && !$pisarPropios) {
            return 0;
        }
        return DB::transaccion(function () use ($listaId, $lista, $margen, $redondeo, $pisarPropios, $motivo) {
            DB::actualizar('listas_precios', ['margen_porc' => $margen, 'redondeo' => $redondeo], 'id = :id', ['id' => $listaId]);
            if ($pisarPropios) {
                DB::query("UPDATE producto_precios SET modo = 'margen', margen_porc = NULL WHERE lista_id = ?", [$listaId]);
            }
            Auditoria::registrar('productos', 'lista_precios', 'lista_precios', $listaId,
                ['margen_porc' => $lista['margen_porc'], 'redondeo' => $lista['redondeo']],
                ['margen_porc' => $margen, 'redondeo' => $redondeo, 'pisar_propios' => $pisarPropios ? 'sí' : 'no'], $motivo);
            return self::recalcularTodos($motivo);
        });
    }

    /** Recalcula los precios por margen de todos los productos activos. Devuelve cuántos se procesaron. */
    public static function recalcularTodos(string $motivo): int
    {
        $ids = DB::columna('SELECT id FROM productos WHERE activo = 1');
        foreach ($ids as $id) {
            self::recalcularProducto((int) $id, $motivo);
        }
        return count($ids);
    }

    /**
     * Fija un margen propio (o vuelve al de la lista con $margen = null) para un grupo de productos en una lista.
     * Los productos con precio fijo en esa lista pasan a calcularse por margen.
     */
    public static function fijarMargen(array $filtros, int $listaId, ?float $margen, string $motivo): int
    {
        if (!DB::valor('SELECT id FROM listas_precios WHERE id = ?', [$listaId])) {
            throw new ErrorNegocio('Elegí la lista de precios.');
        }
        if ($margen !== null && ($margen < 0 || $margen > 1000)) {
            throw new ErrorNegocio('El margen debe estar entre 0 y 1000 %.');
        }
        $where = ['activo = 1'];
        $params = [];
        foreach (['rubro_id', 'marca_id', 'proveedor_id'] as $campo) {
            if (!empty($filtros[$campo])) {
                $where[] = "$campo = ?";
                $params[] = (int) $filtros[$campo];
            }
        }
        $ids = DB::columna('SELECT id FROM productos WHERE ' . implode(' AND ', $where), $params);
        if (!$ids) {
            throw new ErrorNegocio('No hay productos que coincidan con el filtro.');
        }
        DB::transaccion(function () use ($ids, $listaId, $margen, $motivo) {
            foreach ($ids as $id) {
                self::recalcularProducto((int) $id, $motivo); // asegura que exista el renglón de la lista
                DB::query("UPDATE producto_precios SET modo = 'margen', margen_porc = ? WHERE producto_id = ? AND lista_id = ?", [$margen, (int) $id, $listaId]);
                self::recalcularProducto((int) $id, $motivo);
            }
        });
        return count($ids);
    }

    /** Precio final por unidad base de un producto en una lista (0 si no tiene). */
    public static function precioLista(int $productoId, ?int $listaId): float
    {
        $listaId = $listaId ?: (int) DB::valor('SELECT id FROM listas_precios WHERE activo = 1 ORDER BY orden, id LIMIT 1');
        return (float) DB::valor('SELECT precio FROM producto_precios WHERE producto_id = ? AND lista_id = ?', [$productoId, $listaId]);
    }

    /**
     * Bonificación automática por promoción vigente (ej. 12+1): unidades base sin cargo.
     */
    public static function bonificacion(int $productoId, float $cantidadBase, ?string $fecha = null): float
    {
        $fecha ??= hoy();
        $promo = DB::fila(
            'SELECT cantidad_compra, cantidad_bonificada FROM promociones
             WHERE producto_id = ? AND activo = 1 AND (desde IS NULL OR desde <= ?) AND (hasta IS NULL OR hasta >= ?)
             ORDER BY cantidad_compra DESC LIMIT 1',
            [$productoId, $fecha, $fecha]
        );
        if (!$promo || (float) $promo['cantidad_compra'] <= 0) {
            return 0.0;
        }
        return floor($cantidadBase / (float) $promo['cantidad_compra']) * (float) $promo['cantidad_bonificada'];
    }
}
