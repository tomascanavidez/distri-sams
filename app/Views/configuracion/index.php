<?php use App\Controllers\ConfiguracionController; $dis = $puede ? '' : 'disabled'; ?>
<div class="encabezado"><h1>Empresa y parámetros</h1></div>
<form method="post" enctype="multipart/form-data">
    <?= csrf_campo() ?>
    <div class="row g-3">
    <?php foreach (ConfiguracionController::campos() as $seccion => $campos): ?>
        <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo"><?= e($seccion) ?></div>
            <div class="row g-2">
            <?php foreach ($campos as $clave => $c): [$etq, $tipo] = $c; $val = $valores[$clave] ?? ''; ?>
                <div class="col-12 <?= in_array($tipo, ['numero'], true) ? 'col-md-6' : '' ?>">
                    <label class="form-label small mb-1" for="c_<?= $clave ?>"><?= e($etq) ?></label>
                    <?php if ($tipo === 'select'): $ops = is_array($c[2]) ? $c[2] : ($opciones[$c[2]] ?? []); ?>
                        <select class="form-select" id="c_<?= $clave ?>" name="<?= $clave ?>" <?= $dis ?>><?= opciones($ops, $val) ?></select>
                    <?php elseif ($tipo === 'fecha'): ?>
                        <input type="date" class="form-control" id="c_<?= $clave ?>" name="<?= $clave ?>" value="<?= e($val) ?>" <?= $dis ?>>
                    <?php elseif ($tipo === 'numero'): ?>
                        <input type="number" inputmode="numeric" class="form-control" id="c_<?= $clave ?>" name="<?= $clave ?>" value="<?= e($val) ?>" min="0" <?= $dis ?>>
                    <?php else: ?>
                        <input class="form-control" id="c_<?= $clave ?>" name="<?= $clave ?>" value="<?= e($val) ?>" <?= $dis ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($seccion === 'Datos de la empresa'): ?>
                <div class="col-12"><label class="form-label small mb-1">Logo (aparece en comprobantes y PDF)</label>
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= $valores['empresa_logo'] ? url('/uploads/empresa/' . $valores['empresa_logo']) : asset('assets/img/logo.png') ?>" alt="Logo" style="max-height:64px;max-width:140px;background:#fff;border-radius:6px;padding:4px">
                        <?php if ($puede): ?><input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/webp"><?php endif; ?>
                    </div>
                    <?php if ($puede && $valores['empresa_logo']): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="quitar_logo" value="1" id="ql"><label class="form-check-label" for="ql">Volver al logo original</label></div><?php endif; ?>
                </div>
            <?php endif; ?>
            </div>
        </div></div></div>
    <?php endforeach; ?>
        <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="card-titulo">Numeración de comprobantes</div>
            <p class="small text-body-secondary">Último número utilizado por punto de venta y tipo. Sólo puede avanzarse (por ejemplo, para continuar la numeración de un talonario anterior).</p>
            <div class="row g-2">
            <?php foreach ($numeracion as $n): ?>
                <div class="col-6 col-md-4"><label class="form-label small mb-1"><?= sprintf('%04d', $n['pv_numero']) ?> · <?= e(tipos_comprobante()[$n['tipo']] ?? ['RC' => 'Recibos', 'PED' => 'Pedidos', 'OC' => 'Órdenes de compra', 'REC' => 'Recepciones', 'HR' => 'Hojas de ruta', 'PP' => 'Pagos a prov.', 'DP' => 'Devol. a prov.'][$n['tipo']] ?? $n['tipo']) ?></label>
                    <input class="form-control" type="number" inputmode="numeric" name="numeracion[<?= (int) $n['punto_venta_id'] ?>|<?= e($n['tipo']) ?>]" value="<?= (int) $n['ultimo_numero'] ?>" min="<?= (int) $n['ultimo_numero'] ?>" <?= $dis ?>></div>
            <?php endforeach; ?>
            </div>
            <p class="small mt-2 mb-0"><a href="<?= url('/catalogo/puntos_venta') ?>">Administrar puntos de venta</a> · <a href="<?= url('/catalogo/listas') ?>">Listas de precios y redondeos</a> · <a href="<?= url('/catalogo/depositos') ?>">Depósitos</a></p>
        </div></div></div>
    </div>
    <?php if ($puede): ?><div class="barra-acciones-fija"><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar configuración</button></div><?php endif; ?>
</form>
