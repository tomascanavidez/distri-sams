<?php
use App\Services\PedidoService;
use App\Support\Html;
?>
<div class="encabezado">
    <h1><?= !empty($_GET['revision']) ? 'Pedidos a revisar (app)' : 'Pedidos' ?></h1>
    <div class="acciones">
        <?php if (puede('pedidos.exportar')): ?><?= Html::exportar('/pedidos/exportar') ?><?php endif; ?>
        <?php if (puede('pedidos.editar')): ?><a href="<?= url('/pedidos/preparacion') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-box2"></i> Preparación</a><?php endif; ?>
        <?php if (puede('pedidos.crear')): ?><a href="<?= url('/pedidos/nuevo') ?>" class="btn btn-primary btn-touch d-none d-md-inline-flex"><i class="bi bi-plus-lg"></i> Nuevo pedido</a><?php endif; ?>
    </div>
</div>
<form class="filtros row g-2 align-items-end" method="get">
    <?php if (!empty($_GET['revision'])): ?><input type="hidden" name="revision" value="1"><?php endif; ?>
    <div class="col-12 col-lg-3"><label class="form-label" for="q">Cliente o N°</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="estado">Estado</label>
        <select class="form-select" id="estado" name="estado" data-autoenviar><?= opciones(['pendientes' => 'Pendientes de entrega'] + PedidoService::ESTADOS, $_GET['estado'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="preventista_id">Preventista</label>
        <select class="form-select" id="preventista_id" name="preventista_id" data-autoenviar><?= opciones($preventistas, $_GET['preventista_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-1"><label class="form-label" for="zona_id">Zona</label>
        <select class="form-select" id="zona_id" name="zona_id" data-autoenviar><?= opciones($zonas, $_GET['zona_id'] ?? '', 'Todas') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>"></div>
    <div class="col-6 col-lg-12 col-xl-auto"><button class="btn btn-outline-primary w-100 btn-touch"><i class="bi bi-search"></i> Filtrar</button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('numero', 'N°') ?><th>Fecha</th><?= $pag->th('cliente', 'Cliente') ?><th>Zona</th><th>Preventista</th><?= $pag->th('entrega', 'Entrega') ?><?= $pag->th('total', 'Total', 'num') ?><?= $pag->th('estado', 'Estado') ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($pag->items as $p): ?>
        <tr>
            <td data-label="N°"><a href="<?= url('/pedidos/' . $p['id']) ?>" class="fw-bold"><?= (int) $p['numero'] ?></a></td>
            <td data-label="Fecha"><?= fecha_hora($p['fecha']) ?></td>
            <td class="td-titulo"><?= e($p['nombre_comercio']) ?><?php if ((int) $p['revision_requerida'] && $p['estado'] === 'borrador'): ?> <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle"></i> Revisar</span><?php endif; ?><?php if ($p['origen'] === 'movil'): ?> <i class="bi bi-phone text-body-secondary" title="Cargado desde la app"></i><?php endif; ?></td>
            <td data-label="Zona"><?= e($p['zona'] ?? '') ?></td>
            <td data-label="Preventista"><?= e($p['preventista'] ?? '') ?></td>
            <td data-label="Entrega"><?= fecha($p['fecha_entrega']) ?><?= $p['hr_numero'] ? ' <small class="text-body-secondary">HR ' . (int) $p['hr_numero'] . '</small>' : '' ?></td>
            <td class="num fw-semibold" data-label="Total"><?= money($p['total']) ?></td>
            <td data-label="Estado"><?= estado_badge($p['estado']) ?></td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/pedidos/' . $p['id']) ?>"><i class="bi bi-eye"></i> Ver</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="9" class="text-center text-body-secondary py-4">No hay pedidos con esos filtros.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
<?php if (puede('pedidos.crear')): ?><a href="<?= url('/pedidos/nuevo') ?>" class="fab d-md-none" aria-label="Nuevo pedido"><i class="bi bi-plus-lg"></i></a><?php endif; ?>
