<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

final class AuthController extends Controller
{
    public function formulario(): void
    {
        if (Auth::id() && Auth::verificarSesion()) {
            $this->redirigir(Auth::pantallaInicio());
        }
        $this->vista('auth/login', ['titulo' => 'Ingresar'], 'layouts/simple');
    }

    public function ingresar(): void
    {
        $usuario = (string) Request::texto('usuario', '');
        $clave = (string) Request::input('clave', '');
        if ($usuario === '' || $clave === '') {
            flash('error', 'Ingresá usuario y contraseña.');
            $this->vista('auth/login', ['titulo' => 'Ingresar', 'usuario' => $usuario], 'layouts/simple');
            return;
        }
        $error = Auth::intentar($usuario, $clave);
        if ($error !== null) {
            flash('error', $error);
            $this->vista('auth/login', ['titulo' => 'Ingresar', 'usuario' => $usuario], 'layouts/simple');
            return;
        }
        $destino = $_SESSION['volver_a'] ?? null;
        unset($_SESSION['volver_a']);
        $base = base_path();
        if ($destino && is_string($destino) && str_starts_with($destino, $base . '/') && !str_contains($destino, '/api/') && !str_contains($destino, '/login')) {
            header('Location: ' . $destino);
            exit;
        }
        $this->redirigir(Auth::pantallaInicio());
    }

    public function salir(): void
    {
        Auth::cerrar();
        \App\Core\Session::iniciar();
        flash('exito', 'Cerraste la sesión.');
        $this->redirigir('/login');
    }

    public function perfil(): void
    {
        $this->vista('auth/perfil', ['titulo' => 'Mi contraseña']);
    }

    public function cambiarClave(): void
    {
        $actual = (string) Request::input('actual', '');
        $nueva = (string) Request::input('nueva', '');
        $repetir = (string) Request::input('repetir', '');
        $hash = DB::valor('SELECT password_hash FROM usuarios WHERE id = ?', [Auth::id()]);
        if (!password_verify($actual, (string) $hash)) {
            $this->volver('La contraseña actual no es correcta.', 'error');
        }
        if (mb_strlen($nueva) < 8) {
            $this->volver('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
        }
        if ($nueva !== $repetir) {
            $this->volver('Las contraseñas nuevas no coinciden.', 'error');
        }
        DB::actualizar('usuarios', ['password_hash' => password_hash($nueva, PASSWORD_DEFAULT), 'actualizado_en' => ahora()], 'id = :id', ['id' => Auth::id()]);
        Auditoria::registrar('usuarios', 'cambiar_clave', 'usuario', Auth::id(), null, null, 'Cambio de contraseña propia');
        $this->volver('Contraseña actualizada.');
    }

    /** Manifest de la PWA (con la ruta base correcta). */
    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $base = base_path();
        echo json_encode([
            'name' => 'Distribuidora Toro',
            'short_name' => 'Toro',
            'description' => 'Gestión de Distribuidora Toro: pedidos, reparto, stock y cobranzas',
            'lang' => 'es-AR',
            'start_url' => $base . '/?app=1',
            'scope' => $base . '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#9e1b1b',
            'theme_color' => '#9e1b1b',
            'icons' => [
                ['src' => $base . '/assets/img/icono-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $base . '/assets/img/icono-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $base . '/assets/img/icono-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Mi ruta de hoy', 'url' => $base . '/movil', 'icons' => [['src' => $base . '/assets/img/icono-192.png', 'sizes' => '192x192']]],
                ['name' => 'Mis entregas', 'url' => $base . '/chofer', 'icons' => [['src' => $base . '/assets/img/icono-192.png', 'sizes' => '192x192']]],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
