<?php use App\Support\Exportador; $emp = Exportador::empresa(); ?>
<div class="hoja">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
        <div>
            <h1 class="h5 mb-0"><?= e($emp['nombre']) ?></h1>
            <small><?= e($emp['razon']) ?> · CUIT <?= e($emp['cuit']) ?></small>
        </div>
        <div class="text-end">
            <h2 class="h5 mb-0"><?= e($titulo) ?></h2>
            <small><?= e($subtitulo) ?> · Emitido <?= date('d/m/Y H:i') ?></small>
        </div>
    </div>
    <table class="table table-sm tabla-impresion">
        <?php $cols = ($grupo ?? null) ? array_diff_key($columnas, [$grupo => 1]) : $columnas; ?>
        <thead><tr><?php foreach ($cols as [$etq, $tipo]): ?><th class="<?= in_array($tipo, ['money', 'num', 'cant', 'entero', 'porc'], true) ? 'text-end' : '' ?>"><?= e($etq) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php $grupo = $grupo ?? null; $cols = $grupo ? array_diff_key($columnas, [$grupo => 1]) : $columnas; $actual = null; ?>
        <?php foreach ($filas as $f): ?>
            <?php if ($grupo && ($f[$grupo] ?? '') !== $actual): $actual = $f[$grupo] ?? ''; ?>
                <tr class="fila-grupo"><th colspan="<?= count($cols) ?>"><?= e($actual) ?></th></tr>
            <?php endif; ?>
            <tr><?php foreach ($cols as $k => [, $tipo]): ?><td class="<?= in_array($tipo, ['money', 'num', 'cant', 'entero', 'porc'], true) ? 'text-end text-nowrap' : '' ?>"><?= e(Exportador::formatear($f[$k] ?? '', $tipo)) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if (!$filas): ?><tr><td colspan="<?= count($columnas) ?>" class="text-center">Sin datos para los filtros elegidos.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($totales): ?>
            <tfoot><tr class="fw-bold"><?php foreach ($columnas as $k => [, $tipo]): ?><td class="<?= in_array($tipo, ['money', 'num', 'cant', 'entero', 'porc'], true) ? 'text-end text-nowrap' : '' ?>"><?= e(Exportador::formatear($totales[$k] ?? '', $tipo)) ?></td><?php endforeach; ?></tr></tfoot>
        <?php endif; ?>
    </table>
</div>
