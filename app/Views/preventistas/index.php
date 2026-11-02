<div class="encabezado"><h1>Preventistas</h1><div class="acciones"><?php if (puede('preventistas.crear')): ?><a href="<?= url('/preventistas/nuevo') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nuevo preventista</a><?php endif; ?></div></div>
<div class="row g-3">
<?php foreach ($filas as $p): $efect = $p['visitas_mes'] ? round($p['efectivas_mes'] * 100 / $p['visitas_mes']) : 0; ?>
    <div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><div><div class="card-titulo mb-0"><?= e($p['nombre']) ?></div><small class="text-body-secondary">Usuario: <?= e($p['usuario'] ?? 'sin usuario') ?> · <?= e($p['telefono']) ?></small></div><?= estado_badge((int) $p['activo'] ? 'activo' : 'inactivo') ?></div>
        <div class="small mt-2">Zonas: <strong><?= e($p['zonas'] ?: '—') ?></strong> · <?= (int) $p['clientes'] ?> clientes</div>
        <div class="small">Comisión: <strong><?= num($p['comision_porc'], 2) ?> %</strong> sobre <?= $p['comision_base'] === 'cobranzas' ? 'cobranzas' : 'ventas netas' ?></div>
        <div class="row text-center mt-2 g-2">
            <div class="col-4"><div class="kpi-etiqueta">Ventas mes</div><div class="fw-bold"><?= money($p['ventas_mes']) ?></div></div>
            <div class="col-4"><div class="kpi-etiqueta">Visitas</div><div class="fw-bold"><?= (int) $p['visitas_mes'] ?></div></div>
            <div class="col-4"><div class="kpi-etiqueta">Efectividad</div><div class="fw-bold"><?= $efect ?> %</div></div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <?php if (puede('preventistas.editar')): ?><a href="<?= url('/preventistas/' . $p['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary btn-touch"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
            <a href="<?= url('/preventistas/visitas?preventista_id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary btn-touch">Visitas</a>
            <a href="<?= url('/preventistas/comisiones?preventista_id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary btn-touch">Comisiones</a>
        </div>
    </div></div></div>
<?php endforeach; ?>
</div>
