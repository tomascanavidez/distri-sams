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
use App\Core\Response;
use App\Core\Validador;
use App\Services\PrecioService;
use App\Services\RepartoService;
use App\Support\Catalogos;

/**
 * ABM genérico de catálogos simples (rubros, marcas, listas, depósitos, zonas, vehículos, etc.).
 */
final class CatalogoController extends Controller
{
    private function def(string $entidad, string $accion): array
    {
        $def = Catalogos::definicion($entidad);
        if (!$def) {
            Response::error(404, 'Catálogo inexistente.');
            exit;
        }
        $permiso = $accion === 'ver' ? $def['modulo'] . '.ver' : ($def['permiso_editar'] ?? $def['modulo'] . '.editar|' . $def['modulo'] . '.crear');
        if (!Auth::puede($permiso)) {
            Response::error(403, 'No tenés permiso para esta sección.');
            exit;
        }
        $def['entidad'] = $entidad;
        return $def;
    }

    public function index(string $entidad): void
    {
        $def = $this->def($entidad, 'ver');
        $base = $def['consulta'] ?? 'SELECT * FROM ' . $def['tabla'];
        $alias = isset($def['consulta']) ? $this->alias($def['consulta']) : '';
        $q = trim((string) Request::get('q', ''));
        $where = [];
        $params = [];
        if ($q !== '') {
            $primero = array_key_first($def['columnas']);
            $col = $primero === 'producto' ? 'p.descripcion' : ($alias ? "$alias.$primero" : $primero);
            $where[] = "$col LIKE ?";
            $params[] = "%$q%";
        }
        if (Request::get('inactivos') !== '1') {
            $where[] = ($alias ? "$alias." : '') . 'activo = 1';
        }
        $sql = $base . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $ordenes = ['defecto' => $def['orden']];
        $pag = new Paginador($sql, $params, $ordenes, 'defecto', 'asc', 50);
        $this->vista('catalogo/index', ['def' => $def, 'pag' => $pag, 'titulo' => $def['titulo']]);
    }

    private function alias(string $consulta): string
    {
        return preg_match('/FROM\s+\w+\s+(\w+)/i', $consulta, $m) ? $m[1] : '';
    }

    public function nuevo(string $entidad): void
    {
        $def = $this->def($entidad, 'editar');
        $this->vista('catalogo/form', ['def' => $def, 'datos' => ['activo' => 1], 'errores' => [], 'titulo' => 'Nuevo ' . $def['singular']]);
    }

    public function editar(string $entidad, string $id): void
    {
        $def = $this->def($entidad, 'editar');
        $datos = $this->buscarOFallar($def['tabla'], (int) $id);
        if ($entidad === 'promociones') {
            $datos['producto_texto'] = DB::valor('SELECT descripcion FROM productos WHERE id = ?', [$datos['producto_id']]);
        }
        $this->vista('catalogo/form', ['def' => $def, 'datos' => $datos, 'errores' => [], 'titulo' => 'Editar ' . $def['singular']]);
    }

    public function crear(string $entidad): void
    {
        $this->guardar($entidad, null);
    }

    public function actualizar(string $entidad, string $id): void
    {
        $this->guardar($entidad, (int) $id);
    }

