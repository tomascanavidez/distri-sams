<?php use App\Services\StockService; ?>
<div class="encabezado"><h1>Ajustes de stock</h1><div class="acciones"><?php if (puede('stock.crear')): ?><a href="<?= url('/stock/ajustes/nuevo') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nuevo ajuste</a><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Fecha</th><th>Depósito</th><th>Motivo</th><th>Observaciones</th><th class="num">Ítems</th><th class="num">Valor a costo</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $a): ?>
        <tr><td data-label="N°"><a href="<?= url('/stock/ajustes/' . $a['id']) ?>"><?= (int) $a['id'] ?></a></td><td data-label="Fecha"><?= fecha_hora($a['fecha']) ?></td><td data-label="Depósito"><?= e($a['deposito']) ?></td>
            <td class="td-titulo"><?= e(StockService::MOTIVOS_AJUSTE[$a['motivo']] ?? $a['motivo']) ?></td><td data-label="Obs."><small><?= e($a['observaciones']) ?></small></td>
            <td class="num" data-label="Ítems"><?= (int) $a['items'] ?></td><td class="num <?= $a['valor'] < 0 ? 'text-danger' : '' ?>" data-label="Valor"><?= money($a['valor']) ?></td><td data-label="Estado"><?= estado_badge($a['estado']) ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/stock/ajustes/' . $a['id']) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
