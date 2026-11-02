<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controlador base.
 */
abstract class Controller
{
    protected function vista(string $plantilla, array $datos = [], ?string $layout = 'layouts/app'): void
    {
        echo View::render($plantilla, $datos, $layout);
    }

    protected function json(mixed $datos, int $codigo = 200): void
    {
        Response::json($datos, $codigo);
    }

    protected function redirigir(string $ruta, ?string $exito = null): void
    {
        if ($exito !== null) {
            flash('exito', $exito);
        }
        Response::redirigir($ruta);
    }

    protected function volver(?string $mensaje = null, string $tipo = 'exito'): void
    {
        if ($mensaje !== null) {
            flash($tipo, $mensaje);
        }
        Response::volver();
    }

    /** Obtiene una fila por id o muestra 404. */
    protected function buscarOFallar(string $tabla, int $id): array
    {
        $fila = DB::fila("SELECT * FROM `$tabla` WHERE id = ?", [$id]);
        if (!$fila) {
            Response::error(404, 'El registro solicitado no existe.');
            exit;
        }
        return $fila;
    }

    /** Motivo obligatorio para anulaciones. */
    protected function motivoAnulacion(): string
    {
        $motivo = Request::texto('motivo');
        if ($motivo === null || mb_strlen($motivo) < 3) {
            throw new ErrorNegocio('Indicá el motivo de la anulación.');
        }
        return mb_substr($motivo, 0, 255);
    }
}