    private function guardar(string $entidad, ?int $id): void
    {
        $def = $this->def($entidad, 'editar');
        $antes = $id ? $this->buscarOFallar($def['tabla'], $id) : null;
        $entrada = [];
        $reglas = [];
        foreach ($def['campos'] as $campo => [$etq, $tipo, $regla]) {
            $valor = Request::input($campo);
            if ($tipo === 'dias') {
                $valor = is_array($valor) ? implode(',', array_map('intval', $valor)) : '';
            }
            $entrada[$campo] = is_string($valor) ? trim($valor) : $valor;
            $reglas[$campo] = $id ? str_replace(['unico:' . $def['tabla'] . ',' . $campo], ['unico:' . $def['tabla'] . ',' . $campo . ',' . $id], $regla) : $regla;
        }
        $v = Validador::make($entrada, $reglas);
        if ($v->falla()) {
            $datos = array_merge($antes ?? [], $entrada);
            if ($entidad === 'promociones' && !empty($entrada['producto_id'])) {
                $datos['producto_texto'] = DB::valor('SELECT descripcion FROM productos WHERE id = ?', [$entrada['producto_id']]);
            }
            $this->vista('catalogo/form', ['def' => $def, 'datos' => $datos, 'errores' => $v->errores(), 'titulo' => ($id ? 'Editar ' : 'Nuevo ') . $def['singular']]);
            return;
        }
        $fila = [];
        foreach ($def['campos'] as $campo => [, $tipo]) {
            $val = $entrada[$campo];
            $fila[$campo] = match ($tipo) {
                'decimal' => $val === '' || $val === null ? 0 : parse_decimal($val),
                'entero' => $val === '' || $val === null ? null : (int) $val,
                'fecha' => parse_fecha((string) $val),
                'select', 'producto' => $val === '' || $val === null ? null : $val,
                'check' => (int) !empty($val),
                default => $val === '' ? null : $val,
            };
        }
        if ($entidad === 'vehiculos') {
            $fila['patente'] = strtoupper(str_replace(' ', '', (string) $fila['patente']));
        }
        if (!empty($def['fijos']) && !$id) {
            $fila += $def['fijos'];
        }
        if ($entidad === 'choferes' && $fila['usuario_id'] && DB::valor('SELECT id FROM choferes WHERE usuario_id = ? AND id <> ?', [$fila['usuario_id'], $id ?? 0])) {
            throw new ErrorNegocio('Ese usuario ya está asociado a otro chofer.');
        }
        if ($entidad === 'puntos_venta' && DB::valor('SELECT id FROM puntos_venta WHERE numero = ? AND id <> ?', [$fila['numero'], $id ?? 0])) {
            throw new ErrorNegocio('Ya existe un punto de venta con ese número.');
        }
        DB::transaccion(function () use ($def, $id, $fila, $antes, $entidad) {
            if ($id) {
                DB::actualizar($def['tabla'], $fila, 'id = :id', ['id' => $id]);
                Auditoria::registrar($def['modulo'], 'editar', $entidad, $id, $antes, $fila, 'Edición de ' . $def['singular']);
            } else {
                $id = DB::insertar($def['tabla'], $fila + ['activo' => 1]);
                Auditoria::registrar($def['modulo'], 'crear', $entidad, $id, null, $fila, 'Alta de ' . $def['singular']);
            }
            if (($def['despues'] ?? '') === 'depositoVehiculo') {
                RepartoService::depositoVehiculo($id);
                DB::query("UPDATE depositos SET nombre = CONCAT('Camión ', ?) WHERE vehiculo_id = ?", [$fila['patente'], $id]);
            }
            if (($def['despues'] ?? '') === 'recalcularLista' && $antes && ((float) $antes['margen_porc'] !== (float) $fila['margen_porc'] || (float) $antes['redondeo'] !== (float) $fila['redondeo'])) {
                foreach (DB::columna('SELECT id FROM productos WHERE activo = 1') as $pid) {
                    PrecioService::recalcularProducto((int) $pid, 'Cambio de margen/redondeo de la lista ' . $fila['nombre']);
                }
            }
            if (($def['despues'] ?? '') === 'recalcularLista' && !$antes) {
                foreach (DB::columna('SELECT id FROM productos') as $pid) {
                    PrecioService::recalcularProducto((int) $pid, 'Nueva lista ' . $fila['nombre']);
                }
            }
        });
        $this->redirigir('/catalogo/' . $entidad, ucfirst($def['singular']) . ' guardado/a correctamente.');
    }

    public function alternarEstado(string $entidad, string $id): void
    {
        $def = $this->def($entidad, 'editar');
        $fila = $this->buscarOFallar($def['tabla'], (int) $id);
        $nuevo = (int) $fila['activo'] === 1 ? 0 : 1;
        if ($entidad === 'depositos' && (int) $id === (int) \App\Core\Ajustes::get('deposito_central', 1) && $nuevo === 0) {
            throw new ErrorNegocio('No se puede desactivar el depósito central.');
        }
        DB::actualizar($def['tabla'], ['activo' => $nuevo], 'id = :id', ['id' => (int) $id]);
        Auditoria::registrar($def['modulo'], $nuevo ? 'activar' : 'desactivar', $entidad, (int) $id, ['activo' => $fila['activo']], ['activo' => $nuevo]);
        $this->volver(ucfirst($def['singular']) . ($nuevo ? ' activado/a.' : ' desactivado/a.'));
    }
}
