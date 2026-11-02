<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Auditoría</h1><div class="acciones"><?php if (puede('auditoria.exportar')): ?><?= Html::exportar('/auditoria', false) ?><?php endif; ?></div></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-md-3"><label class="form-label" for="q">Texto</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Descripción o valores"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="modulo">Módulo</label><select class="form-select" id="modulo" name="modulo" data-autoenviar><?= opciones(array_combine($modulos, $modulos), $_GET['modulo'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="usuario_id">Usuario</label><select class="form-select" id="usuario_id" name="usuario_id" data-autoenviar><?= opciones($usuarios, $_GET['usuario_id'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? '') ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>"></div>
    <div class="col-12 col-md-1"><button class="btn btn-outline-primary w-100 btn-touch" aria-label="Filtrar"><i class="bi bi-search"></i></button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-sm align-middle tabla-cards mb-0">
    <thead><tr><th>Fecha</th><th>Usuario</th><th>Módulo / acción</th><th>Descripción</th><th>Cambios</th><th>IP</th></tr></thead>
    <tbody><?php foreach ($pag->items as $a): $antes = $a['datos_anteriores'] ? json_decode($a['datos_anteriores'], true) : []; $desp = $a['datos_nuevos'] ? json_decode($a['datos_nuevos'], true) : []; ?>
        <tr><td data-label="Fecha" class="text-nowrap"><?= fecha_hora($a['fecha']) ?></td><td data-label="Usuario"><?= e($a['usuario'] ?? 'Sistema') ?></td>
            <td class="td-titulo"><?= e($a['modulo']) ?> · <?= e($a['accion']) ?><?= $a['entidad'] ? ' <small class="fw-normal text-body-secondary">' . e($a['entidad']) . ' #' . (int) $a['entidad_id'] . '</small>' : '' ?></td>
            <td data-label="Descripción"><?= e($a['descripcion']) ?></td>
            <td data-label="Cambios"><small><?php foreach (array_unique(array_merge(array_keys((array) $antes), array_keys((array) $desp))) as $k):
                $va = $antes[$k] ?? null; $vd = $desp[$k] ?? null; ?>
                <div><strong><?= e($k) ?>:</strong> <?= $va !== null ? '<span class="text-danger text-decoration-line-through">' . e(is_array($va) ? json_encode($va, JSON_UNESCAPED_UNICODE) : $va) . '</span> → ' : '' ?><span class="text-success"><?= e(is_array($vd) ? json_encode($vd, JSON_UNESCAPED_UNICODE) : (string) $vd) ?></span></div>
            <?php endforeach; ?></small></td>
            <td data-label="IP"><small><?= e($a['ip']) ?></small></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?= $pag->enlaces() ?>
