<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Ajustes;
use App\Core\DB;
use App\Services\Migrador;

/**
 * Tareas de instalación: estructura, datos iniciales mínimos y usuario administrador.
 * La usan el instalador web, el script de consola y las pruebas.
 */
final class Instalacion
{
    public const REQUISITOS_EXT = ['pdo_mysql', 'mbstring', 'gd', 'json', 'zlib'];

    /** Verificación de requisitos: [[descripcion, ok, detalle], ...] */
    public static function requisitos(): array
    {
        $r = [];
        $r[] = ['PHP 8.1 o superior', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION];
        foreach (self::REQUISITOS_EXT as $ext) {
            $r[] = ["Extensión $ext", extension_loaded($ext), extension_loaded($ext) ? 'instalada' : 'falta'];
        }
        $r[] = ['Extensión zip (opcional, exportar Excel)', true, extension_loaded('zip') ? 'instalada' : 'no instalada: se exportará CSV'];
        foreach (['config', 'storage', 'storage/logs', 'storage/backups', 'storage/sessions', 'storage/cache', 'public/uploads', 'public/uploads/productos', 'public/uploads/empresa'] as $dir) {
            $ruta = BASE_PATH . '/' . $dir;
            $r[] = ["Permiso de escritura en /$dir", is_dir($ruta) && is_writable($ruta), is_writable($ruta) ? 'ok' : 'sin permiso (usar 755 o 775)'];
        }
        return $r;
    }

    public static function crearEstructura(): void
    {
        Migrador::ejecutarSql((string) file_get_contents(BASE_PATH . '/database/schema.sql'));
        Migrador::marcarTodas();
    }

