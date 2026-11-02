<div class="encabezado"><h1>Reportes</h1></div>
<div class="row g-3">
<?php $iconos = ['Ventas' => 'graph-up', 'Compras' => 'bag', 'Stock' => 'boxes', 'Cuentas corrientes' => 'journal-text', 'Impuestos' => 'bank2', 'Reparto' => 'truck', 'Finanzas' => 'cash-stack'];
foreach ($grupos as $grupo => $reportes): ?>
    <div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="card-titulo"><i class="bi bi-<?= $iconos[$grupo] ?? 'file-earmark-bar-graph' ?> text-primary"></i> <?= e($grupo) ?></div>
        <div class="list-group list-group-flush">
            <?php foreach ($reportes as $clave => [$t, $d]): ?>
                <a class="list-group-item list-group-item-action px-0 py-2" href="<?= url('/reportes/' . $clave) ?>"><strong><?= e($t) ?></strong><br><small class="text-body-secondary"><?= e($d) ?></small></a>
            <?php endforeach; ?>
        </div>
    </div></div></div>
<?php endforeach; ?>
</div>
