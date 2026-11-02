<?php use App\Support\Html; $e = $oc['estado']; ?>
<div class="encabezado"><h1>Orden de compra N° <?= (int) $oc['numero'] ?> <?= estado_badge($e) ?></h1>
    <div class="acciones">
        <a href="<?= url('/compras/' . $oc['id'] . '/imprimir') ?>" target="_blank" class="btn btn-outline-secondary btn-touch"><i class="bi bi-printer"></i> Imprimir</a>
        <?php if (in_array($e, ['borrador', 'enviada'], true) && puede('compras.editar')): ?><a href="<?= url('/compras/' . $oc['id'] . '/editar') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
        <?php if ($e === 'borrador' && puede('compras.editar')): ?><?= Html::botonPost('/compras/' . $oc['id'] . '/estado', '<i class="bi bi-send"></i> Marcar enviada', 'btn-outline-primary', null, false, ['estado' => 'enviada']) ?><?php endif; ?>
        <?php if (in_array($e, ['borrador', 'enviada', 'recibida_parcial'], true) && puede('compras.crear')): ?><a href="<?= url('/compras/recepciones/nueva?orden_id=' . $oc['id']) ?>" class="btn btn-success btn-touch"><i class="bi bi-box-arrow-in-down"></i> Recibir mercadería</a><?php endif; ?>
        <?php if (in_array($e, ['borrador', 'enviada', 'recibida_parcial'], true) && puede('compras.anular')): ?><?= Html::botonPost('/compras/' . $oc['id'] . '/estado', $e === 'recibida_parcial' ? 'Cerrar con faltantes' : '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', $e === 'recibida_parcial' ? '¿Cerrar la orden? Lo pendiente no se recibirá.' : '¿Anular la orden de compra?', true, ['estado' => 'anulada']) ?><?php endif; ?>
    </div></div>
<div class="row g-3">
    <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body ficha-dato row g-2">
        <div class="col-12"><small>Proveedor</small><a class="fw-bold" href="<?= url('/proveedores/' . $oc['proveedor_id']) ?>"><?= e($oc['razon_social']) ?></a><br><span class="small"><?= e($oc['contacto']) ?> · <?= e($oc['telefono']) ?></span></div>
        <div class="col-6"><small>Fecha</small><?= fecha($oc['fecha']) ?></div><div class="col-6"><small>Entrega</small><?= fecha($oc['fecha_entrega']) ?: '—' ?></div>
        <div class="col-6"><small>Depósito</small><?= e($oc['deposito']) ?></div><div class="col-6"><small>Usuario</small><?= e($oc['usuario']) ?></div>
        <?php if ($oc['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($oc['observaciones']) ?></div><?php endif; ?>
        <?php if ($oc['motivo_anulacion']): ?><div class="col-12"><small>Motivo</small><?= e($oc['motivo_anulacion']) ?></div><?php endif; ?>
        <div class="col-12"><small>Recepciones</small><?php foreach ($recepciones as $r): ?><a href="<?= url('/compras/recepciones/' . $r['id']) ?>">N° <?= (int) $r['numero'] ?> (<?= fecha($r['fecha']) ?>)</a> <?= $r['estado'] === 'anulada' ? estado_badge('anulada') : '' ?><br><?php endforeach; ?><?= $recepciones ? '' : '—' ?></div>
    </div></div></div>
    <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body">
        <table class="table align-middle tabla-cards mb-0">
            <thead><tr><th>Producto</th><th class="num">Pedido</th><th class="num">Recibido</th><th class="num">Costo</th><th class="num">Subtotal</th></tr></thead>
            <tbody><?php foreach ($items as $it): $rec = (float) $it['cantidad_recibida_base']; ?>
                <tr><td class="td-titulo"><?= e($it['descripcion']) ?><div class="small fw-normal text-body-secondary"><?= e($it['codigo']) ?></div></td>
                    <td class="num" data-label="Pedido"><?= cant($it['cantidad']) ?> <?= e($it['presentacion_nombre']) ?><br><small class="text-body-secondary"><?= cant($it['cantidad_base']) ?> u.</small></td>
                    <td class="num <?= $rec + 0.0005 < (float) $it['cantidad_base'] ? 'text-warning-emphasis' : 'text-success' ?>" data-label="Recibido"><?= cant($rec) ?> u.</td>
                    <td class="num" data-label="Costo"><?= money($it['costo_unitario']) ?></td><td class="num" data-label="Subtotal"><?= money((float) $it['costo_unitario'] * (float) $it['cantidad']) ?></td></tr>
            <?php endforeach; ?></tbody>
            <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">Total neto</td><td class="num"><?= money($oc['total']) ?></td></tr></tfoot>
        </table>
    </div></div></div>
</div>
