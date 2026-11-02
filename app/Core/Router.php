<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router con parámetros en la ruta ({id}), control de autenticación,
 * permisos por ruta y verificación CSRF en toda petición que modifica datos.
 */
final class Router
{
    private array $rutas = [];

    public function get(string $patron, array $accion, ?string $permiso = null): self
    {
        return $this->agregar('GET', $patron, $accion, $permiso);
    }

    public function post(string $patron, array $accion, ?string $permiso = null): self
    {
        return $this->agregar('POST', $patron, $accion, $permiso);
    }

    /** Ruta accesible sin iniciar sesión. */
    public function publica(string $metodo, string $patron, array $accion): self
    {
        return $this->agregar($metodo, $patron, $accion, '*publico*');
    }

    private function agregar(string $metodo, string $patron, array $accion, ?string $permiso): self
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
        $this->rutas[] = compact('metodo', 'patron', 'regex', 'accion', 'permiso');
        return $this;
    }

    public function despachar(string $metodo, string $ruta): void
    {
        $permitidoOtroMetodo = false;
        foreach ($this->rutas as $r) {
            if (!preg_match($r['regex'], $ruta, $m)) {
                continue;
            }
            if ($r['metodo'] !== $metodo) {
                $permitidoOtroMetodo = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->ejecutar($r, $metodo, $params);
            return;
        }
        if ($permitidoOtroMetodo) {
            Response::error(405, 'Método no permitido');
            return;
        }
        Response::error(404, 'La página que buscás no existe.');
    }

    private function ejecutar(array $r, string $metodo, array $params): void
    {
        $publica = $r['permiso'] === '*publico*';

        if (!$publica) {
            if (!Auth::verificarSesion()) {
                if (Request::esAjax()) {
                    Response::json(['ok' => false, 'error' => 'Tu sesión venció. Volvé a ingresar.', 'sesion' => false], 401);
                    return;
                }
                $_SESSION['volver_a'] = $_SERVER['REQUEST_URI'] ?? null;
                Response::redirigir('/login');
                return;
            }
            if ($r['permiso'] !== null && !Auth::puede($r['permiso'])) {
                if (Request::esAjax()) {
                    Response::json(['ok' => false, 'error' => 'No tenés permiso para esta acción.'], 403);
                    return;
                }
                Response::error(403, 'No tenés permiso para acceder a esta sección.');
                return;
            }
        }

        if ($metodo !== 'GET' && !Csrf::verificar()) {
            if (Request::esAjax()) {
                Response::json(['ok' => false, 'error' => 'El formulario venció. Recargá la página e intentá de nuevo.', 'csrf' => false], 419);
                return;
            }
            flash('error', 'El formulario venció por seguridad. Intentá de nuevo.');
            Response::volver();
            return;
        }

        [$clase, $metodoControlador] = $r['accion'];
        $controlador = new $clase();
        try {
            $controlador->$metodoControlador(...array_values($params));
        } catch (ErrorNegocio $e) {
            if (Request::esAjax()) {
                Response::json(['ok' => false, 'error' => $e->getMessage(), 'detalle' => $e->detalle()], 422);
                return;
            }
            flash('error', $e->getMessage());
            Response::volver();
        }
    }
}
