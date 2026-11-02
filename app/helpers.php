<?php
/**
 * Funciones de ayuda globales: escapado, URLs, formatos argentinos, flash, permisos.
 */

declare(strict_types=1);

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

/** Escapa texto para HTML (prevención de XSS). */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ruta base pública de la app (ej. "" o "/sistema"). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $url = (string) Config::get('app.url', '');
    if ($url !== '') {
        return $base = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // Si el sitio se publica desde la raíz del proyecto (.htaccess reenvía a /public), se oculta "/public".
    $dir = preg_replace('#/public(/install)?$#', '', $dir);
    $dir = preg_replace('#/install$#', '', (string) $dir);
    return $base = ($dir === '/' || $dir === '.') ? '' : (string) $dir;
}

function url(string $ruta = '/'): string
{
    if ($ruta === '' || $ruta[0] !== '/') {
        $ruta = '/' . $ruta;
    }
    return base_path() . $ruta;
}

/** URL de un recurso estático con versión para evitar caché vieja. */
function asset(string $ruta): string
{
    $archivo = PUBLIC_PATH . '/' . ltrim($ruta, '/');
    $v = is_file($archivo) ? substr((string) filemtime($archivo), -6) : APP_VERSION;
    return url('/' . ltrim($ruta, '/')) . '?v=' . $v;
}

function ahora(): string
{
    return date('Y-m-d H:i:s', App\Core\Reloj::ahora());
}

function hoy(): string
{
    return date('Y-m-d', App\Core\Reloj::ahora());
}

/** $ 1.234,56 */
function money(mixed $n, bool $simbolo = true): string
{
    $s = number_format(round((float) $n, 2), 2, ',', '.');
    if ($s === '-0,00') {
        $s = '0,00';
    }
    return $simbolo ? '$ ' . $s : $s;
}

function num(mixed $n, int $decimales = 2): string
{
    return number_format((float) $n, $decimales, ',', '.');
}

/** Cantidad sin ceros de más: 12 / 1,5 / 0,250 */
function cant(mixed $n): string
{
    $n = round((float) $n, 3);
    if (floor($n) == $n) {
        return number_format($n, 0, ',', '.');
    }
    return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
}

function fecha(?string $f): string
{
    if (!$f || str_starts_with($f, '0000')) {
        return '';
    }
    $t = strtotime($f);
    return $t ? date('d/m/Y', $t) : '';
}

function fecha_hora(?string $f): string
{
    if (!$f) {
        return '';
    }
    $t = strtotime($f);
    return $t ? date('d/m/Y H:i', $t) : '';
}

/** Normaliza "1.234,56" / "1234,56" / "1234.56" a "1234.56". */
function parse_decimal_str(string $v): string
{
    $v = trim(str_replace(['$', ' ', "\u{00a0}"], '', $v));
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return $v;
}

function parse_decimal(mixed $v): float
{
    if (is_int($v) || is_float($v)) {
        return (float) $v;
    }
    $s = parse_decimal_str((string) $v);
    return is_numeric($s) ? (float) $s : 0.0;
}

/** Acepta dd/mm/aaaa o aaaa-mm-dd. Devuelve aaaa-mm-dd o null. */
function parse_fecha(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
        return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
    }
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $v, $m)) {
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
    }
    return null;
}

function csrf_campo(): string
{
    return Csrf::campo();
}

