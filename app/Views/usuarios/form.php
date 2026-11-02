<?php use App\Support\Html; ?>
<div class="encabezado"><h1><?= e($titulo) ?></h1></div>
<?= App\Core\View::parcial('partials/flash', ['errores' => $errores]) ?>
<div class="card shadow-sm" style="max-width:720px"><div class="card-body">
<form method="post" class="row g-3" autocomplete="off">
    <?= csrf_campo() ?>
    <?= Html::input('nombre', 'Nombre y apellido', $datos, $errores, ['requerido' => true, 'col' => 'col-md-6']) ?>
    <?= Html::input('usuario', 'Usuario (para ingresar)', $datos, $errores, ['requerido' => true, 'col' => 'col-md-6', 'attrs' => ' autocapitalize="none"']) ?>
    <?= Html::input('email', 'Correo electrónico', $datos, $errores, ['col' => 'col-md-6', 'tipo' => 'email']) ?>
    <?= Html::select('rol_id', 'Rol', $roles, $datos, $errores, ['requerido' => true, 'col' => 'col-md-6']) ?>
    <?= Html::select('caja_id', 'Caja que usa (cobros en efectivo)', $cajas, $datos, $errores, ['vacio' => 'Ninguna', 'col' => 'col-md-6']) ?>
    <?= Html::input('clave', $id ? 'Nueva contraseña (dejar vacío para no cambiarla)' : 'Contraseña', [], $errores, ['tipo' => 'password', 'col' => 'col-md-6', 'requerido' => !$id, 'attrs' => ' autocomplete="new-password" minlength="8"']) ?>
    <?= Html::check('activo', 'Usuario activo', $datos) ?>
    <?php if (isset($errores['activo'])): ?><div class="col-12 text-danger small"><?= e($errores['activo']) ?></div><?php endif; ?>
    <div class="col-12"><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar</button> <a href="<?= url('/usuarios') ?>" class="btn btn-outline-secondary btn-touch">Cancelar</a></div>
</form>
<p class="small text-body-secondary mt-3 mb-0">Para que un preventista o chofer use la app del celular, después de crear el usuario asocialo en Preventistas o en Reparto › Choferes.</p>
</div></div>
