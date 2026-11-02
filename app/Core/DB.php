<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Acceso a la base de datos. Únicamente PDO con sentencias preparadas.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $nivelTransaccion = 0;

    public static function conectar(?array $cfg = null): PDO
    {
        $cfg ??= Config::get('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            (int) ($cfg['puerto'] ?? 3306),
            $cfg['nombre']
        );
        $pdo = new PDO($dsn, $cfg['usuario'], $cfg['clave'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // Argentina no usa horario de verano: UTC-3 fijo.
        $pdo->exec("SET time_zone = '-03:00', NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::conectar();
        }
        return self::$pdo;
    }

    /** Permite inyectar una conexión (instalador, pruebas). */
    public static function usar(PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$nivelTransaccion = 0;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        foreach ($params as $clave => $valor) {
            $nombre = is_int($clave) ? $clave + 1 : (str_starts_with($clave, ':') ? $clave : ':' . $clave);
            if (is_bool($valor)) {
                $valor = (int) $valor;
            }
            $tipo = match (true) {
                is_int($valor) => PDO::PARAM_INT,
                $valor === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($nombre, is_float($valor) ? (string) $valor : $valor, $tipo);
        }
        $st->execute();
        return $st;
    }

    public static function fila(string $sql, array $params = []): ?array
    {
        $fila = self::query($sql, $params)->fetch();
        return $fila === false ? null : $fila;
    }

    public static function filas(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function valor(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Devuelve [clave => valor] usando las dos primeras columnas. */
    public static function pares(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function columna(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function insertar(string $tabla, array $datos): int
    {
        $cols = array_keys($datos);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $tabla,
            implode('`,`', $cols),
            implode(',', array_map(fn ($c) => ':' . $c, $cols))
        );
        self::query($sql, $datos);
        return (int) self::pdo()->lastInsertId();
    }

    public static function actualizar(string $tabla, array $datos, string $where, array $params = []): int
    {
        $sets = [];
        $valores = [];
        foreach ($datos as $col => $valor) {
            $sets[] = "`$col` = :set_$col";
            $valores['set_' . $col] = $valor;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $tabla, implode(', ', $sets), $where);
        return self::query($sql, array_merge($valores, $params))->rowCount();
    }

    /**
     * Ejecuta el callback dentro de una transacción. Soporta anidamiento
     * (sólo la transacción externa hace commit/rollback real).
     */
    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$nivelTransaccion === 0) {
            $pdo->beginTransaction();
        }
        self::$nivelTransaccion++;
        try {
            $resultado = $fn();
            self::$nivelTransaccion--;
            if (self::$nivelTransaccion === 0) {
                $pdo->commit();
            }
            return $resultado;
        } catch (Throwable $e) {
            self::$nivelTransaccion--;
            if (self::$nivelTransaccion === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function enTransaccion(): bool
    {
        return self::$nivelTransaccion > 0;
    }

    /** Genera "?,?,?" para cláusulas IN. */
    public static function marcadores(array $valores): string
    {
        return implode(',', array_fill(0, max(1, count($valores)), '?'));
    }
}
