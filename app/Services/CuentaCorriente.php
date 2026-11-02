<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;

/**
 * Cuenta corriente de clientes o proveedores.
 * - importe > 0: aumenta la deuda (factura, ND, remito); importe < 0: la disminuye (recibo/pago, NC).
 * - "pendiente": parte del renglón aún no imputada. Las imputaciones vinculan créditos con débitos
 *   y permiten calcular la antigüedad de la deuda.
 * - Saldo = SUM(importe). Las anulaciones agregan un contra-asiento.
 */
final class CuentaCorriente
{
    private string $tabla;
    private string $tablaImp;
    private string $columna;

    public function __construct(string $entidad)
    {
        if (!in_array($entidad, ['cliente', 'proveedor'], true)) {
            throw new \InvalidArgumentException('Entidad inválida');
        }
        $this->tabla = $entidad === 'cliente' ? 'cc_clientes' : 'cc_proveedores';
        $this->tablaImp = $this->tabla . '_imputaciones';
        $this->columna = $entidad === 'cliente' ? 'cliente_id' : 'proveedor_id';
    }

    public static function clientes(): self
    {
        return new self('cliente');
    }

    public static function proveedores(): self
    {
        return new self('proveedor');
    }

    /** Registra un movimiento. Devuelve el id. */
    public function registrar(
        int $entidadId,
        string $tipo,
        float $importe,
        string $descripcion,
        ?string $docTipo = null,
        ?int $docId = null,
        ?string $vencimiento = null,
        ?string $fecha = null,
        bool $imputarAutomatico = true
    ): int {
        $importe = round($importe, 2);
        if (abs($importe) < 0.005) {
            throw new ErrorNegocio('El importe del movimiento de cuenta corriente no puede ser cero.');
        }
        return DB::transaccion(function () use ($entidadId, $tipo, $importe, $descripcion, $docTipo, $docId, $vencimiento, $fecha, $imputarAutomatico) {
            $fecha ??= ahora();
            $id = DB::insertar($this->tabla, [
                $this->columna => $entidadId,
                'fecha' => $fecha,
                'vencimiento' => $vencimiento ?? substr($fecha, 0, 10),
                'tipo' => $tipo,
                'documento_tipo' => $docTipo,
                'documento_id' => $docId,
                'descripcion' => mb_substr($descripcion, 0, 150),
                'importe' => $importe,
                'pendiente' => abs($importe),
                'usuario_id' => Auth::id(),
            ]);
            if ($imputarAutomatico) {
                $this->aplicarPendientes($entidadId);
            }
            return $id;
        });
    }

    /**
     * Imputa un crédito a débitos específicos: [debito_id => importe]. Lo que sobre queda pendiente.
     */
    public function imputar(int $creditoId, array $imputaciones): void
    {
        DB::transaccion(function () use ($creditoId, $imputaciones) {
            $cred = DB::fila("SELECT * FROM {$this->tabla} WHERE id = ? FOR UPDATE", [$creditoId]);
            if (!$cred || (float) $cred['importe'] >= 0) {
                throw new ErrorNegocio('Crédito inválido para imputar.');
            }
            $disponible = (float) $cred['pendiente'];
            foreach ($imputaciones as $debitoId => $importe) {
                $importe = round((float) $importe, 2);
                if ($importe <= 0 || $disponible <= 0) {
                    continue;
                }
                $deb = DB::fila("SELECT * FROM {$this->tabla} WHERE id = ? FOR UPDATE", [(int) $debitoId]);
                if (!$deb || (float) $deb['importe'] <= 0 || (int) $deb[$this->columna] !== (int) $cred[$this->columna]) {
                    throw new ErrorNegocio('Comprobante a imputar inválido.');
                }
                $aplicar = min($importe, (float) $deb['pendiente'], $disponible);
                if ($aplicar <= 0) {
                    continue;
                }
                $this->aplicar((int) $deb['id'], $creditoId, $aplicar);
                $disponible = round($disponible - $aplicar, 2);
            }
        });
    }

    /** Aplica créditos pendientes contra débitos pendientes por antigüedad (FIFO). */
    public function aplicarPendientes(int $entidadId): void
    {
        DB::transaccion(function () use ($entidadId) {
            $creditos = DB::filas(
                "SELECT id, pendiente FROM {$this->tabla} WHERE {$this->columna} = ? AND importe < 0 AND pendiente > 0 ORDER BY fecha, id FOR UPDATE",
                [$entidadId]
            );
            if (!$creditos) {
                return;
            }
            $debitos = DB::filas(
                "SELECT id, pendiente FROM {$this->tabla} WHERE {$this->columna} = ? AND importe > 0 AND pendiente > 0 ORDER BY vencimiento, fecha, id FOR UPDATE",
                [$entidadId]
            );
            $d = 0;
            foreach ($creditos as $c) {
                $disp = (float) $c['pendiente'];
                while ($disp > 0.004 && $d < count($debitos)) {
                    $pend = (float) $debitos[$d]['pendiente'];
                    $aplicar = round(min($disp, $pend), 2);
                    $this->aplicar((int) $debitos[$d]['id'], (int) $c['id'], $aplicar);
                    $disp = round($disp - $aplicar, 2);
                    $debitos[$d]['pendiente'] = round($pend - $aplicar, 2);
                    if ($debitos[$d]['pendiente'] <= 0.004) {
                        $d++;
                    }
                }
            }
        });
    }

    private function aplicar(int $debitoId, int $creditoId, float $importe): void
    {
        DB::insertar($this->tablaImp, ['debito_id' => $debitoId, 'credito_id' => $creditoId, 'importe' => $importe, 'fecha' => ahora()]);
        DB::query("UPDATE {$this->tabla} SET pendiente = GREATEST(0, pendiente - ?) WHERE id IN (?, ?)", [$importe, $debitoId, $creditoId]);
    }

