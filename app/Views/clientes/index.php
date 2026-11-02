<?php use App\Support\Html; ?>
<div class="encabezado">
    <h1>Clientes</h1>
    <div class="acciones">
        <?php if (puede('clientes.exportar')): ?><?= Html::exportar('/clientes/exportar') ?><?php endif; ?>
        <?php if (puede('clientes.crear')): ?><a href="<?= url('/clientes/nuevo') ?>" class="btn btn-primary btn-touch d-none d-md-inline-flex"><i class="bi bi-plus-lg"></i> Nuevo cliente</a><?php endif; ?>
    </div>
</div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-lg-4"><label class="form-label" for="q">Buscar</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Comercio, titular, dirección, CUIT o código"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="zona_id">Zona</label><select class="form-select" id="zona_id" name="zona_id" data-autoenviar><?= opciones($zonas, $_GET['zona_id'] ?? '', 'Todas') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="preventista_id">Preventista</label><select class="form-select" id="preventista_id" name="preventista_id" data-autoenviar><?= opciones($preventistas, $_GET['preventista_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="estado">Estado</label><select class="form-select" id="estado" name="estado" data-autoenviar><?= opciones(['activo' => 'Activos', 'inactivo' => 'Inactivos', 'bloqueado' => 'Bloqueados'], $_GET['estado'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="vencidos" value="1" id="vencidos" <?= !empty($_GET['vencidos']) ? 'checked' : '' ?> data-autoenviar><label class="form-check-label" for="vencidos">Con deuda vencida</label></div></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('codigo', 'Código') ?><?= $pag->th('nombre', 'Comercio') ?><th>Dirección</th><?= $pag->th('zona', 'Zona') ?><th>Teléfono</th><?= $pag->th('saldo', 'Saldo', 'num') ?><?= $pag->th('vencido', 'Vencido', 'num') ?><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($pag->items as $c): $wa = whatsapp_numero($c['whatsapp'] ?: $c['telefono']); ?>
        <tr>
            <td data-label="Código"><?= e($c['codigo']) ?></td>
            <td class="td-titulo"><a href="<?= url('/clientes/' . $c['id']) ?>"><?= e($c['nombre_comercio']) ?></a><div class="small text-body-secondary fw-normal"><?= e($c['titular']) ?> · <?= e($c['tipo_comercio']) ?></div></td>
            <td data-label="Dirección"><?= e($c['direccion']) ?></td>
            <td data-label="Zona"><?= e($c['zona'] ?? '') ?></td>
            <td data-label="Teléfono"><?= e($c['telefono']) ?></td>
            <td class="num <?= $c['saldo'] > 0 ? 'saldo-deuda' : '' ?>" data-label="Saldo"><?= money($c['saldo']) ?></td>
            <td class="num <?= $c['vencido'] > 0 ? 'text-danger fw-bold' : '' ?>" data-label="Vencido"><?= $c['vencido'] > 0 ? money($c['vencido']) : '—' ?></td>
            <td data-label="Estado"><?= estado_badge($c['estado']) ?></td>
            <td class="td-acciones text-end text-nowrap">
                <?php if ($c['telefono']): ?><a class="btn btn-sm btn-outline-secondary btn-touch" href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['telefono'])) ?>" aria-label="Llamar"><i class="bi bi-telephone"></i></a><?php endif; ?>
                <?php if ($wa): ?><a class="btn btn-sm btn-outline-success btn-touch" href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary btn-touch" href="<?= e(link_maps($c['direccion'], $c['localidad'], $c['latitud'], $c['longitud'])) ?>" target="_blank" rel="noopener" aria-label="Mapa"><i class="bi bi-geo-alt"></i></a>
                <a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/clientes/' . $c['id']) ?>" aria-label="Ver ficha"><i class="bi bi-eye"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="9" class="text-center text-body-secondary py-4">No se encontraron clientes.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
<?php if (puede('clientes.crear')): ?><a href="<?= url('/clientes/nuevo') ?>" class="fab d-md-none" aria-label="Nuevo cliente"><i class="bi bi-person-plus"></i></a><?php endif; ?>
