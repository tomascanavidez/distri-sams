<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Validador;

/** Validaciones, CSRF, bloqueo por intentos, permisos y auditoría. */
final class SeguridadTest extends Prueba
{
    public function testCuit(): void
    {
        $this->verdadero(Validador::cuitValido('30-71234567-1'), 'CUIT válido');
        $this->verdadero(!Validador::cuitValido('30-71234567-9'), 'dígito verificador incorrecto');
        $this->verdadero(!Validador::cuitValido('123'), 'largo inválido');
    }

    public function testValidador(): void
    {
        $v = Validador::make(['nombre' => '', 'email' => 'x@', 'n' => 'abc'], ['nombre' => 'requerido', 'email' => 'email', 'n' => 'numerico']);
        $this->igual(['nombre', 'email', 'n'], array_keys($v->errores()));
    }

    public function testCsrf(): void
    {
        $_POST = [];
        $t = Csrf::token();
        $this->verdadero(!Csrf::verificar(), 'sin token se rechaza');
        $_POST['_token'] = 'falso';
        $this->verdadero(!Csrf::verificar(), 'token incorrecto se rechaza');
        $_POST['_token'] = $t;
        $this->verdadero(Csrf::verificar(), 'token correcto');
        $_POST = [];
    }

    public function testBloqueoPorIntentosFallidos(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.' . random_int(2, 250);
        $id = DB::insertar('usuarios', ['nombre' => 'Víctima', 'usuario' => 'victima', 'password_hash' => password_hash('correcta123', PASSWORD_DEFAULT), 'rol_id' => 2, 'activo' => 1, 'creado_en' => ahora()]);
        for ($i = 0; $i < 5; $i++) {
            $this->verdadero(Auth::intentar('victima', 'mala') !== null, 'clave incorrecta');
        }
        $msg = Auth::intentar('victima', 'correcta123');
        $this->verdadero($msg !== null && str_contains($msg, 'Demasiados intentos'), 'queda bloqueado aunque la clave sea correcta');
        DB::query('DELETE FROM login_intentos WHERE usuario = ?', ['victima']);
        $this->igual(null, Auth::intentar('victima', 'correcta123'), 'pasado el bloqueo puede ingresar');
        $this->igual($id, Auth::id());
        $this->usuario('admin');
    }

    public function testPermisosPorRol(): void
    {
        $rol = (int) DB::valor("SELECT id FROM roles WHERE slug = 'contador'");
        DB::insertar('usuarios', ['nombre' => 'Contadora', 'usuario' => 'conta_prueba', 'password_hash' => 'x', 'rol_id' => $rol, 'activo' => 1, 'creado_en' => ahora()]);
        $this->usuario('conta_prueba');
        $this->verdadero(Auth::puede('reportes.ver'), 'el contador ve reportes');
        $this->verdadero(!Auth::puede('ventas.crear'), 'pero no vende');
        $this->verdadero(!Auth::puede('usuarios.editar'), 'ni edita usuarios');
        $this->usuario('admin');
        $this->verdadero(Auth::puede('usuarios.editar'), 'el administrador puede todo');
    }

    public function testEscapadoHtml(): void
    {
        $this->igual('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', e("<script>alert('x')</script>"));
    }

    public function testAuditoriaRegistraCambios(): void
    {
        \App\Core\Auditoria::registrar('pruebas', 'editar', 'cosa', 1, ['precio' => 10, 'nombre' => 'A'], ['precio' => 12, 'nombre' => 'A']);
        $a = DB::fila("SELECT * FROM auditoria WHERE modulo = 'pruebas' ORDER BY id DESC LIMIT 1");
        $this->igual(['precio' => 10], json_decode($a['datos_anteriores'], true), 'sólo guarda lo que cambió');
        $this->igual(['precio' => 12], json_decode($a['datos_nuevos'], true));
    }
}
