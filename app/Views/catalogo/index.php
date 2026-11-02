<?php
use App\Support\Html;

$puedeEditar = puede($def['permiso_editar'] ?? $def['modulo'] . '.editar|' . $def['modulo'] . '.crear');
?>
<div class="encabezado">
    <h1><?= e($def['titulo']) ?></h1>
    <div class="acciones">
        <?php if ($puedeEditar): ?><a href="<?= url('/catalogo/' . $def['entidad'] . '/nuevo') ?>" class="btn btn-primary btn-touch"><i class="bi bi-plus-lg"></i> Nuevo</a><?php endif; ?>
    </div>
</div>
<?php if (!empty($def['ayuda'])): ?><p class="text-body-secondary"><i class="bi bi-info-circle"></i> <?= e($def['ayuda']) ?></p><?php endif; ?>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-md-6"><label class="form-label" for="q">Buscar</label>
        <input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Nombre..."></div>
    <div class="col-6 col-md-3"><div class="form-check mt-md-4"><input class="form-check-input" type="checkbox" id="inactivos" name="inactivos" value="1" <?= ($_GET['inactivos'] ?? '') === '1' ? 'checked' : '' ?> data-autoenviar>
        <label class="form-check-label" for="inactivos">Ver inactivos</label></div></div>
    <div class="col-6 col-md-3"><button class="btn btn-outline-primary w-100 btn-touch" type="submit"><i class="bi bi-search"></i> Buscar</button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr>
        <?php foreach ($def['columnas'] as $col => $etq): ?><th><?= e($etq) ?></th><?php endforeach; ?>
        <th>Estado</th><th class="text-end">Acciones</th>
    </tr></thead>
    <tbody>
    <?php foreach ($pag->items as $f): $primero = true; ?>
        <tr>
            <?php foreach ($def['columnas'] as $col => $etq):
                $v = $f[$col] ?? '';
                if (in_array($col, ['dias_visita', 'dias_entrega'], true)) { $v = dias_texto($v); }
                elseif (in_array($col, ['licencia_vencimiento', 'desde', 'hasta'], true)) { $v = fecha($v); }
                elseif (in_array($col, ['margen_porc'], true)) { $v = num($v) . ' %'; }
                elseif ($col === 'redondeo') { $v = (float) $v > 0 ? money($v) : 'Sin redondeo'; }
                elseif ($col === 'tipo') { $v = ['deposito' => 'Depósito', 'vehiculo' => 'Camión', 'banco' => 'Banco', 'billetera' => 'Billetera', 'tarjeta' => 'Tarjetas'][$v] ?? $v; }
            ?>
                <td class="<?= $primero ? 'td-titulo' : '' ?>" <?= $primero ? '' : 'data-label="' . e($etq) . '"' ?>><?= e($v) ?></td>
            <?php $primero = false; endforeach; ?>
            <td data-label="Estado"><?= estado_badge((int) $f['activo'] ? 'activo' : 'inactivo') ?></td>
            <td class="td-acciones text-end text-nowrap">
                <?php if ($puedeEditar): ?>
                    <a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/catalogo/' . $def['entidad'] . '/' . $f['id'] . '/editar') ?>"><i class="bi bi-pencil"></i> Editar</a>
                    <?= Html::botonPost('/catalogo/' . $def['entidad'] . '/' . $f['id'] . '/estado', (int) $f['activo'] ? '<i class="bi bi-toggle-off"></i> Desactivar' : '<i class="bi bi-toggle-on"></i> Activar', 'btn-sm btn-outline-secondary', (int) $f['activo'] ? '¿Desactivar este registro? No se borra, deja de aparecer en las listas.' : null) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="<?= count($def['columnas']) + 2 ?>" class="text-center text-body-secondary py-4">No hay registros.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<?= $pag->enlaces() ?>
