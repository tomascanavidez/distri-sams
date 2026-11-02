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
use App\Services\ClienteService;
use App\Services\CuentaCorriente;
use App\Support\Consultas;
use App\Support\Exportador;

final class ClientesController extends Controller
{
    public const TIPOS_COMERCIO = ['Kiosco', 'Maxikiosco', 'Almacén', 'Despensa', 'Autoservicio', 'Supermercado', 'Bar / buffet', 'Otro'];

    private function filtros(): array
    {
        $w = [];
        $p = [];
        if ($q = trim((string) Request::get('q', ''))) {
            foreach (array_slice(preg_split('/\s+/', $q) ?: [], 0, 4) as $palabra) {
                $w[] = '(c.nombre_comercio LIKE ? OR c.titular LIKE ? OR c.codigo LIKE ? OR c.documento LIKE ? OR c.direccion LIKE ?)';
                array_push($p, "%$palabra%", "%$palabra%", "%$palabra%", "%$palabra%", "%$palabra%");
            }
        }
        foreach (['zona_id', 'preventista_id', 'lista_precio_id'] as $k) {
            if ($v = (int) Request::get($k, 0)) {
                $w[] = "c.$k = ?";
                $p[] = $v;
            }
        }
        $estado = Request::get('estado', '');
        if ($estado !== '') {
            $w[] = 'c.estado = ?';
            $p[] = (string) $estado;
        }
        if ($cp = Request::get('condicion_pago')) {
            $w[] = 'c.condicion_pago = ?';
            $p[] = (string) $cp;
        }
        $u = Auth::usuario();
        if (($u['rol_slug'] ?? '') === 'preventista' && $u['preventista_id']) {
            $w[] = 'c.preventista_id = ?';
            $p[] = (int) $u['preventista_id'];
        }
        $having = Request::get('con_deuda') ? ' HAVING saldo > 0' : (Request::get('vencidos') ? ' HAVING vencido > 0' : '');
        $sql = 'SELECT c.*, z.nombre AS zona, pr.nombre AS preventista, l.nombre AS lista, ' . Consultas::saldoCliente() . ' AS saldo, ' . Consultas::vencidoCliente() . ' AS vencido
                FROM clientes c LEFT JOIN zonas z ON z.id = c.zona_id LEFT JOIN preventistas pr ON pr.id = c.preventista_id LEFT JOIN listas_precios l ON l.id = c.lista_precio_id'
            . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . $having;
        return [$sql, $p];
    }

    private function combos(): array
    {
        return [
            'zonas' => DB::pares('SELECT id, nombre FROM zonas WHERE activo = 1 ORDER BY nombre'),
            'preventistas' => DB::pares('SELECT id, nombre FROM preventistas WHERE activo = 1 ORDER BY nombre'),
            'listas' => DB::pares('SELECT id, nombre FROM listas_precios WHERE activo = 1 ORDER BY orden, id'),
        ];
    }

    public function index(): void
    {
        [$sql, $p] = $this->filtros();
        $pag = new Paginador($sql, $p, ['nombre' => 'c.nombre_comercio', 'codigo' => 'c.codigo', 'zona' => 'zona', 'saldo' => 'saldo', 'vencido' => 'vencido', 'ruta' => 'c.orden_ruta'], 'nombre');
        $this->vista('clientes/index', ['titulo' => 'Clientes', 'pag' => $pag] + $this->combos());
    }

    public function exportar(): void
    {
        [$sql, $p] = $this->filtros();
        $filas = DB::filas($sql . ' ORDER BY c.nombre_comercio', $p);
        foreach ($filas as &$f) {
            $f['condicion_iva'] = condicion_iva($f['condicion_iva']);
            $f['dias_visita'] = dias_texto($f['dias_visita']);
        }
        Exportador::enviar((string) Request::get('formato', 'csv'), 'Clientes', [
            'codigo' => ['Código', 'texto'], 'nombre_comercio' => ['Comercio', 'texto'], 'titular' => ['Titular', 'texto'], 'documento' => ['CUIT/DNI', 'texto'],
            'condicion_iva' => ['Cond. IVA', 'texto'], 'direccion' => ['Dirección', 'texto'], 'zona' => ['Zona', 'texto'], 'telefono' => ['Teléfono', 'texto'],
            'dias_visita' => ['Visita', 'texto'], 'lista' => ['Lista', 'texto'], 'preventista' => ['Preventista', 'texto'], 'limite_credito' => ['Límite', 'money'],
            'saldo' => ['Saldo', 'money'], 'vencido' => ['Vencido', 'money'], 'estado' => ['Estado', 'texto'],
        ], $filas, ['nombre_comercio' => 'TOTAL', 'saldo' => array_sum(array_column($filas, 'saldo')), 'vencido' => array_sum(array_column($filas, 'vencido'))]);
    }

