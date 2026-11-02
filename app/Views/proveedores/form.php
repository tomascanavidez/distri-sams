<?php use App\Support\Html; ?>
<div class="encabezado"><h1><?= e($titulo) ?></h1><div class="acciones"><a href="<?= url($id ? '/proveedores/' . $id : '/proveedores') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Volver</a></div></div>
<?= App\Core\View::parcial('partials/flash', ['errores' => $errores]) ?>
<div class="card shadow-sm" style="max-width:900px"><div class="card-body">
<form method="post" class="row g-3">
    <?= csrf_campo() ?>
    <?= Html::input('razon_social', 'Razón social', $datos, $errores, ['requerido' => true, 'col' => 'col-md-6', 'max' => 150]) ?>
    <?= Html::input('nombre_fantasia', 'Nombre de fantasía', $datos, $errores, ['col' => 'col-md-6', 'max' => 150]) ?>
    <?= Html::input('cuit', 'CUIT', $datos, $errores, ['col' => 'col-md-4', 'inputmode' => 'numeric', 'placeholder' => '30-12345678-9']) ?>
    <?= Html::select('condicion_iva', 'Condición de IVA', condiciones_iva(), $datos, $errores, ['col' => 'col-md-4']) ?>
    <?= Html::input('plazo_pago_dias', 'Plazo de pago (días)', $datos, $errores, ['col' => 'col-md-4', 'tipo' => 'number', 'inputmode' => 'numeric']) ?>
    <?= Html::input('direccion', 'Dirección', $datos, $errores, ['col' => 'col-md-8']) ?>
    <?= Html::input('localidad', 'Localidad', $datos, $errores, ['col' => 'col-md-4']) ?>
    <?= Html::input('contacto', 'Contacto', $datos, $errores, ['col' => 'col-md-4']) ?>
    <?= Html::input('telefono', 'Teléfono', $datos, $errores, ['col' => 'col-md-4', 'tipo' => 'tel']) ?>
    <?= Html::input('email', 'Correo electrónico', $datos, $errores, ['col' => 'col-md-4', 'tipo' => 'email']) ?>
    <?= Html::textarea('observaciones', 'Observaciones', $datos, $errores) ?>
    <div class="col-12"><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar</button></div>
</form>
</div></div>
