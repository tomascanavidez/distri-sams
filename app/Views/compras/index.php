<?php use App\Services\CompraService; ?>
<div class="encabezado"><h1>Órdenes de compra</h1>
    <div class="acciones"><?php if (puede('compras.crear')): ?><a href="<?= url('/compras/sugerencia') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-lightbulb"></i> Sugerencia</a><a href="<?= url('/compras/nueva') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nueva orden</a><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-4"><label class="form-label" for="proveedor_id">Proveedor</label><select class="form-select" id="proveedor_id" name="proveedor_id" data-autoenviar><?= opciones($proveedores, $_GET['proveedor_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="estado">Estado</label><select class="form-select" id="estado" name="estado" data-autoenviar><?= opciones(['pendientes' => 'Pendientes de recibir'] + CompraService::ESTADOS_OC, $_GET['estado'] ?? '', 'Todos') ?></select></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('numero', 'N°') ?><th>Fecha</th><th>Proveedor</th><th>Entrega</th><?= $pag->th('total', 'Total estimado', 'num') ?><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($pag->items as $o): ?>
        <tr><td data-label="N°"><a class="fw-bold" href="<?= url('/compras/' . $o['id']) ?>"><?= (int) $o['numero'] ?></a></td><td data-label="Fecha"><?= fecha($o['fecha']) ?></td><td class="td-titulo"><?= e($o['razon_social']) ?></td>
            <td data-label="Entrega"><?= fecha($o['fecha_entrega']) ?></td><td class="num" data-label="Total"><?= money($o['total']) ?></td><td data-label="Estado"><?= estado_badge($o['estado']) ?></td>
            <td class="td-acciones text-end text-nowrap"><?php if (in_array($o['estado'], ['borrador', 'enviada', 'recibida_parcial'], true) && puede('compras.crear')): ?><a class="btn btn-sm btn-outline-success btn-touch" href="<?= url('/compras/recepciones/nueva?orden_id=' . $o['id']) ?>"><i class="bi bi-box-arrow-in-down"></i> Recibir</a><?php endif; ?>
                <a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/compras/' . $o['id']) ?>"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No hay órdenes.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
