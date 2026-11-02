<?php use App\Support\Permisos;
$rol = array_values(array_filter($roles, fn ($r) => (int) $r['id'] === $rolId))[0] ?? $roles[0];
$esAdmin = $rol['slug'] === 'admin';
$editable = puede('usuarios.editar') && !$esAdmin; ?>
<div class="encabezado"><h1>Roles y permisos</h1></div>
<div class="row g-3">
    <div class="col-lg-3">
        <div class="list-group shadow-sm mb-3">
            <?php foreach ($roles as $r): ?><a href="<?= url('/usuarios/roles?rol_id=' . $r['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between <?= (int) $r['id'] === $rolId ? 'active' : '' ?>"><span><?= e($r['nombre']) ?></span><span class="badge text-bg-light"><?= (int) $r['usuarios'] ?></span></a><?php endforeach; ?>
        </div>
        <?php if (puede('usuarios.crear')): ?>
        <div class="card shadow-sm"><div class="card-body">
            <div class="card-titulo">Nuevo rol</div>
            <form method="post" action="<?= url('/usuarios/roles/nuevo') ?>" class="d-grid gap-2"><?= csrf_campo() ?>
                <input class="form-control" name="nombre" placeholder="Nombre del rol" required>
                <select class="form-select" name="copiar_de"><?= opciones(array_column($roles, 'nombre', 'id'), '', 'Copiar permisos de…') ?></select>
                <button class="btn btn-outline-primary btn-touch">Crear rol</button></form>
        </div></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-9">
        <form method="post" action="<?= url('/usuarios/roles') ?>" class="card shadow-sm"><div class="card-body">
            <?= csrf_campo() ?><input type="hidden" name="rol_id" value="<?= (int) $rol['id'] ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
                <div><div class="card-titulo mb-0"><?= e($rol['nombre']) ?></div><small class="text-body-secondary"><?= e($rol['descripcion']) ?></small></div>
                <div><label class="form-label small mb-0">Pantalla de inicio</label><input class="form-control form-control-sm" name="pantalla_inicio" value="<?= e($rol['pantalla_inicio']) ?>" <?= $editable ? '' : 'disabled' ?>></div>
            </div>
            <?php if ($esAdmin): ?><div class="alert alert-info py-2">El Administrador tiene siempre acceso total.</div><?php endif; ?>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Módulo</th><?php foreach (Permisos::ACCIONES as $a => $t): ?><th class="text-center"><?= e($t) ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach (Permisos::modulos() as $mod => [$nombre, $acciones]): ?>
                    <tr><td><?= e($nombre) ?></td><?php foreach (Permisos::ACCIONES as $a => $t): ?><td class="text-center"><?php if (in_array($a, $acciones, true)): ?><input class="form-check-input" type="checkbox" name="permisos[]" value="<?= $mod . '.' . $a ?>" aria-label="<?= e($nombre . ' ' . $t) ?>" <?= $esAdmin || isset($asignados[$mod . '.' . $a]) ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>><?php endif; ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php if ($editable): ?><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar permisos</button><?php endif; ?>
        </div></form>
    </div>
</div>
