<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autenticación, control de inactividad, bloqueo por intentos fallidos y permisos.
 */
final class Auth
{
    private static ?array $usuario = null;
    private static bool $cargado = false;

    /** Roles que trabajan desde el celular y tienen un tiempo de inactividad mayor. */
    public const ROLES_MOVILES = ['preventista', 'chofer'];

    /**
     * Intenta iniciar sesión. Devuelve null si fue exitoso o el mensaje de error.
     */
    public static function intentar(string $usuario, string $clave): ?string
    {
        $usuario = mb_strtolower(trim($usuario));
        $ip = Request::ip();
        $maxIntentos = (int) Ajustes::get('login_max_intentos', 5);
        $minutos = (int) Ajustes::get('login_bloqueo_min', 15);
        $desde = date('Y-m-d H:i:s', time() - $minutos * 60);

        // Fallidos del usuario desde su último ingreso correcto, dentro de la ventana.
        $fallidos = (int) DB::valor(
            'SELECT COUNT(*) FROM login_intentos WHERE exito = 0 AND fecha >= ? AND usuario = ?
             AND fecha > COALESCE((SELECT MAX(fecha) FROM login_intentos WHERE exito = 1 AND usuario = ?), ?)',
            [$desde, $usuario, $usuario, '2000-01-01']
        );
        // Además se limita por IP (con más margen: en la oficina varios comparten IP).
        $fallidosIp = (int) DB::valor(
            'SELECT COUNT(*) FROM login_intentos WHERE exito = 0 AND fecha >= ? AND ip = ?',
            [$desde, $ip]
        );
        if ($fallidos >= $maxIntentos || $fallidosIp >= $maxIntentos * 4) {
            return "Demasiados intentos fallidos. Esperá $minutos minutos e intentá de nuevo.";
        }

        $u = DB::fila('SELECT id, password_hash, activo FROM usuarios WHERE usuario = ?', [$usuario]);
        $ok = $u && (int) $u['activo'] === 1 && password_verify($clave, $u['password_hash']);

        DB::insertar('login_intentos', [
            'usuario' => substr($usuario, 0, 50),
            'ip' => $ip,
            'exito' => $ok ? 1 : 0,
            'fecha' => ahora(),
        ]);

        if (!$ok) {
            $restantes = $maxIntentos - $fallidos - 1;
            if ($u && (int) $u['activo'] !== 1) {
                return 'El usuario está desactivado. Consultá con el administrador.';
            }
            return $restantes > 0
                ? "Usuario o contraseña incorrectos. Te quedan $restantes intentos."
                : "Usuario o contraseña incorrectos. Se bloqueó el acceso por $minutos minutos.";
        }

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::actualizar('usuarios', ['password_hash' => password_hash($clave, PASSWORD_DEFAULT)], 'id = :id', ['id' => $u['id']]);
        }

        Session::regenerar();
        Csrf::renovar();
        $_SESSION['usuario_id'] = (int) $u['id'];
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['huella'] = self::huella();
        DB::actualizar('usuarios', ['ultimo_login' => ahora()], 'id = :id', ['id' => $u['id']]);
        self::$cargado = false;
        Auditoria::registrar('usuarios', 'login', 'usuario', (int) $u['id'], null, null, 'Inicio de sesión');
        return null;
    }

    public static function cerrar(): void
    {
        if (self::id()) {
            Auditoria::registrar('usuarios', 'logout', 'usuario', self::id(), null, null, 'Cierre de sesión');
        }
        Session::destruir();
        self::$usuario = null;
        self::$cargado = true;
    }

    /** Verifica que haya sesión válida y no vencida por inactividad. */
    public static function verificarSesion(): bool
    {
        if (empty($_SESSION['usuario_id'])) {
            return false;
        }
        if (($_SESSION['huella'] ?? '') !== self::huella()) {
            self::cerrar();
            return false;
        }
        $u = self::usuario();
        if (!$u || (int) $u['activo'] !== 1) {
            self::cerrar();
            return false;
        }
        $minutos = in_array($u['rol_slug'], self::ROLES_MOVILES, true)
            ? (int) Config::get('sesion.inactividad_movil_min', 720)
            : (int) Config::get('sesion.inactividad_min', 60);
        if (time() - (int) ($_SESSION['ultima_actividad'] ?? 0) > $minutos * 60) {
            self::cerrar();
            Session::iniciar();
            flash('aviso', 'Tu sesión se cerró por inactividad. Ingresá nuevamente.');
            return false;
        }
        $_SESSION['ultima_actividad'] = time();
        return true;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    public static function usuario(): ?array
    {
        if (self::$cargado) {
            return self::$usuario;
        }
        self::$cargado = true;
        $id = self::id();
        if (!$id) {
            return self::$usuario = null;
        }
        $u = DB::fila(
            'SELECT u.id, u.nombre, u.usuario, u.email, u.activo, u.caja_id, u.rol_id,
                    r.slug AS rol_slug, r.nombre AS rol_nombre, r.pantalla_inicio,
                    p.id AS preventista_id, c.id AS chofer_id
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             LEFT JOIN preventistas p ON p.usuario_id = u.id AND p.activo = 1
             LEFT JOIN choferes c ON c.usuario_id = u.id AND c.activo = 1
             WHERE u.id = ?',
            [$id]
        );
        if ($u) {
            $u['permisos'] = array_flip(DB::columna(
                'SELECT CONCAT(p.modulo, ".", p.accion) FROM rol_permiso rp JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ?',
                [$u['rol_id']]
            ));
        }
        return self::$usuario = $u;
    }

    /** Fuerza recargar el usuario (ej. tras editar permisos). */
    public static function refrescar(): void
    {
        self::$cargado = false;
    }

    public static function esAdmin(): bool
    {
        return (self::usuario()['rol_slug'] ?? '') === 'admin';
    }

    /**
     * ¿Tiene el permiso? Acepta "modulo.accion" o varios separados por "|" (alcanza con uno).
     */
    public static function puede(string $permiso): bool
    {
        $u = self::usuario();
        if (!$u) {
            return false;
        }
        if ($u['rol_slug'] === 'admin') {
            return true;
        }
        foreach (explode('|', $permiso) as $p) {
            if (isset($u['permisos'][trim($p)])) {
                return true;
            }
        }
        return false;
    }

    public static function exigir(string $permiso): void
    {
        if (!self::puede($permiso)) {
            throw new ErrorNegocio('No tenés permiso para realizar esta acción.');
        }
    }

    public static function pantallaInicio(): string
    {
        $u = self::usuario();
        $p = $u['pantalla_inicio'] ?? '/';
        return $p !== '' ? $p : '/';
    }

    private static function huella(): string
    {
        // Se ata la sesión al navegador (no a la IP, que cambia en datos móviles).
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . Config::get('app.clave', ''));
    }
}
