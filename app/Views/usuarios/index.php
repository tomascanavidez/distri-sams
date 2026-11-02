<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Usuarios</h1><div class="acciones"><a href="<?= url('/usuarios/roles') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-shield-lock"></i> Roles y permisos</a><?php if (puede('usuarios.crear')): ?><a href="<?= url('/usuarios/nuevo') ?>" class="btn btn-primary btn-touch"><i class="bi bi-person-plus"></i> Nuevo usuario</a><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Caja</th><th>Vinculado a</th><th>Último ingreso</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
    <tbody><?php foreach ($usuarios as $u): ?>
        <tr><td class="td-titulo"><?= e($u['nombre']) ?></td><td data-label="Usuario"><code><?= e($u['usuario']) ?></code></td><td data-label="Rol"><?= e($u['rol']) ?></td><td data-label="Caja"><?= e($u['caja']) ?></td>
            <td data-label="Vinculado"><?= $u['preventista'] ? 'Preventista ' . e($u['preventista']) : ($u['chofer'] ? 'Chofer ' . e($u['chofer']) : '—') ?></td><td data-label="Último ingreso"><?= fecha_hora($u['ultimo_login']) ?: 'Nunca' ?></td>
            <td data-label="Estado"><?= estado_badge((int) $u['activo'] ? 'activo' : 'inactivo') ?></td>
            <td class="td-acciones text-end text-nowrap"><?php if (puede('usuarios.editar')): ?><a href="<?= url('/usuarios/' . $u['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary btn-touch"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (puede('usuarios.anular') && (int) $u['id'] !== (int) usuario()['id']): ?><?= Html::botonPost('/usuarios/' . $u['id'] . '/estado', (int) $u['activo'] ? 'Desactivar' : 'Activar', 'btn-sm btn-outline-secondary', (int) $u['activo'] ? '¿Desactivar el usuario? No podrá ingresar al sistema.' : null) ?><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
