<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Error de regla de negocio: su mensaje se muestra tal cual al usuario
 * (ej.: "Stock insuficiente", "El cliente supera su límite de crédito").
 */
class ErrorNegocio extends RuntimeException
{
    public function __construct(string $mensaje, private array $detalle = [])
    {
        parent::__construct($mensaje);
    }

    public function detalle(): array
    {
        return $this->detalle;
    }
}
