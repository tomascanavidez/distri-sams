<?php
/**
 * Base de las pruebas: aserciones y datos de ejemplo (fixtures).
 */

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Services\CajaService;
use App\Services\PrecioService;

abstract class Prueba
{
    private static int $n = 0;

    protected function igual(mixed $esperado, mixed $real, string $msg = ''): void
    {
        $ok = is_float($esperado) || is_float($real) ? abs((float) $esperado - (float) $real) < 0.0051 : $esperado == $real;
        if (!$ok) {
            throw new \RuntimeException(($msg ? "$msg: " : '') . 'se esperaba ' . var_export($esperado, true) . ' y se obtuvo ' . var_export($real, true));
        }
    }

    protected function verdadero(bool $condicion, string $msg): void
    {
        if (!$condicion) {
            throw new \RuntimeException('Falló: ' . $msg);
        }
    }

    /** Verifica que el callback lance un ErrorNegocio (y opcionalmente que el mensaje contenga un texto). */
    protected function lanza(callable $fn, string $contiene = ''): void
    {
        try {
            $fn();
        } catch (ErrorNegocio $e) {
            if ($contiene && stripos($e->getMessage(), $contiene) === false) {
                throw new \RuntimeException("Se lanzó \"{$e->getMessage()}\" pero se esperaba que contuviera \"$contiene\"");
            }
            return;
        }
        throw new \RuntimeException('Se esperaba un error de negocio' . ($contiene ? " (\"$contiene\")" : ''));
    }

    /** Crea un producto con presentaciones [nombre => factor]. Devuelve el id. */
    protected function producto(float $costo = 100, float $iva = 21, array $pres = ['Caja' => 12], bool $lotes = false, float $internos = 0): int
    {
        self::$n++;
        $id = DB::insertar('productos', [
            'codigo' => 'T' . self::$n . '-' . substr(uniqid(), -5), 'descripcion' => 'Producto de prueba ' . self::$n, 'unidad_base' => 'Unidad',
            'costo' => $costo, 'costo_promedio' => $costo, 'iva_alicuota' => $iva, 'impuesto_interno' => $internos, 'maneja_lotes' => $lotes ? 1 : 0,
            'activo' => 1, 'creado_en' => ahora(),
        ]);
        DB::insertar('producto_presentaciones', ['producto_id' => $id, 'nombre' => 'Unidad', 'factor' => 1, 'es_venta' => 1, 'es_compra' => 1, 'predeterminada_venta' => 1, 'activo' => 1]);
        foreach ($pres as $nombre => $factor) {
            DB::insertar('producto_presentaciones', ['producto_id' => $id, 'nombre' => $nombre, 'factor' => $factor, 'es_venta' => 1, 'es_compra' => 1, 'activo' => 1]);
        }
        PrecioService::recalcularProducto($id);
        return $id;
    }

    protected function presentacion(int $productoId, string $nombre): int
    {
        return (int) DB::valor('SELECT id FROM producto_presentaciones WHERE producto_id = ? AND nombre = ?', [$productoId, $nombre]);
    }

    protected function cliente(string $condIva = 'MONO', string $pago = 'cta_cte', float $limite = 0, int $dias = 7): int
    {
        self::$n++;
        return DB::insertar('clientes', [
            'codigo' => 'CT' . self::$n . substr(uniqid(), -4), 'nombre_comercio' => 'Cliente de prueba ' . self::$n, 'condicion_iva' => $condIva,
            'tipo_documento' => $condIva === 'RI' ? 'CUIT' : 'DNI', 'documento' => $condIva === 'RI' ? '20-12345678-6' : '12345678',
            'lista_precio_id' => 1, 'condicion_pago' => $pago, 'dias_credito' => $dias, 'limite_credito' => $limite, 'estado' => 'activo', 'creado_en' => ahora(),
        ]);
    }

    protected function stock(int $productoId, int $deposito = 1): array
    {
        return DB::fila('SELECT cantidad, reservado FROM stock WHERE producto_id = ? AND deposito_id = ?', [$productoId, $deposito]) ?: ['cantidad' => 0, 'reservado' => 0];
    }

    protected function cajaAbierta(int $caja = 1): int
    {
        $s = CajaService::sesionAbierta($caja);
        return $s ? (int) $s['id'] : CajaService::abrir($caja, 0);
    }

    protected function usuario(string $usuario): void
    {
        $_SESSION['usuario_id'] = (int) DB::valor('SELECT id FROM usuarios WHERE usuario = ?', [$usuario]);
        Auth::refrescar();
    }
}