    /** Datos mínimos para empezar a trabajar (sin datos de ejemplo). */
    public static function datosIniciales(array $admin, array $empresa = []): void
    {
        DB::transaccion(function () use ($admin, $empresa) {
            foreach (Ajustes::DEFECTOS as $clave => $valor) {
                DB::query('INSERT IGNORE INTO configuracion (clave, valor) VALUES (?, ?)', [$clave, $valor]);
            }
            foreach ($empresa as $clave => $valor) {
                Ajustes::set($clave, (string) $valor);
            }

            Permisos::sincronizar();
            $permisos = DB::pares('SELECT CONCAT(modulo, ".", accion), id FROM permisos');
            foreach (Permisos::rolesBase() as $slug => [$nombre, $desc, $inicio, $lista]) {
                $rolId = DB::valor('SELECT id FROM roles WHERE slug = ?', [$slug]);
                if (!$rolId) {
                    $rolId = DB::insertar('roles', ['nombre' => $nombre, 'slug' => $slug, 'descripcion' => $desc, 'pantalla_inicio' => $inicio, 'es_sistema' => 1]);
                }
                foreach ($lista as $p) {
                    if (isset($permisos[$p])) {
                        DB::query('INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)', [$rolId, $permisos[$p]]);
                    }
                }
            }

            if (!DB::valor('SELECT COUNT(*) FROM puntos_venta')) {
                DB::insertar('puntos_venta', ['id' => 1, 'numero' => 1, 'nombre' => 'Casa central', 'proveedor_facturacion' => 'interno', 'activo' => 1]);
            }
            if (!DB::valor('SELECT COUNT(*) FROM depositos')) {
                DB::insertar('depositos', ['id' => 1, 'nombre' => 'Depósito central', 'tipo' => 'deposito', 'activo' => 1]);
            }
            if (!DB::valor('SELECT COUNT(*) FROM cajas')) {
                DB::insertar('cajas', ['id' => 1, 'nombre' => 'Caja oficina', 'activo' => 1]);
                DB::insertar('cajas', ['id' => 2, 'nombre' => 'Caja mostrador', 'activo' => 1]);
            }
            if (!DB::valor('SELECT COUNT(*) FROM listas_precios')) {
                $listas = [[1, 'Mayorista', 15, 1, 1], [2, 'Kiosco', 25, 1, 2], [3, 'Minorista', 35, 5, 3], [4, 'Especial', 10, 1, 4]];
                foreach ($listas as [$id, $n, $m, $r, $o]) {
                    DB::insertar('listas_precios', ['id' => $id, 'nombre' => $n, 'margen_porc' => $m, 'redondeo' => $r, 'orden' => $o, 'activo' => 1]);
                }
            }
            if (!DB::valor('SELECT COUNT(*) FROM clientes')) {
                DB::insertar('clientes', [
                    'id' => 1, 'codigo' => 'C0000', 'nombre_comercio' => 'Consumidor Final', 'tipo_documento' => 'DNI',
                    'condicion_iva' => 'CF', 'lista_precio_id' => 3, 'condicion_pago' => 'contado', 'estado' => 'activo',
                    'localidad' => 'Salta', 'creado_en' => ahora(),
                ]);
            }
            if (!DB::valor('SELECT COUNT(*) FROM gastos_categorias')) {
                foreach (['Combustible', 'Sueldos y cargas sociales', 'Alquiler', 'Servicios (luz, agua, gas, internet)', 'Mantenimiento de vehículos', 'Impuestos y tasas', 'Comisiones bancarias', 'Librería y oficina', 'Limpieza', 'Comisiones de preventistas', 'Otros'] as $c) {
                    DB::insertar('gastos_categorias', ['nombre' => $c, 'activo' => 1]);
                }
            }
            if (!DB::valor('SELECT COUNT(*) FROM cuentas_bancarias')) {
                DB::insertar('cuentas_bancarias', ['nombre' => 'Banco Macro - Cta. Cte.', 'tipo' => 'banco', 'banco' => 'Banco Macro', 'saldo_inicial' => 0, 'activo' => 1]);
                DB::insertar('cuentas_bancarias', ['nombre' => 'Mercado Pago', 'tipo' => 'billetera', 'banco' => 'Mercado Pago', 'saldo_inicial' => 0, 'activo' => 1]);
                DB::insertar('cuentas_bancarias', ['nombre' => 'Tarjetas a acreditar', 'tipo' => 'tarjeta', 'banco' => 'Posnet / Clover', 'saldo_inicial' => 0, 'activo' => 1]);
            }

            if (!empty($admin['usuario']) && !DB::valor('SELECT id FROM usuarios WHERE usuario = ?', [$admin['usuario']])) {
                DB::insertar('usuarios', [
                    'nombre' => $admin['nombre'] ?? 'Administrador', 'usuario' => mb_strtolower($admin['usuario']),
                    'email' => $admin['email'] ?? null, 'password_hash' => password_hash($admin['clave'], PASSWORD_DEFAULT),
                    'rol_id' => DB::valor("SELECT id FROM roles WHERE slug = 'admin'"), 'caja_id' => 1, 'activo' => 1, 'creado_en' => ahora(),
                ]);
            }
        });
    }

    /** Escribe config/config.php */
    public static function escribirConfig(array $db, bool $debug = false, string $url = ''): void
    {
        $contenido = "<?php\n/**\n * Configuración generada por el instalador el " . date('d/m/Y H:i') . ".\n * Ver config.example.php para la descripción de cada opción.\n */\n\nreturn " . var_export([
            'app' => ['nombre' => 'Distribuidora Toro', 'debug' => $debug, 'url' => $url, 'clave' => bin2hex(random_bytes(24))],
            'db' => ['host' => $db['host'], 'puerto' => (int) $db['puerto'], 'nombre' => $db['nombre'], 'usuario' => $db['usuario'], 'clave' => $db['clave']],
            'sesion' => ['inactividad_min' => 60, 'inactividad_movil_min' => 720],
            'backup' => ['conservar' => 15],
        ], true) . ";\n";
        file_put_contents(BASE_PATH . '/config/config.php', $contenido, LOCK_EX);
        @chmod(BASE_PATH . '/config/config.php', 0644);
    }

    public static function bloquear(): void
    {
        file_put_contents(STORAGE_PATH . '/instalado.lock', 'Instalado el ' . date('Y-m-d H:i:s') . "\nBorrar este archivo sólo para reinstalar (¡borra los datos!).\n");
    }
}