    /**
     * Anula los movimientos de un documento: deshace sus imputaciones y agrega el contra-asiento.
     */
    public function anularDocumento(string $docTipo, int $docId, string $descripcion): void
    {
        DB::transaccion(function () use ($docTipo, $docId, $descripcion) {
            $filas = DB::filas(
                "SELECT * FROM {$this->tabla} WHERE documento_tipo = ? AND documento_id = ? AND tipo <> 'anulacion' FOR UPDATE",
                [$docTipo, $docId]
            );
            $entidades = [];
            foreach ($filas as $f) {
                $entidades[(int) $f[$this->columna]] = true;
                // Deshace las imputaciones: devuelve el pendiente a la contraparte
                $imps = DB::filas("SELECT * FROM {$this->tablaImp} WHERE debito_id = ? OR credito_id = ?", [$f['id'], $f['id']]);
                foreach ($imps as $i) {
                    $contra = (int) $i['debito_id'] === (int) $f['id'] ? (int) $i['credito_id'] : (int) $i['debito_id'];
                    DB::query("UPDATE {$this->tabla} SET pendiente = pendiente + ? WHERE id = ?", [$i['importe'], $contra]);
                    DB::query("DELETE FROM {$this->tablaImp} WHERE id = ?", [$i['id']]);
                }
                DB::query("UPDATE {$this->tabla} SET pendiente = 0 WHERE id = ?", [$f['id']]);
                DB::insertar($this->tabla, [
                    $this->columna => $f[$this->columna],
                    'fecha' => ahora(),
                    'vencimiento' => hoy(),
                    'tipo' => 'anulacion',
                    'documento_tipo' => $docTipo,
                    'documento_id' => $docId,
                    'descripcion' => mb_substr($descripcion, 0, 150),
                    'importe' => -(float) $f['importe'],
                    'pendiente' => 0,
                    'usuario_id' => Auth::id(),
                ]);
            }
            foreach (array_keys($entidades) as $ent) {
                $this->aplicarPendientes($ent);
            }
        });
    }

    public function saldo(int $entidadId): float
    {
        return round((float) DB::valor("SELECT COALESCE(SUM(importe), 0) FROM {$this->tabla} WHERE {$this->columna} = ?", [$entidadId]), 2);
    }

    /** Deuda con vencimiento anterior a hoy menos la tolerancia (días). */
    public function deudaVencida(int $entidadId, int $toleranciaDias = 0): float
    {
        $limite = date('Y-m-d', strtotime("-$toleranciaDias days", \App\Core\Reloj::ahora()));
        return round((float) DB::valor(
            "SELECT COALESCE(SUM(pendiente), 0) FROM {$this->tabla} WHERE {$this->columna} = ? AND importe > 0 AND pendiente > 0 AND vencimiento < ?",
            [$entidadId, $limite]
        ), 2);
    }

    /** Débitos con saldo pendiente (para elegir qué cancelar en un recibo/pago). */
    public function pendientes(int $entidadId): array
    {
        return DB::filas(
            "SELECT id, fecha, vencimiento, descripcion, importe, pendiente, documento_tipo, documento_id,
                    DATEDIFF(CURDATE(), vencimiento) AS dias_vencido
             FROM {$this->tabla} WHERE {$this->columna} = ? AND importe > 0 AND pendiente > 0 ORDER BY vencimiento, fecha, id",
            [$entidadId]
        );
    }

    /** Crédito a favor no imputado. */
    public function creditoDisponible(int $entidadId): float
    {
        return round((float) DB::valor(
            "SELECT COALESCE(SUM(pendiente), 0) FROM {$this->tabla} WHERE {$this->columna} = ? AND importe < 0 AND pendiente > 0",
            [$entidadId]
        ), 2);
    }

    /** Antigüedad de la deuda: [a_vencer, 1-30, 31-60, 61-90, mas_90] */
    public function antiguedad(int $entidadId): array
    {
        $f = DB::fila(
            "SELECT
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), vencimiento) <= 0 THEN pendiente END), 0) AS a_vencer,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), vencimiento) BETWEEN 1 AND 30 THEN pendiente END), 0) AS d30,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), vencimiento) BETWEEN 31 AND 60 THEN pendiente END), 0) AS d60,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), vencimiento) BETWEEN 61 AND 90 THEN pendiente END), 0) AS d90,
                COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), vencimiento) > 90 THEN pendiente END), 0) AS mas90
             FROM {$this->tabla} WHERE {$this->columna} = ? AND importe > 0 AND pendiente > 0",
            [$entidadId]
        );
        return array_map('floatval', $f ?: []);
    }

    /** Movimientos de un período con saldo acumulado (incluye saldo anterior). */
    public function movimientos(int $entidadId, string $desde, string $hasta): array
    {
        $anterior = (float) DB::valor(
            "SELECT COALESCE(SUM(importe), 0) FROM {$this->tabla} WHERE {$this->columna} = ? AND fecha < ?",
            [$entidadId, $desde . ' 00:00:00']
        );
        $filas = DB::filas(
            "SELECT * FROM {$this->tabla} WHERE {$this->columna} = ? AND fecha BETWEEN ? AND ? ORDER BY fecha, id",
            [$entidadId, $desde . ' 00:00:00', $hasta . ' 23:59:59']
        );
        $saldo = $anterior;
        foreach ($filas as &$f) {
            $saldo += (float) $f['importe'];
            $f['saldo'] = round($saldo, 2);
        }
        return ['anterior' => round($anterior, 2), 'filas' => $filas, 'saldo' => round($saldo, 2)];
    }
}
