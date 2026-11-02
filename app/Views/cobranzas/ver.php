<?php use App\Support\Html; ?>
<div class="encabezado">
    <h1>Recibo <?= e($r['nro']) ?> <?= estado_badge($r['estado']) ?></h1>
    <div class="acciones">
        <a class="btn btn-outline-secondary btn-touch" target="_blank" href="<?= url('/cobranzas/' . $r['id'] . '/imprimir') ?>"><i class="bi bi-printer"></i> Imprimir</a>
        <?php if ($r['estado'] === 'emitido' && puede('cobranzas.anular')): ?><?= Html::botonPost('/cobranzas/' . $r['id'] . '/anular', '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', '¿Anular el recibo? Se revierte el ingreso de caja/banco y vuelve la deuda al cliente.', true) ?><?php endif; ?>
    </div>
</div>
<?php if ($r['estado'] === 'anulado'): ?><div class="alert alert-secondary">Motivo de anulación: <?= e($r['motivo_anulacion']) ?></div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-5"><div class="card shadow-sm"><div class="card-body ficha-dato row g-2">
        <div class="col-12"><small>Cliente</small><a class="fw-bold" href="<?= url('/clientes/' . $r['cliente_id'] . '/cuenta') ?>"><?= e($r['nombre_comercio']) ?></a></div>
        <div class="col-6"><small>Fecha</small><?= fecha_hora($r['fecha']) ?></div>
        <div class="col-6"><small>Origen</small><?= e(['cobranza' => 'Oficina', 'mostrador' => 'Mostrador', 'reparto' => 'Reparto'][$r['origen']] ?? '') ?><?= $r['hr_numero'] ? ' (HR ' . (int) $r['hr_numero'] . ')' : '' ?></div>
        <div class="col-6"><small>Usuario</small><?= e($r['usuario']) ?></div>
        <div class="col-6"><small>Total</small><strong class="fs-5"><?= money($r['total']) ?></strong></div>
        <?php if ($r['comprobante_id']): ?><div class="col-12"><small>Comprobante</small><a href="<?= url('/ventas/' . $r['comprobante_id']) ?>">Ver comprobante cobrado</a></div><?php endif; ?>
        <?php if ($r['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($r['observaciones']) ?></div><?php endif; ?>
    </div></div></div>
    <div class="col-lg-7">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo">Pagos</div>
            <table class="table table-sm mb-0"><tbody><?php foreach ($r['pagos'] as $p): ?>
                <tr><td><?= e(forma_pago($p['forma_pago'])) ?><?= $p['cuenta'] ? ' <small class="text-body-secondary">→ ' . e($p['cuenta']) . '</small>' : '' ?>
                    <?= $p['cheque_numero'] ? '<br><small>Cheque N° ' . e($p['cheque_numero']) . ' ' . e($p['cheque_banco']) . ' · pago ' . fecha($p['cheque_fecha']) . ' · ' . e($p['cheque_estado']) . '</small>' : '' ?>
                    <?= $p['referencia'] ? '<br><small>Ref. ' . e($p['referencia']) . '</small>' : '' ?>
                    <?= (int) $p['pendiente_rendicion'] ? ' <span class="badge text-bg-warning">Pendiente de rendición</span>' : '' ?></td>
                    <td class="text-end fw-semibold"><?= money($p['importe']) ?></td></tr>
            <?php endforeach; ?></tbody></table>
        </div></div>
        <div class="card shadow-sm"><div class="card-body">
            <div class="card-titulo">Imputación</div>
            <?php foreach ($r['imputaciones'] as $i): ?><div class="d-flex justify-content-between border-bottom py-1"><span><?= e($i['descripcion']) ?> <small class="text-body-secondary"><?= fecha($i['fecha']) ?></small></span><strong><?= money($i['importe']) ?></strong></div><?php endforeach; ?>
            <?php if ($r['a_cuenta'] > 0): ?><div class="d-flex justify-content-between py-1"><span>A cuenta (crédito a favor)</span><strong><?= money($r['a_cuenta']) ?></strong></div><?php endif; ?>
            <?php if (!$r['imputaciones'] && $r['a_cuenta'] <= 0): ?><p class="text-body-secondary mb-0">Sin imputaciones vigentes.</p><?php endif; ?>
        </div></div>
    </div>
</div>
