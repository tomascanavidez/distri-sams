<?php use App\Services\StockService; use App\Support\Html; ?>
<div class="encabezado"><h1>Ajuste N° <?= (int) $a['id'] ?> <?= estado_badge($a['estado']) ?></h1>
    <div class="acciones"><?php if ($a['estado'] === 'aplicado' && puede('stock.anular')): ?><?= Html::botonPost('/stock/ajustes/' . $a['id'] . '/anular', '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', '¿Anular el ajuste? Se revierten los movimientos.', true) ?><?php endif; ?></div></div>
<div class="card shadow-sm mb-3"><div class="card-body ficha-dato row g-2">
    <div class="col-6 col-md-3"><small>Fecha</small><?= fecha_hora($a['fecha']) ?></div><div class="col-6 col-md-3"><small>Depósito</small><?= e($a['deposito']) ?></div>
    <div class="col-6 col-md-3"><small>Motivo</small><?= e(StockService::MOTIVOS_AJUSTE[$a['motivo']] ?? $a['motivo']) ?></div><div class="col-6 col-md-3"><small>Usuario</small><?= e($a['usuario']) ?></div>
    <?php if ($a['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($a['observaciones']) ?></div><?php endif; ?>
    <?php if ($a['motivo_anulacion']): ?><div class="col-12"><small>Motivo de anulación</small><?= e($a['motivo_anulacion']) ?></div><?php endif; ?>
</div></div>
<div class="card shadow-sm"><div class="card-body"><table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>Producto</th><th class="num">Cantidad</th><th>Lote</th><th class="num">Costo</th><th class="num">Valor</th></tr></thead>
    <tbody><?php foreach ($items as $it): ?><tr><td class="td-titulo"><?= e($it['descripcion']) ?></td><td class="num fw-bold <?= $it['cantidad'] < 0 ? 'text-danger' : 'text-success' ?>" data-label="Cantidad"><?= ($it['cantidad'] > 0 ? '+' : '') . cant($it['cantidad']) ?> <?= e($it['unidad_base']) ?></td>
        <td data-label="Lote"><?= e($it['lote']) ?> <?= fecha($it['vencimiento']) ?></td><td class="num" data-label="Costo"><?= money($it['costo_unitario']) ?></td><td class="num" data-label="Valor"><?= money((float) $it['cantidad'] * (float) $it['costo_unitario']) ?></td></tr><?php endforeach; ?></tbody>
</table></div></div>
