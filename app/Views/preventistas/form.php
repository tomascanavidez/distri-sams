<?php use App\Support\Html; ?>
<div class="encabezado"><h1><?= e($titulo) ?></h1></div>
<?= App\Core\View::parcial('partials/flash', ['errores' => $errores]) ?>
<div class="card shadow-sm" style="max-width:760px"><div class="card-body">
<form method="post" class="row g-3">
    <?= csrf_campo() ?>
    <?= Html::input('nombre', 'Nombre y apellido', $datos, $errores, ['requerido' => true, 'col' => 'col-md-6']) ?>
    <?= Html::input('telefono', 'Teléfono', $datos, $errores, ['col' => 'col-md-6', 'tipo' => 'tel']) ?>
    <?= Html::select('usuario_id', 'Usuario del sistema (para la app)', $usuarios, $datos, $errores, ['vacio' => 'Sin usuario', 'col' => 'col-md-6']) ?>
    <?= Html::decimal('comision_porc', 'Comisión (%)', $datos, $errores, ['col' => 'col-6 col-md-3']) ?>
    <?= Html::select('comision_base', 'Sobre', ['ventas' => 'Ventas netas', 'cobranzas' => 'Cobranzas'], $datos, $errores, ['col' => 'col-6 col-md-3']) ?>
    <div class="col-12"><label class="form-label">Zonas asignadas</label><div class="d-flex flex-wrap gap-2">
        <?php foreach ($zonas as $z): ?><input type="checkbox" class="btn-check" id="z<?= (int) $z['id'] ?>" name="zonas[]" value="<?= (int) $z['id'] ?>" <?= $id && (int) $z['preventista_id'] === $id ? 'checked' : '' ?>><label class="btn btn-outline-secondary btn-touch" for="z<?= (int) $z['id'] ?>"><?= e($z['nombre']) ?></label><?php endforeach; ?>
    </div></div>
    <?= Html::check('asignar_clientes', 'Asignarle también los clientes de esas zonas', [], ['valor' => 0]) ?>
    <?= Html::check('activo', 'Activo', $datos) ?>
    <div class="col-12"><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar</button></div>
</form>
</div></div>
