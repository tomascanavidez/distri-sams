<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ajustes;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Request;
use App\Core\Validador;
use App\Services\Migrador;
use App\Services\PrecioService;
use App\Support\Imagenes;

final class ConfiguracionController extends Controller
{
    /** Parámetros editables: clave => [etiqueta, tipo, opciones|ayuda] agrupados por sección. */
    public static function campos(): array
    {
        return [
            'Datos de la empresa' => [
                'empresa_nombre' => ['Nombre comercial', 'texto'],
                'empresa_razon_social' => ['Razón social', 'texto'],
                'empresa_cuit' => ['CUIT', 'texto'],
                'empresa_condicion_iva' => ['Condición frente al IVA', 'select', ['RI' => 'Responsable Inscripto', 'MONO' => 'Monotributista', 'EX' => 'Exento']],
                'empresa_iibb' => ['Ingresos Brutos (N°)', 'texto'],
                'empresa_inicio_actividades' => ['Inicio de actividades', 'fecha'],
                'empresa_domicilio' => ['Domicilio', 'texto'],
                'empresa_telefono' => ['Teléfono', 'texto'],
                'empresa_email' => ['Correo electrónico', 'texto'],
                'empresa_leyenda' => ['Leyenda al pie de los comprobantes', 'texto'],
            ],
            'Ventas y facturación' => [
                'punto_venta_defecto' => ['Punto de venta por defecto', 'select', 'puntos_venta'],
                'comprobante_reparto' => ['Comprobante al entregar en reparto', 'select', ['factura' => 'Factura (A o B según el cliente)', 'remito' => 'Remito (se factura después)']],
                'pos_lista_precio' => ['Lista de precios del mostrador', 'select', 'listas'],
                'cliente_consumidor_final' => ['Cliente "Consumidor Final"', 'select', 'clientes_cf'],
                'pedido_movil_auto_confirmar' => ['Confirmar automáticamente los pedidos de la app', 'select', ['1' => 'Sí (si no hay problemas de stock, precio o crédito)', '0' => 'No, siempre quedan para revisión']],
            ],
            'Precios y costos' => [
                'costo_metodo' => ['Costo para precios y valorización', 'select', ['ultimo' => 'Último costo de compra', 'promedio' => 'Costo promedio ponderado']],
                'actualizar_precios_al_recibir' => ['Recalcular precios al recibir mercadería', 'select', ['1' => 'Sí', '0' => 'No']],
                'redondeo_modo' => ['Modo de redondeo de precios', 'select', ['arriba' => 'Hacia arriba', 'cercano' => 'Al más cercano', 'abajo' => 'Hacia abajo']],
            ],
            'Crédito de clientes' => [
                'credito_modo' => ['Si un cliente supera el límite o tiene deuda vencida', 'select', ['advertir' => 'Advertir pero permitir', 'bloquear' => 'Bloquear la venta a cuenta corriente', 'ignorar' => 'No controlar']],
                'credito_tolerancia_dias' => ['Días de tolerancia de deuda vencida', 'numero'],
                'bloqueo_automatico_dias' => ['Bloquear automáticamente con deuda vencida de más de (días, 0 = no)', 'numero'],
            ],
            'Stock' => [
                'deposito_central' => ['Depósito principal', 'select', 'depositos'],
                'stock_permitir_negativo' => ['Permitir stock negativo', 'select', ['0' => 'No', '1' => 'Sí']],
                'stock_alerta_vencimiento_dias' => ['Avisar vencimientos con anticipación (días)', 'numero'],
                'sugerencia_dias_venta' => ['Sugerencia de compra: días de venta analizados', 'numero'],
                'sugerencia_dias_cobertura' => ['Sugerencia de compra: días a cubrir', 'numero'],
            ],
            'Seguridad' => [
                'login_max_intentos' => ['Intentos de ingreso fallidos antes del bloqueo', 'numero'],
                'login_bloqueo_min' => ['Minutos de bloqueo', 'numero'],
            ],
        ];
    }

    public function index(): void
    {
        $opciones = [
            'puntos_venta' => DB::pares("SELECT id, CONCAT(LPAD(numero, 4, '0'), ' - ', nombre) FROM puntos_venta WHERE activo = 1 ORDER BY numero"),
            'listas' => DB::pares('SELECT id, nombre FROM listas_precios WHERE activo = 1 ORDER BY orden'),
            'depositos' => DB::pares("SELECT id, nombre FROM depositos WHERE tipo = 'deposito' AND activo = 1"),
            'clientes_cf' => DB::pares("SELECT id, nombre_comercio FROM clientes WHERE condicion_iva = 'CF' ORDER BY id LIMIT 50"),
        ];
        $numeracion = DB::filas('SELECT n.*, pv.numero AS pv_numero, pv.nombre FROM numeracion n JOIN puntos_venta pv ON pv.id = n.punto_venta_id ORDER BY pv.numero, n.tipo');
        $listas = DB::filas("SELECT l.*, (SELECT COUNT(*) FROM producto_precios pp WHERE pp.lista_id = l.id AND (pp.modo = 'fijo' OR pp.margen_porc IS NOT NULL)) AS propios FROM listas_precios l WHERE l.activo = 1 ORDER BY l.orden, l.id");
        $this->vista('configuracion/index', ['titulo' => 'Empresa y parámetros', 'valores' => Ajustes::todos(), 'opciones' => $opciones, 'numeracion' => $numeracion, 'puede' => Auth::puede('configuracion.editar'), 'listas' => $listas]);
    }

