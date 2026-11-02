<?php $hr = $d['hr']; $esp = $d['esperado']; ?>
<div class="encabezado"><h1>Rendición — Hoja de ruta N° <?= (int) $hr['numero'] ?></h1></div>
<form method="post" action="<?= url('/reparto/' . $hr['id'] . '/rendicion') ?>" data-confirmar="¿Confirmar la rendición? Se ingresa el efectivo a la caja, se ajustan las diferencias y la mercadería vuelve al depósito." x-data="{ efectivo: '<?= e(num($esp['efectivo'] ?? 0)) ?>' }">
    <?= csrf_campo() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3"><div class="card-body">
                <div class="card-titulo"><i class="bi bi-boxes"></i> Mercadería</div>
                <p class="small text-body-secondary">Contá lo que vuelve en el camión. Si no coincide con lo esperado, la diferencia se registra como ajuste de stock (faltante/sobrante).</p>
                <table class="table table-sm align-middle tabla-cards mb-0">
                    <thead><tr><th>Producto</th><th class="num">Cargado</th><th class="num">Entregado</th><th class="num">Debe volver</th><th class="num">Volvió</th></tr></thead>
                    <tbody><?php foreach ($d['productos'] as $p): ?>
                        <tr><td class="td-titulo"><?= e($p['descripcion']) ?></td><td class="num" data-label="Cargado"><?= cant($p['cargado']) ?></td><td class="num" data-label="Entregado"><?= cant($p['entregado']) ?></td>
                            <td class="num fw-bold" data-label="Debe volver"><?= cant($p['devolucion_esperada']) ?></td>
                            <td class="num" data-label="Volvió"><input class="form-control form-control-sm text-end ms-auto" style="max-width:90px" name="devuelto[<?= (int) $p['id'] ?>]" value="<?= e(cant($p['devolucion_esperada'])) ?>" inputmode="decimal" aria-label="Devuelto"></td></tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div></div>
            <div class="card shadow-sm"><div class="card-body">
                <div class="card-titulo"><i class="bi bi-signpost-split"></i> Entregas no realizadas</div>
                <?php $hay = false; foreach ($d['paradas'] as $pa): if (!in_array($pa['estado'], ['pendiente', 'rechazado', 'cerrado'], true)) continue; $hay = true; ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2">
                        <span><strong><?= e($pa['nombre_comercio']) ?></strong> · pedido N° <?= (int) $pa['pedido_numero'] ?> · <?= estado_badge($pa['estado']) ?> <small><?= e($pa['motivo']) ?></small></span>
                        <label class="form-check-label text-nowrap"><input type="checkbox" class="form-check-input" name="reprogramar[<?= (int) $pa['id'] ?>]" value="1" <?= $pa['estado'] !== 'rechazado' ? 'checked' : '' ?>> Reprogramar</label>
                    </div>
                <?php endforeach; ?>
                <?php if (!$hay): ?><p class="text-success mb-0"><i class="bi bi-check-circle"></i> Se entregaron todos los pedidos.</p><?php else: ?><p class="small text-body-secondary mt-2 mb-0">Los reprogramados vuelven a "preparado" con stock reservado para otra hoja de ruta; los demás quedan como rechazados.</p><?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-5"><div class="card shadow-sm"><div class="card-body">
            <div class="card-titulo"><i class="bi bi-cash-stack"></i> Dinero</div>
            <div class="row g-2 align-items-center mb-2">
                <div class="col-6">Efectivo esperado</div><div class="col-6 text-end fw-bold"><?= money($esp['efectivo'] ?? 0) ?></div>
                <div class="col-6"><label class="form-label mb-0" for="rendEf">Efectivo rendido</label></div><div class="col-6"><input class="form-control text-end fw-bold" id="rendEf" name="rendido[efectivo]" x-model="efectivo" inputmode="decimal"></div>
                <div class="col-6">Diferencia</div><div class="col-6 text-end fw-bold" :class="Math.abs(DT.parse(efectivo) - <?= (float) ($esp['efectivo'] ?? 0) ?>) < 0.01 ? 'text-success' : 'text-danger'" x-text="DT.dinero(DT.parse(efectivo) - <?= (float) ($esp['efectivo'] ?? 0) ?>)"></div>
            </div>
            <?php foreach ($esp as $forma => $imp): if ($forma === 'efectivo') continue; ?>
                <div class="row g-2 align-items-center mb-2"><div class="col-6"><?= e(forma_pago($forma)) ?> <small class="text-body-secondary">(esperado <?= money($imp) ?>)</small></div>
                    <div class="col-6"><input class="form-control text-end" name="rendido[<?= e($forma) ?>]" value="<?= e(num($imp)) ?>" inputmode="decimal" aria-label="Confirmado <?= e($forma) ?>"></div></div>
            <?php endforeach; ?>
            <div class="mb-2"><label class="form-label">Caja donde ingresa el efectivo</label><select class="form-select" name="caja_id"><?= opciones($cajas, $cajaUsuario) ?></select>
                <?php if (!in_array($cajaUsuario, array_map('intval', $cajasAbiertas), true)): ?><div class="form-text text-danger">La caja elegida debe estar abierta.</div><?php endif; ?></div>
            <div class="mb-3"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="2"></textarea></div>
            <button class="btn btn-success btn-touch w-100"><i class="bi bi-clipboard-check"></i> Confirmar rendición</button>
        </div></div></div>
    </div>
</form>
