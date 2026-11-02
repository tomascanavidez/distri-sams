<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Liquidación N° <?= (int) $l['id'] ?> <?= estado_badge($l['estado']) ?></h1><div class="acciones"><?= Html::exportar('/preventistas/comisiones/' . $l['id']) ?></div></div>
<div class="card shadow-sm mb-3"><div class="card-body ficha-dato row g-2">
    <div class="col-6 col-md-3"><small>Preventista</small><?= e($l['preventista']) ?></div><div class="col-6 col-md-3"><small>Período</small><?= fecha($l['desde']) ?> al <?= fecha($l['hasta']) ?></div>
    <div class="col-6 col-md-2"><small>Base (<?= e($l['base_calculo']) ?>)</small><?= money($l['importe_base']) ?></div><div class="col-6 col-md-2"><small>Porcentaje</small><?= num($l['porcentaje']) ?> %</div>
    <div class="col-12 col-md-2"><small>Comisión</small><strong class="fs-5"><?= money($l['importe']) ?></strong></div>
</div></div>
<div class="card shadow-sm"><div class="card-body"><table class="table table-sm tabla-cards mb-0"><thead><tr><th>Fecha</th><th>Comprobante</th><th>Cliente</th><th class="num">Base</th></tr></thead><tbody>
<?php foreach ($docs as $d): ?><tr><td data-label="Fecha"><?= fecha($d['fecha']) ?></td><td data-label="Comprobante"><?= e($d['numero']) ?></td><td class="td-titulo"><?= e($d['nombre_comercio']) ?></td><td class="num" data-label="Base"><?= money($d['base']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