    public function nuevo(): void
    {
        $this->formulario([
            'codigo' => ClienteService::proximoCodigo(), 'tipo_documento' => 'CUIT', 'condicion_iva' => 'MONO', 'localidad' => 'Salta',
            'condicion_pago' => 'contado', 'estado' => 'activo', 'lista_precio_id' => 2, 'tipo_comercio' => 'Kiosco',
        ], [], null);
    }

    public function editar(string $id): void
    {
        $this->formulario($this->buscarOFallar('clientes', (int) $id), [], (int) $id);
    }

    private function formulario(array $datos, array $errores, ?int $id): void
    {
        $this->vista('clientes/form', ['titulo' => $id ? 'Editar cliente' : 'Nuevo cliente', 'datos' => $datos, 'errores' => $errores, 'id' => $id] + $this->combos());
    }

    public function guardar(?string $id = null): void
    {
        $id = $id !== null ? (int) $id : null;
        $antes = $id ? $this->buscarOFallar('clientes', $id) : null;
        $campos = ['codigo', 'nombre_comercio', 'titular', 'tipo_documento', 'documento', 'condicion_iva', 'tipo_comercio', 'direccion', 'localidad',
            'telefono', 'whatsapp', 'email', 'horario_visita', 'horario_entrega', 'condicion_pago', 'observaciones'];
        $d = [];
        foreach ($campos as $c) {
            $d[$c] = Request::texto($c);
        }
        foreach (['zona_id', 'lista_precio_id', 'preventista_id'] as $c) {
            $d[$c] = Request::entero($c) ?: null;
        }
        $d['dias_visita'] = implode(',', array_map('intval', (array) Request::input('dias_visita', [])));
        $d['dias_entrega'] = implode(',', array_map('intval', (array) Request::input('dias_entrega', [])));
        $d['limite_credito'] = Request::input('limite_credito', '0');
        $d['dias_credito'] = Request::entero('dias_credito');
        $d['orden_ruta'] = Request::entero('orden_ruta');
        $d['latitud'] = Request::texto('latitud');
        $d['longitud'] = Request::texto('longitud');
        $reglas = [
            'codigo' => 'requerido|max:20|unico:clientes,codigo' . ($id ? ",$id" : ''), 'nombre_comercio' => 'requerido|max:150',
            'condicion_iva' => 'requerido|en:RI,MONO,EX,CF', 'tipo_documento' => 'en:CUIT,CUIL,DNI', 'email' => 'email|max:120',
            'condicion_pago' => 'requerido|en:contado,cta_cte', 'limite_credito' => 'numerico|min:0', 'latitud' => 'numerico', 'longitud' => 'numerico',
        ];
        if (in_array($d['tipo_documento'], ['CUIT', 'CUIL'], true) && $d['documento']) {
            $reglas['documento'] = 'cuit';
        }
        $v = Validador::make($d, $reglas);
        $errores = $v->errores();
        if ($d['condicion_iva'] === 'RI' && (!$d['documento'] || $d['tipo_documento'] !== 'CUIT')) {
            $errores['documento'] = 'Para Responsable Inscripto se requiere CUIT (Factura A).';
        }
        if ($errores) {
            $this->formulario(array_merge($antes ?? [], $d), $errores, $id);
            return;
        }
        $d['limite_credito'] = parse_decimal($d['limite_credito']);
        $d['latitud'] = $d['latitud'] !== null ? (float) $d['latitud'] : null;
        $d['longitud'] = $d['longitud'] !== null ? (float) $d['longitud'] : null;
        $d['documento'] = $d['documento'] ? preg_replace('/[^\d\-]/', '', $d['documento']) : null;
        if ($d['condicion_pago'] === 'contado') {
            $d['dias_credito'] = 0;
        }
        if ($id) {
            DB::actualizar('clientes', $d + ['actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
            Auditoria::registrar('clientes', 'editar', 'cliente', $id, $antes, $d, 'Edición del cliente ' . $d['nombre_comercio']);
        } else {
            $id = DB::insertar('clientes', $d + ['estado' => 'activo', 'creado_en' => ahora()]);
            Auditoria::registrar('clientes', 'crear', 'cliente', $id, null, $d, 'Alta del cliente ' . $d['nombre_comercio']);
        }
        $this->redirigir('/clientes/' . $id, 'Cliente guardado.');
    }

    public function ver(string $id): void
    {
        $c = DB::fila('SELECT c.*, z.nombre AS zona, pr.nombre AS preventista, l.nombre AS lista FROM clientes c LEFT JOIN zonas z ON z.id = c.zona_id
                       LEFT JOIN preventistas pr ON pr.id = c.preventista_id LEFT JOIN listas_precios l ON l.id = c.lista_precio_id WHERE c.id = ?', [(int) $id]);
        if (!$c) {
            $this->buscarOFallar('clientes', (int) $id);
        }
        $cc = CuentaCorriente::clientes();
        $this->vista('clientes/ver', [
            'titulo' => $c['nombre_comercio'], 'c' => $c, 'resumen' => ClienteService::resumen((int) $id), 'antiguedad' => $cc->antiguedad((int) $id),
            'problemas' => ClienteService::problemasCredito((int) $id),
            'pedidos' => DB::filas('SELECT * FROM pedidos WHERE cliente_id = ? ORDER BY id DESC LIMIT 6', [$id]),
            'comprobantes' => DB::filas("SELECT c.*, pv.numero AS pv_numero FROM comprobantes c JOIN puntos_venta pv ON pv.id = c.punto_venta_id WHERE c.cliente_id = ? AND c.tipo <> 'PR' ORDER BY c.id DESC LIMIT 6", [$id]),
            'top' => DB::filas(
                "SELECT p.id, p.descripcion, SUM(ci.cantidad_base) AS unidades, SUM(ci.total) AS total, MAX(c.fecha) AS ultima
                 FROM comprobante_items ci JOIN comprobantes c ON c.id = ci.comprobante_id JOIN productos p ON p.id = ci.producto_id
                 WHERE c.cliente_id = ? AND c.estado = 'emitido' AND c.tipo IN ('FA','FB','FC','RM') GROUP BY p.id, p.descripcion ORDER BY unidades DESC LIMIT 10",
                [$id]
            ),
            'visitas' => DB::filas('SELECT v.*, pr.nombre AS preventista FROM visitas v LEFT JOIN preventistas pr ON pr.id = v.preventista_id WHERE v.cliente_id = ? ORDER BY v.id DESC LIMIT 5', [$id]),
            'compras90' => (float) DB::valor("SELECT COALESCE(SUM(CASE WHEN tipo LIKE 'NC%' THEN -total ELSE total END), 0) FROM comprobantes WHERE cliente_id = ? AND estado = 'emitido' AND tipo IN " . Consultas::TIPOS_VENTA . ' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)', [$id]),
        ]);
    }

    public function cuenta(string $id): void
    {
        $c = $this->buscarOFallar('clientes', (int) $id);
        $desde = parse_fecha(Request::get('desde')) ?? date('Y-m-d', strtotime('-90 days'));
        $hasta = parse_fecha(Request::get('hasta')) ?? hoy();
        $cc = CuentaCorriente::clientes();
        $mov = $cc->movimientos((int) $id, $desde, $hasta);
        if ($formato = Request::get('formato')) {
            $filas = array_map(fn ($f) => ['fecha' => $f['fecha'], 'descripcion' => $f['descripcion'], 'vencimiento' => $f['vencimiento'],
                'debe' => (float) $f['importe'] > 0 ? $f['importe'] : '', 'haber' => (float) $f['importe'] < 0 ? -$f['importe'] : '', 'saldo' => $f['saldo']], $mov['filas']);
            array_unshift($filas, ['descripcion' => 'Saldo anterior al ' . fecha($desde), 'saldo' => $mov['anterior']]);
            Exportador::enviar((string) $formato, 'Cuenta corriente ' . $c['nombre_comercio'], [
                'fecha' => ['Fecha', 'fecha'], 'descripcion' => ['Concepto', 'texto'], 'vencimiento' => ['Vence', 'fecha'],
                'debe' => ['Debe', 'money'], 'haber' => ['Haber', 'money'], 'saldo' => ['Saldo', 'money'],
            ], $filas, null, 'Del ' . fecha($desde) . ' al ' . fecha($hasta));
            return;
        }
        $this->vista('clientes/cuenta', [
            'titulo' => 'Cuenta corriente — ' . $c['nombre_comercio'], 'c' => $c, 'mov' => $mov, 'desde' => $desde, 'hasta' => $hasta,
            'antiguedad' => $cc->antiguedad((int) $id), 'pendientes' => $cc->pendientes((int) $id), 'credito' => $cc->creditoDisponible((int) $id),
        ]);
    }

    public function saldos(): void
    {
        $where = '';
        $p = [];
        if ($z = (int) Request::get('zona_id', 0)) {
            $where = ' AND c.zona_id = ?';
            $p[] = $z;
        }
        $sql = "SELECT c.id, c.codigo, c.nombre_comercio, c.limite_credito, c.estado, z.nombre AS zona,
                    SUM(cc.importe) AS saldo,
                    SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) <= 0 THEN cc.pendiente ELSE 0 END) AS a_vencer,
                    SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) BETWEEN 1 AND 30 THEN cc.pendiente ELSE 0 END) AS d30,
                    SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) BETWEEN 31 AND 60 THEN cc.pendiente ELSE 0 END) AS d60,
                    SUM(CASE WHEN cc.importe > 0 AND DATEDIFF(CURDATE(), cc.vencimiento) > 60 THEN cc.pendiente ELSE 0 END) AS mas60
                FROM clientes c JOIN cc_clientes cc ON cc.cliente_id = c.id LEFT JOIN zonas z ON z.id = c.zona_id
                WHERE 1 = 1 $where GROUP BY c.id, c.codigo, c.nombre_comercio, c.limite_credito, c.estado, z.nombre HAVING ABS(saldo) > 0.009";
        if ($formato = Request::get('formato')) {
            $filas = DB::filas($sql . ' ORDER BY saldo DESC', $p);
            $tot = ['nombre_comercio' => 'TOTAL'];
            foreach (['saldo', 'a_vencer', 'd30', 'd60', 'mas60'] as $k) {
                $tot[$k] = array_sum(array_column($filas, $k));
            }
            Exportador::enviar((string) $formato, 'Saldos de clientes', [
                'codigo' => ['Código', 'texto'], 'nombre_comercio' => ['Cliente', 'texto'], 'zona' => ['Zona', 'texto'], 'saldo' => ['Saldo', 'money'],
                'a_vencer' => ['A vencer', 'money'], 'd30' => ['1-30 días', 'money'], 'd60' => ['31-60 días', 'money'], 'mas60' => ['+60 días', 'money'],
            ], $filas, $tot, 'Antigüedad de deuda al ' . fecha(hoy()));
            return;
        }
        $pag = new Paginador($sql, $p, ['saldo' => 'saldo', 'nombre' => 'c.nombre_comercio', 'vencido' => '(d30 + d60 + mas60)'], 'saldo', 'desc', 50);
        $tot = DB::fila("SELECT SUM(saldo) AS saldo, SUM(a_vencer) AS a_vencer, SUM(d30) AS d30, SUM(d60) AS d60, SUM(mas60) AS mas60 FROM ($sql) t", $p);
        $this->vista('clientes/saldos', ['titulo' => 'Saldos y deudas de clientes', 'pag' => $pag, 'tot' => $tot, 'zonas' => DB::pares('SELECT id, nombre FROM zonas ORDER BY nombre')]);
    }

    public function cambiarEstado(string $id): void
    {
        $c = $this->buscarOFallar('clientes', (int) $id);
        $estado = (string) Request::texto('estado');
        if (!in_array($estado, ['activo', 'inactivo', 'bloqueado'], true)) {
            throw new ErrorNegocio('Estado inválido.');
        }
        if ((int) $id === (int) \App\Core\Ajustes::get('cliente_consumidor_final', 1) && $estado !== 'activo') {
            throw new ErrorNegocio('El cliente "Consumidor Final" no se puede desactivar.');
        }
        DB::actualizar('clientes', ['estado' => $estado, 'actualizado_en' => ahora()], 'id = :id', ['id' => (int) $id]);
        Auditoria::registrar('clientes', 'estado', 'cliente', (int) $id, ['estado' => $c['estado']], ['estado' => $estado], 'Cambio de estado del cliente');
        $this->volver('Estado del cliente: ' . $estado . '.');
    }

    /** Saldo inicial o ajuste manual de la cuenta corriente (auditado). */
    public function ajusteCuenta(string $id): void
    {
        $this->buscarOFallar('clientes', (int) $id);
        $importe = Request::decimal('importe');
        $sentido = Request::texto('sentido') === 'haber' ? -1 : 1;
        $desc = Request::texto('descripcion');
        if ($importe <= 0 || !$desc) {
            throw new ErrorNegocio('Indicá importe y descripción del ajuste.');
        }
        $tipo = Request::texto('tipo') === 'saldo_inicial' ? 'saldo_inicial' : 'ajuste';
        $cc = CuentaCorriente::clientes();
        $mid = $cc->registrar((int) $id, $tipo, $sentido * $importe, $desc, 'ajuste_cc', null, parse_fecha(Request::texto('vencimiento')));
        Auditoria::registrar('clientes', 'ajuste_cc', 'cliente', (int) $id, null, ['importe' => $sentido * $importe, 'descripcion' => $desc, 'movimiento' => $mid], 'Ajuste de cuenta corriente');
        $this->volver('Ajuste registrado en la cuenta corriente.');
    }
}
