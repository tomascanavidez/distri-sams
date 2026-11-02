<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Transferencia N° <?= (int) $t['id'] ?> <?= estado_badge($t['estado']) ?></h1>
    <div class="acciones"><?php if ($t['estado'] === 'aplicada' && !$t['hoja_ruta_id'] && puede('stock.anular')): ?><?= Html::botonPost('/stock/transferencias/' . $t['id'] . '/anular', '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', '¿Anular la transferencia?', true) ?><?php endif; ?></div></div>
<div class="card shadow-sm mb-3"><div class="card-body ficha-dato row g-2">
    <div class="col-6 col-md-3"><small>Fecha</small><?= fecha_hora($t['fecha']) ?></div><div class="col-6 col-md-3"><small>Origen</small><?= e($t['origen']) ?></div>
    <div class="col-6 col-md-3"><small>Destino</small><?= e($t['destino']) ?></div><div class="col-6 col-md-3"><small>Usuario</small><?= e($t['usuario']) ?></div>
    <?php if ($t['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($t['observaciones']) ?></div><?php endif; ?>
</div></div>
<div class="card shadow-sm"><div class="card-body"><table class="table tabla-cards mb-0"><thead><tr><th>Producto</th><th class="num">Cantidad</th></tr></thead>
    <tbody><?php foreach ($items as $it): ?><tr><td class="td-titulo"><?= e($it['descripcion']) ?></td><td class="num" data-label="Cantidad"><?= cant($it['cantidad']) ?> <?= e($it['unidad_base']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
