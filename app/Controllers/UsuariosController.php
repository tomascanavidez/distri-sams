<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Request;
use App\Core\Validador;
use App\Support\Permisos;

final class UsuariosController extends Controller
{
    public function index(): void
    {
        $usuarios = DB::filas('SELECT u.*, r.nombre AS rol, c.nombre AS caja, p.nombre AS preventista, ch.nombre AS chofer FROM usuarios u JOIN roles r ON r.id = u.rol_id
                               LEFT JOIN cajas c ON c.id = u.caja_id LEFT JOIN preventistas p ON p.usuario_id = u.id LEFT JOIN choferes ch ON ch.usuario_id = u.id ORDER BY u.activo DESC, u.nombre');
        $this->vista('usuarios/index', ['titulo' => 'Usuarios', 'usuarios' => $usuarios]);
    }

    private function formulario(array $datos, array $errores, ?int $id): void
    {
        $this->vista('usuarios/form', [
            'titulo' => $id ? 'Editar usuario' : 'Nuevo usuario', 'datos' => $datos, 'errores' => $errores, 'id' => $id,
            'roles' => DB::pares('SELECT id, nombre FROM roles ORDER BY id'), 'cajas' => DB::pares('SELECT id, nombre FROM cajas WHERE activo = 1'),
        ]);
    }

    public function nuevo(): void
    {
        $this->formulario(['activo' => 1, 'caja_id' => 1], [], null);
    }

    public function editar(string $id): void
    {
        $this->formulario($this->buscarOFallar('usuarios', (int) $id), [], (int) $id);
    }

    public function guardar(?string $id = null): void
    {
        $id = $id !== null ? (int) $id : null;
        $antes = $id ? $this->buscarOFallar('usuarios', $id) : null;
        $d = [
            'nombre' => Request::texto('nombre'), 'usuario' => mb_strtolower((string) Request::texto('usuario')), 'email' => Request::texto('email'),
            'rol_id' => Request::entero('rol_id'), 'caja_id' => Request::entero('caja_id') ?: null, 'activo' => Request::entero('activo', 1),
        ];
        $clave = (string) Request::input('clave', '');
        $v = Validador::make($d + ['clave' => $clave], [
            'nombre' => 'requerido|max:100', 'usuario' => 'requerido|max:50|unico:usuarios,usuario' . ($id ? ",$id" : ''), 'email' => 'email|max:120', 'rol_id' => 'requerido',
        ] + ($id ? [] : ['clave' => 'requerido']));
        $errores = $v->errores();
        if ($d['usuario'] && !preg_match('/^[a-z0-9._-]{3,50}$/', $d['usuario'])) {
            $errores['usuario'] = 'Usá entre 3 y 50 letras minúsculas, números, punto, guion o guion bajo.';
        }
        if ($clave !== '' && mb_strlen($clave) < 8) {
            $errores['clave'] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($id === Auth::id() && (int) $d['activo'] === 0) {
            $errores['activo'] = 'No podés desactivar tu propio usuario.';
        }
        if ($id === Auth::id() && $antes && (int) $antes['rol_id'] !== (int) $d['rol_id'] && Auth::esAdmin() && !$this->otroAdmin($id)) {
            $errores['rol_id'] = 'Sos el único administrador activo: no podés quitarte el rol.';
        }
        if ($errores) {
            $this->formulario(array_merge($antes ?? [], $d), $errores, $id);
            return;
        }
        if ($clave !== '') {
            $d['password_hash'] = password_hash($clave, PASSWORD_DEFAULT);
        }
        if ($id) {
            DB::actualizar('usuarios', $d + ['actualizado_en' => ahora()], 'id = :id', ['id' => $id]);
            Auditoria::registrar('usuarios', 'editar', 'usuario', $id, $antes, $d + ($clave !== '' ? ['clave' => '(cambiada)'] : []), 'Edición del usuario ' . $d['usuario']);
        } else {
            $id = DB::insertar('usuarios', $d + ['creado_en' => ahora()]);
            Auditoria::registrar('usuarios', 'crear', 'usuario', $id, null, $d, 'Alta del usuario ' . $d['usuario']);
        }
        $this->redirigir('/usuarios', 'Usuario guardado.');
    }

    private function otroAdmin(int $excepto): bool
    {
        return (bool) DB::valor("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.slug = 'admin' AND u.activo = 1 AND u.id <> ?", [$excepto]);
    }

    public function alternarEstado(string $id): void
    {
        $u = $this->buscarOFallar('usuarios', (int) $id);
        if ((int) $id === Auth::id()) {
            throw new ErrorNegocio('No podés desactivar tu propio usuario.');
        }
        $nuevo = (int) $u['activo'] ? 0 : 1;
        DB::actualizar('usuarios', ['activo' => $nuevo, 'actualizado_en' => ahora()], 'id = :id', ['id' => (int) $id]);
        Auditoria::registrar('usuarios', $nuevo ? 'activar' : 'desactivar', 'usuario', (int) $id, ['activo' => $u['activo']], ['activo' => $nuevo]);
        $this->volver($nuevo ? 'Usuario activado.' : 'Usuario desactivado: ya no puede ingresar.');
    }

    public function roles(): void
    {
        $roles = DB::filas('SELECT r.*, (SELECT COUNT(*) FROM usuarios WHERE rol_id = r.id) AS usuarios FROM roles r ORDER BY r.id');
        $rolId = (int) \App\Core\Request::get('rol_id', $roles[1]['id'] ?? $roles[0]['id']);
        $asignados = array_flip(DB::columna('SELECT CONCAT(p.modulo, ".", p.accion) FROM rol_permiso rp JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ?', [$rolId]));
        $this->vista('usuarios/roles', ['titulo' => 'Roles y permisos', 'roles' => $roles, 'rolId' => $rolId, 'asignados' => $asignados]);
    }

    public function guardarRoles(): void
    {
        $rolId = Request::entero('rol_id');
        $rol = $this->buscarOFallar('roles', $rolId);
        if ($rol['slug'] === 'admin') {
            throw new ErrorNegocio('El rol Administrador tiene siempre todos los permisos.');
        }
        Permisos::sincronizar();
        $permisos = DB::pares('SELECT CONCAT(modulo, ".", accion), id FROM permisos');
        $elegidos = array_values(array_intersect_key($permisos, array_flip((array) Request::input('permisos', []))));
        $antes = DB::columna('SELECT CONCAT(p.modulo, ".", p.accion) FROM rol_permiso rp JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ?', [$rolId]);
        DB::transaccion(function () use ($rolId, $elegidos, $rol) {
            DB::query('DELETE FROM rol_permiso WHERE rol_id = ?', [$rolId]);
            foreach ($elegidos as $pid) {
                DB::query('INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)', [$rolId, $pid]);
            }
            $inicio = Request::texto('pantalla_inicio');
            if ($inicio && str_starts_with($inicio, '/')) {
                DB::actualizar('roles', ['pantalla_inicio' => $inicio], 'id = :id', ['id' => $rolId]);
            }
        });
        Auditoria::registrar('usuarios', 'permisos', 'rol', $rolId, ['permisos' => implode(', ', $antes)], ['permisos' => implode(', ', (array) Request::input('permisos', []))], 'Permisos del rol ' . $rol['nombre']);
        Auth::refrescar();
        $this->redirigir('/usuarios/roles?rol_id=' . $rolId, 'Permisos del rol "' . $rol['nombre'] . '" guardados.');
    }

    public function crearRol(): void
    {
        $nombre = Request::texto('nombre');
        if (!$nombre) {
            throw new ErrorNegocio('Indicá el nombre del rol.');
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $nombre))), '_') ?: 'rol';
        while (DB::valor('SELECT id FROM roles WHERE slug = ?', [$slug])) {
            $slug .= '_' . random_int(1, 99);
        }
        $id = DB::insertar('roles', ['nombre' => mb_substr($nombre, 0, 60), 'slug' => substr($slug, 0, 40), 'descripcion' => Request::texto('descripcion'), 'pantalla_inicio' => '/', 'es_sistema' => 0]);
        if ($base = Request::entero('copiar_de')) {
            DB::query('INSERT INTO rol_permiso (rol_id, permiso_id) SELECT ?, permiso_id FROM rol_permiso WHERE rol_id = ?', [$id, $base]);
        }
        Auditoria::registrar('usuarios', 'crear_rol', 'rol', $id, null, ['nombre' => $nombre]);
        $this->redirigir('/usuarios/roles?rol_id=' . $id, 'Rol creado.');
    }
}
