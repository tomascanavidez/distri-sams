<div class="encabezado"><h1>Inventario físico</h1></div>
<?php if (puede('stock.crear')): ?>
<div class="card shadow-sm mb-3"><div class="card-body">
    <div class="card-titulo">Iniciar una toma de inventario</div>
    <form method="post" action="<?= url('/stock/inventarios/nuevo') ?>" class="row g-2 align-items-end">
        <?= csrf_campo() ?>
        <div class="col-md-3"><label class="form-label">Depósito</label><select class="form-select" name="deposito_id"><?= opciones($depositos, 1) ?></select></div>
        <div class="col-md-3"><label class="form-label">Rubro (opcional)</label><select class="form-select" name="rubro_id"><?= opciones($rubros, '', 'Todos los productos') ?></select></div>
        <div class="col-md-4"><label class="form-label">Descripción</label><input class="form-control" name="descripcion" placeholder="Ej.: Inventario mensual octubre"></div>
        <div class="col-md-2"><button class="btn btn-primary btn-touch w-100"><i class="bi bi-clipboard-check"></i> Generar planilla</button></div>
    </form>
    <p class="small text-body-secondary mb-0 mt-2">Se congela el stock del sistema en ese momento. Contá la mercadería, cargá las cantidades y aplicá: las diferencias generan un ajuste de stock.</p>
</div></div>
<?php endif; ?>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><th>N°</th><th>Fecha</th><th>Depósito</th><th>Rubro</th><th>Descripción</th><th class="num">Contados</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $i): ?>
        <tr><td data-label="N°"><?= (int) $i['id'] ?></td><td data-label="Fecha"><?= fecha_hora($i['fecha']) ?></td><td data-label="Depósito"><?= e($i['deposito']) ?></td><td data-label="Rubro"><?= e($i['rubro'] ?? 'Todos') ?></td>
            <td class="td-titulo"><?= e($i['descripcion'] ?: 'Inventario') ?></td><td class="num" data-label="Contados"><?= (int) $i['contados'] ?> / <?= (int) $i['items'] ?></td><td data-label="Estado"><?= estado_badge($i['estado']) ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/stock/inventarios/' . $i['id']) ?>"><i class="bi bi-eye"></i> Abrir</a></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