    public function guardar(): void
    {
        $antes = Ajustes::todos();
        $cambios = [];
        if (Request::texto('empresa_cuit') && !Validador::cuitValido((string) Request::texto('empresa_cuit'))) {
            throw new ErrorNegocio('El CUIT de la empresa no es válido.');
        }
        foreach (self::campos() as $campos) {
            foreach ($campos as $clave => [$etq, $tipo]) {
                if (!array_key_exists($clave, $_POST)) {
                    continue;
                }
                $v = trim((string) $_POST[$clave]);
                if ($tipo === 'numero') {
                    $v = (string) max(0, (int) $v);
                }
                if ($tipo === 'fecha') {
                    $v = (string) parse_fecha($v);
                }
                if ((string) ($antes[$clave] ?? '') !== $v) {
                    $cambios[$clave] = $v;
                    Ajustes::set($clave, $v);
                }
            }
        }
        if ($f = Request::archivo('logo')) {
            $nombre = Imagenes::guardarLogo($f);
            Ajustes::set('empresa_logo', $nombre);
            $cambios['empresa_logo'] = $nombre;
        }
        if (Request::input('quitar_logo')) {
            Ajustes::set('empresa_logo', '');
            $cambios['empresa_logo'] = '';
        }
        // Ajuste manual del último número utilizado (sólo hacia adelante, para no repetir números)
        foreach ((array) Request::input('numeracion', []) as $clave => $nuevo) {
            [$pv, $tipo] = explode('|', (string) $clave) + [null, null];
            $actual = (int) DB::valor('SELECT ultimo_numero FROM numeracion WHERE punto_venta_id = ? AND tipo = ?', [(int) $pv, $tipo]);
            $nuevo = (int) $nuevo;
            if ($nuevo !== $actual) {
                if ($nuevo < $actual) {
                    throw new ErrorNegocio("La numeración de $tipo no puede retroceder (último usado: $actual).");
                }
                DB::query('UPDATE numeracion SET ultimo_numero = ? WHERE punto_venta_id = ? AND tipo = ?', [$nuevo, (int) $pv, $tipo]);
                $cambios["numeracion_$tipo"] = $nuevo;
            }
        }
        if ($cambios) {
            Auditoria::registrar('configuracion', 'editar', 'configuracion', null, array_intersect_key($antes, $cambios), $cambios, 'Cambio de parámetros del sistema');
        }
        $this->volver($cambios ? 'Configuración guardada.' : 'No hubo cambios.');
    }

    /** Márgenes y redondeos por defecto de las listas de precios (con recálculo de todos los productos). */
    public function guardarListas(): void
    {
        $pisar = array_map('intval', (array) Request::input('pisar', []));
        $productos = 0;
        foreach ((array) Request::input('margen', []) as $listaId => $margen) {
            $listaId = (int) $listaId;
            if (trim((string) $margen) === '') {
                throw new ErrorNegocio('Indicá el margen de todas las listas.');
            }
            $redondeo = parse_decimal($_POST['redondeo'][$listaId] ?? 0);
            $productos = max($productos, PrecioService::configurarLista($listaId, parse_decimal($margen), $redondeo, in_array($listaId, $pisar, true), 'Cambio de margen por defecto desde Configuración'));
        }
        $this->volver($productos ? "Listas actualizadas: se recalcularon los precios de $productos productos." : 'No hubo cambios en las listas.');
    }

    public function migraciones(): void
    {
        $this->vista('configuracion/migraciones', ['titulo' => 'Actualizaciones de la base de datos', 'disponibles' => Migrador::disponibles(), 'aplicadas' => Migrador::aplicadas(), 'pendientes' => Migrador::pendientes()]);
    }

    public function migrar(): void
    {
        $hechas = Migrador::migrar();
        Auditoria::registrar('configuracion', 'migrar', null, null, null, ['versiones' => $hechas], 'Aplicación de migraciones');
        $this->volver($hechas ? 'Se aplicaron las actualizaciones: ' . implode(', ', $hechas) : 'La base ya estaba actualizada.');
    }
}
