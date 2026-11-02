<?php use App\Support\Html; ?>
<div class="encabezado"><h1>Pago N° <?= (int) $pago['numero'] ?> <?= estado_badge($pago['estado']) ?></h1>
    <div class="acciones"><button class="btn btn-outline-secondary btn-touch" data-imprimir><i class="bi bi-printer"></i> Imprimir</button>
    <?php if ($pago['estado'] === 'emitido' && puede('compras.anular')): ?><?= Html::botonPost('/compras/pagos/' . $pago['id'] . '/anular', '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', '¿Anular el pago? Se revierte la caja/banco y los cheques vuelven a cartera.', true) ?><?php endif; ?></div></div>
<div class="row g-3">
    <div class="col-md-5"><div class="card shadow-sm"><div class="card-body ficha-dato row g-2">
        <div class="col-12"><small>Proveedor</small><a href="<?= url('/proveedores/' . $pago['proveedor_id']) ?>"><?= e($pago['razon_social']) ?></a> <small><?= e($pago['cuit']) ?></small></div>
        <div class="col-6"><small>Fecha</small><?= fecha_hora($pago['fecha']) ?></div><div class="col-6"><small>Total</small><strong class="fs-5"><?= money($pago['total']) ?></strong></div>
        <div class="col-6"><small>Usuario</small><?= e($pago['usuario']) ?></div>
        <?php if ($pago['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($pago['observaciones']) ?></div><?php endif; ?>
        <?php if ($pago['motivo_anulacion']): ?><div class="col-12"><small>Motivo de anulación</small><?= e($pago['motivo_anulacion']) ?></div><?php endif; ?>
    </div></div></div>
    <div class="col-md-7"><div class="card shadow-sm mb-3"><div class="card-body"><div class="card-titulo">Detalle</div>
        <?php foreach ($detalle as $d): ?><div class="d-flex justify-content-between border-bottom py-1"><span><?= e(['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque_propio' => 'Cheque propio', 'cheque_tercero' => 'Cheque de tercero'][$d['forma_pago']] ?? $d['forma_pago']) ?> <small class="text-body-secondary"><?= e($d['cuenta'] ?? '') ?><?= $d['cheque_numero'] ? 'N° ' . e($d['cheque_numero']) . ' ' . e($d['cheque_banco']) : '' ?> <?= e($d['referencia'] ?? '') ?></small></span><strong><?= money($d['importe']) ?></strong></div><?php endforeach; ?>
    </div></div>
    <div class="card shadow-sm"><div class="card-body"><div class="card-titulo">Aplicado a</div>
        <?php foreach ($imput as $i): ?><div class="d-flex justify-content-between"><span><?= e($i['descripcion']) ?></span><strong><?= money($i['importe']) ?></strong></div><?php endforeach; ?>
        <?php if (!$imput): ?><p class="text-body-secondary mb-0">A cuenta.</p><?php endif; ?></div></div></div>
</div>