function csrf_token(): string
{
    return Csrf::token();
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function puede(string $permiso): bool
{
    return Auth::puede($permiso);
}

function usuario(): ?array
{
    return Auth::usuario();
}

function ajuste(string $clave, mixed $defecto = null): mixed
{
    return Ajustes::get($clave, $defecto);
}

/** UUID v4 (para identificar operaciones). */
function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function es_uuid(?string $v): bool
{
    return is_string($v) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
}

/** Condiciones frente al IVA. */
function condiciones_iva(): array
{
    return [
        'RI' => 'Responsable Inscripto',
        'MONO' => 'Monotributista',
        'EX' => 'Exento',
        'CF' => 'Consumidor Final',
    ];
}

function condicion_iva(?string $c): string
{
    return condiciones_iva()[$c] ?? (string) $c;
}

function alicuotas_iva(): array
{
    return ['21.00' => '21 %', '10.50' => '10,5 %', '0.00' => 'Exento'];
}

function formas_pago(): array
{
    return [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'mercadopago' => 'Mercado Pago / QR',
        'tarjeta' => 'Tarjeta',
        'cheque' => 'Cheque',
        'retencion' => 'Retención',
    ];
}

function forma_pago(?string $f): string
{
    return formas_pago()[$f] ?? ($f === 'cuenta_corriente' ? 'Cuenta corriente' : (string) $f);
}

function dias_semana(): array
{
    return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
}

/** "1,4" => "Lun, Jue" */
function dias_texto(?string $dias): string
{
    if (!$dias) {
        return '';
    }
    $nombres = dias_semana();
    $out = [];
    foreach (explode(',', $dias) as $d) {
        $d = (int) $d;
        if (isset($nombres[$d])) {
            $out[] = mb_substr($nombres[$d], 0, 3);
        }
    }
    return implode(', ', $out);
}

function tipos_comprobante(): array
{
    return [
        'FA' => 'Factura A', 'FB' => 'Factura B', 'FC' => 'Factura C',
        'NCA' => 'Nota de crédito A', 'NCB' => 'Nota de crédito B', 'NCC' => 'Nota de crédito C',
        'NDA' => 'Nota de débito A', 'NDB' => 'Nota de débito B', 'NDC' => 'Nota de débito C',
        'RM' => 'Remito', 'PR' => 'Presupuesto',
    ];
}

function tipo_comprobante(string $t): string
{
    return tipos_comprobante()[$t] ?? $t;
}

/** "FA 0001-00000123" */
function numero_comprobante(string $tipo, int $pv, int $numero): string
{
    return $tipo . ' ' . sprintf('%04d-%08d', $pv, $numero);
}

/** Etiqueta de estado con color. */
function estado_badge(?string $estado): string
{
    $colores = [
        'borrador' => 'secondary', 'confirmado' => 'primary', 'en_preparacion' => 'info', 'preparado' => 'info',
        'en_reparto' => 'warning', 'entregado' => 'success', 'entregado_parcial' => 'warning', 'rechazado' => 'danger',
        'anulado' => 'dark', 'anulada' => 'dark', 'emitido' => 'success', 'enviada' => 'primary',
        'recibida_parcial' => 'warning', 'recibida' => 'success', 'registrada' => 'success', 'aplicada' => 'success',
        'aplicado' => 'success', 'abierto' => 'primary', 'abierta' => 'success', 'cerrada' => 'secondary',
        'activo' => 'success', 'inactivo' => 'secondary', 'bloqueado' => 'danger', 'armado' => 'primary',
        'rendida' => 'success', 'pendiente' => 'secondary', 'parcial' => 'warning', 'cerrado' => 'danger',
        'cartera' => 'primary', 'depositado' => 'success', 'endosado' => 'info', 'pagada' => 'success',
        'registrado' => 'success',
    ];
    $textos = [
        'en_preparacion' => 'En preparación', 'en_reparto' => 'En reparto', 'entregado_parcial' => 'Entregado parcial',
        'recibida_parcial' => 'Recibida parcial', 'cerrado' => 'Cliente cerrado', 'parcial' => 'Entrega parcial',
    ];
    $estado = (string) $estado;
    $texto = $textos[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
    $color = $colores[$estado] ?? 'secondary';
    return '<span class="badge text-bg-' . $color . ' badge-estado">' . e($texto) . '</span>';
}

/** Marca como activo el ítem del menú. */
function menu_activo(string $prefijo): string
{
    $ruta = App\Core\Request::ruta();
    if ($prefijo === '/') {
        return $ruta === '/' ? 'active' : '';
    }
    return ($ruta === $prefijo || str_starts_with($ruta, $prefijo . '/')) ? 'active' : '';
}

/** Arma un enlace a Google Maps con coordenadas o dirección. */
function link_maps(?string $direccion, ?string $localidad = null, mixed $lat = null, mixed $lng = null): string
{
    if ($lat && $lng && (float) $lat != 0.0) {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng);
    }
    $q = trim(($direccion ?? '') . ', ' . ($localidad ?: 'Salta') . ', Argentina', ', ');
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}

/** Número de WhatsApp en formato internacional argentino (549 + área + número). */
function whatsapp_numero(?string $tel): string
{
    $d = preg_replace('/\D/', '', (string) $tel);
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '549')) {
        return $d;
    }
    if (str_starts_with($d, '54')) {
        return '549' . substr($d, 2);
    }
    $d = ltrim($d, '0');
    // Quita el "15" de celulares locales (ej. 387 15 4123456 => 387 4123456)
    if (preg_match('/^(\d{3,4})15(\d{6,8})$/', $d, $m)) {
        $d = $m[1] . $m[2];
    }
    return '549' . $d;
}

/** Para selects: <option> con selected. */
function opciones(array $opciones, mixed $seleccionado, ?string $vacio = null): string
{
    $html = $vacio !== null ? '<option value="">' . e($vacio) . '</option>' : '';
    foreach ($opciones as $valor => $texto) {
        $sel = ((string) $valor === (string) $seleccionado) ? ' selected' : '';
        $html .= '<option value="' . e($valor) . '"' . $sel . '>' . e($texto) . '</option>';
    }
    return $html;
}

/** Activo para un sub-ítem que puede llevar query string (ej. "/ventas?tipo=PR"). */
function menu_sub_activo(string $enlace): string
{
    $partes = parse_url($enlace);
    $ruta = App\Core\Request::ruta();
    if (($partes['path'] ?? '') !== $ruta) {
        return '';
    }
    parse_str($partes['query'] ?? '', $q);
    foreach ($q as $k => $v) {
        if ((string) ($_GET[$k] ?? '') !== (string) $v) {
            return '';
        }
    }
    // Un enlace sin query no queda activo si la URL actual tiene filtros que otro sub-ítem representa.
    if (!$q && (isset($_GET['tipo']) || isset($_GET['revision']))) {
        return '';
    }
    return 'active';
}

/** Enlace sólo si el usuario tiene el permiso; si no, el texto plano (escapado). */
function enlace_si(string $permiso, string $ruta, string $texto, string $clase = ''): string
{
    if (!puede($permiso)) {
        return '<span class="' . e($clase) . '">' . e($texto) . '</span>';
    }
    return '<a href="' . e(url($ruta)) . '" class="' . e($clase) . '">' . e($texto) . '</a>';
}
