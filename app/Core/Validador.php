<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validación de datos del lado del servidor.
 * Reglas: requerido, numerico, entero, email, max:N, min:N, cuit, fecha, en:a,b,c, unico:tabla,columna[,idExcluido]
 */
final class Validador
{
    private array $errores = [];

    private const MENSAJES = [
        'requerido' => 'Este campo es obligatorio.',
        'numerico' => 'Ingresá un número válido.',
        'entero' => 'Ingresá un número entero.',
        'email' => 'El correo electrónico no es válido.',
        'cuit' => 'El CUIT no es válido (verificá los 11 dígitos).',
        'fecha' => 'La fecha no es válida.',
        'en' => 'El valor elegido no es válido.',
        'unico' => 'Ya existe un registro con este valor.',
    ];

    public function __construct(private array $datos, array $reglas)
    {
        foreach ($reglas as $campo => $lista) {
            foreach (explode('|', $lista) as $regla) {
                [$nombre, $arg] = array_pad(explode(':', $regla, 2), 2, null);
                $valor = $this->datos[$campo] ?? null;
                $vacio = $valor === null || (is_string($valor) && trim($valor) === '');
                if ($nombre !== 'requerido' && $vacio) {
                    continue;
                }
                $error = $this->evaluar($nombre, $arg, $valor, $vacio);
                if ($error !== null) {
                    $this->errores[$campo] = $error;
                    break;
                }
            }
        }
    }

    public static function make(array $datos, array $reglas): self
    {
        return new self($datos, $reglas);
    }

    private function evaluar(string $nombre, ?string $arg, mixed $valor, bool $vacio): ?string
    {
        switch ($nombre) {
            case 'requerido':
                return $vacio ? self::MENSAJES['requerido'] : null;
            case 'numerico':
                return is_numeric(is_string($valor) ? parse_decimal_str($valor) : $valor) ? null : self::MENSAJES['numerico'];
            case 'entero':
                return filter_var($valor, FILTER_VALIDATE_INT) !== false ? null : self::MENSAJES['entero'];
            case 'email':
                return filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : self::MENSAJES['email'];
            case 'max':
                if (is_numeric($valor) && !is_string($valor)) {
                    return $valor <= (float) $arg ? null : "El valor máximo es $arg.";
                }
                return mb_strlen((string) $valor) <= (int) $arg ? null : "Máximo $arg caracteres.";
            case 'min':
                $n = is_string($valor) ? parse_decimal($valor) : (float) $valor;
                return $n >= (float) $arg ? null : "El valor mínimo es $arg.";
            case 'cuit':
                return self::cuitValido((string) $valor) ? null : self::MENSAJES['cuit'];
            case 'fecha':
                return parse_fecha((string) $valor) !== null ? null : self::MENSAJES['fecha'];
            case 'en':
                return in_array((string) $valor, explode(',', (string) $arg), true) ? null : self::MENSAJES['en'];
            case 'unico':
                [$tabla, $columna, $excluir] = array_pad(explode(',', (string) $arg), 3, null);
                $sql = "SELECT COUNT(*) FROM `$tabla` WHERE `$columna` = ?";
                $p = [$valor];
                if ($excluir) {
                    $sql .= ' AND id <> ?';
                    $p[] = (int) $excluir;
                }
                return (int) DB::valor($sql, $p) > 0 ? self::MENSAJES['unico'] : null;
        }
        return null;
    }

    /** Verifica el dígito verificador de un CUIT/CUIL argentino. */
    public static function cuitValido(string $cuit): bool
    {
        $c = preg_replace('/\D/', '', $cuit);
        if (strlen($c) !== 11) {
            return false;
        }
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $c[$i] * $pesos[$i];
        }
        $dv = 11 - ($suma % 11);
        $dv = $dv === 11 ? 0 : ($dv === 10 ? 9 : $dv);
        return $dv === (int) $c[10];
    }

    public function falla(): bool
    {
        return $this->errores !== [];
    }

    public function errores(): array
    {
        return $this->errores;
    }

    /** Lanza ErrorNegocio con el primer error (para endpoints JSON). */
    public function exigir(array $etiquetas = []): void
    {
        if ($this->falla()) {
            $campo = array_key_first($this->errores);
            $nombre = $etiquetas[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
            throw new ErrorNegocio("$nombre: " . $this->errores[$campo], ['errores' => $this->errores]);
        }
    }
}
