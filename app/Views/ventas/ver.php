<?php
use App\Services\CobranzaService;
use App\Services\VentaService;
use App\Support\Html;
$esNC = str_starts_with($c['tipo'], 'NC');
?>
<div class="encabezado">
    <h1><?= e(tipo_comprobante($c['tipo'])) ?> <?= e(sprintf('%04d-%08d', $c['pv_numero'], $c['numero'])) ?> <?= estado_badge($c['estado']) ?></h1>
    <div class="acciones">
        <a href="<?= url('/ventas/' . $c['id'] . '/imprimir') ?>" target="_blank" class="btn btn-outline-secondary btn-touch"><i class="bi bi-printer"></i> A4</a>
        <a href="<?= url('/ventas/' . $c['id'] . '/imprimir?formato=ticket') ?>" target="_blank" class="btn btn-outline-secondary btn-touch"><i class="bi bi-receipt-cutoff"></i> Ticket</a>
        <a href="<?= url('/ventas/' . $c['id'] . '/pdf') ?>" class="btn btn-outline-danger btn-touch"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <?php if ($c['estado'] === 'emitido' && $c['tipo'] === 'PR' && puede('ventas.crear')): ?><?= Html::botonPost('/ventas/' . $c['id'] . '/convertir', '<i class="bi bi-cart-plus"></i> Convertir en pedido', 'btn-primary') ?><?php endif; ?>
        <?php if ($c['estado'] === 'emitido' && in_array($c['tipo'], ['FA', 'FB', 'FC', 'RM'], true) && puede('ventas.crear')): ?><a class="btn btn-outline-primary btn-touch" href="<?= url('/ventas/devolucion?factura_id=' . $c['id']) ?>"><i class="bi bi-arrow-return-left"></i> Devolución</a><?php endif; ?>
        <?php if ($c['estado'] === 'emitido' && $pendiente > 0 && puede('cobranzas.crear')): ?><a class="btn btn-outline-success btn-touch" href="<?= url('/cobranzas/nueva?cliente_id=' . $c['cliente_id']) ?>"><i class="bi bi-cash-coin"></i> Cobrar</a><?php endif; ?>
        <?php if ($c['estado'] === 'emitido' && puede('ventas.anular')): ?>
            <form method="post" action="<?= url('/ventas/' . $c['id'] . '/anular') ?>" class="d-inline" data-confirmar="¿Anular este comprobante? Se revierte el stock y la cuenta corriente. El número no se reutiliza." data-motivo data-boton="Anular">
                <?= csrf_campo() ?><input type="hidden" name="anular_recibos" value="1">
                <button class="btn btn-outline-danger btn-touch"><i class="bi bi-x-circle"></i> Anular</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if ($c['estado'] === 'anulado'): ?><div class="alert alert-secondary">Anulado el <?= fecha_hora($c['anulado_en']) ?>. Motivo: <?= e($c['motivo_anulacion']) ?></div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-body ficha-dato row g-2">
            <div class="col-12"><small>Cliente</small><a class="fw-bold" href="<?= url('/clientes/' . $c['cliente_id']) ?>"><?= e($c['cliente_nombre']) ?></a><br><span class="small"><?= e($c['cliente_documento']) ?> · <?= e(condicion_iva($c['cliente_condicion_iva'])) ?></span></div>
            <div class="col-6"><small>Fecha</small><?= fecha_hora($c['fecha']) ?></div>
            <div class="col-6"><small>Condición</small><?= $c['condicion_venta'] === 'cta_cte' ? 'Cta. cte. (vence ' . fecha($c['vencimiento']) . ')' : 'Contado' ?></div>
            <div class="col-6"><small>Origen</small><?= e(ucfirst($c['origen'])) ?></div>
            <div class="col-6"><small>Usuario</small><?= e($c['usuario']) ?></div>
            <?php if ($c['preventista']): ?><div class="col-6"><small>Preventista</small><?= e($c['preventista']) ?></div><?php endif; ?>
            <?php if ($c['pedido_id']): ?><div class="col-6"><small>Pedido</small><a href="<?= url('/pedidos/' . $c['pedido_id']) ?>">Ver pedido</a></div><?php endif; ?>
            <?php if ($c['hoja_ruta_id']): ?><div class="col-6"><small>Hoja de ruta</small><a href="<?= url('/reparto/' . $c['hoja_ruta_id']) ?>">Ver hoja</a></div><?php endif; ?>
            <?php if ($origen): ?><div class="col-12"><small>Comprobante asociado</small><a href="<?= url('/ventas/' . $origen['id']) ?>"><?= e(VentaService::numero($origen)) ?></a></div><?php endif; ?>
            <div class="col-12"><small>Facturación</small><?= e($c['proveedor_facturacion'] === 'interno' ? 'Interna (sin CAE)' : $c['proveedor_facturacion']) ?><?= $c['cae'] ? ' · CAE ' . e($c['cae']) . ' vto. ' . fecha($c['cae_vencimiento']) : '' ?></div>
            <?php if (!$esNC && $c['tipo'] !== 'PR' && $c['estado'] === 'emitido'): ?><div class="col-12"><small>Saldo pendiente de cobro</small><span class="<?= $pendiente > 0 ? 'saldo-deuda' : 'saldo-favor' ?>"><?= money($pendiente) ?></span></div><?php endif; ?>
            <?php if ($c['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($c['observaciones']) ?></div><?php endif; ?>
        </div></div>
        <?php if ($recibos): ?>
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo">Cobros</div>
            <?php foreach ($recibos as $r): ?>
                <div class="d-flex justify-content-between border-bottom py-1"><a href="<?= url('/cobranzas/' . $r['id']) ?>"><?= e(CobranzaService::numero($r)) ?></a><span><?= e(implode(', ', array_map('forma_pago', explode(',', (string) $r['formas'])))) ?></span><strong><?= money($r['total']) ?></strong> <?= estado_badge($r['estado']) ?></div>
            <?php endforeach; ?>
        </div></div>
        <?php endif; ?>
        <?php if ($asociados): ?>
        <div class="card shadow-sm"><div class="card-body">
            <div class="card-titulo">Notas asociadas</div>
            <?php foreach ($asociados as $a): ?><div class="d-flex justify-content-between"><a href="<?= url('/ventas/' . $a['id']) ?>"><?= e(VentaService::numero($a)) ?></a><span><?= money($a['total']) ?> <?= estado_badge($a['estado']) ?></span></div><?php endforeach; ?>
        </div></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm"><div class="card-body">
        <table class="table align-middle tabla-cards mb-0">
            <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Desc.</th><th class="num">IVA</th><th class="num">Total</th><?php if ($esNC): ?><th>Stock</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($c['items'] as $it): ?>
                <tr><td class="td-titulo"><?= e($it['descripcion']) ?><div class="small fw-normal text-body-secondary"><?= e($it['codigo']) ?><?= (float) $it['bonificacion_base'] > 0 ? ' · +' . cant($it['bonificacion_base']) . ' u. bonificadas' : '' ?></div></td>
                    <td class="num" data-label="Cantidad"><?= cant($it['cantidad']) ?> <?= e($it['presentacion_nombre']) ?></td>
                    <td class="num" data-label="Precio"><?= money($it['precio_unitario']) ?></td>
                    <td class="num" data-label="Desc."><?= (float) $it['descuento_porc'] > 0 ? num($it['descuento_porc'], 1) . ' %' : '—' ?></td>
                    <td class="num" data-label="IVA"><?= num($it['iva_alicuota'], 1) ?> %</td>
                    <td class="num fw-semibold" data-label="Total"><?= money($it['total']) ?></td>
                    <?php if ($esNC): ?><td data-label="Stock"><?= (int) $it['reingresa_stock'] ? 'Reingresó' : 'No reingresa' ?></td><?php endif; ?></tr>
            <?php endforeach; ?></tbody>
        </table>
        <div class="row justify-content-end mt-3"><div class="col-md-6 totales-doc">
            <?php if ((float) $c['descuento_importe'] > 0): ?><div class="d-flex justify-content-between"><span>Descuento general (<?= num($c['descuento_porc'], 1) ?> %)</span><span>- <?= money($c['descuento_importe']) ?></span></div><?php endif; ?>
            <div class="d-flex justify-content-between"><span>Neto gravado 21 %</span><span><?= money($c['neto_21']) ?></span></div>
            <?php if ((float) $c['neto_105']): ?><div class="d-flex justify-content-between"><span>Neto gravado 10,5 %</span><span><?= money($c['neto_105']) ?></span></div><?php endif; ?>
            <?php if ((float) $c['neto_exento']): ?><div class="d-flex justify-content-between"><span>Exento / no gravado</span><span><?= money($c['neto_exento']) ?></span></div><?php endif; ?>
            <div class="d-flex justify-content-between"><span>IVA</span><span><?= money((float) $c['iva_21'] + (float) $c['iva_105']) ?></span></div>
            <?php if ((float) $c['impuestos_internos']): ?><div class="d-flex justify-content-between"><span>Impuestos internos</span><span><?= money($c['impuestos_internos']) ?></span></div><?php endif; ?>
            <div class="d-flex justify-content-between total border-top pt-1"><span>Total</span><span><?= money($c['total']) ?></span></div>
            <?php if (puede('reportes.ver') && !$esNC): ?><div class="d-flex justify-content-between small text-body-secondary"><span>Costo / margen bruto</span><span><?= money($c['costo_total']) ?> / <?= money((float) $c['total'] - (float) $c['iva_21'] - (float) $c['iva_105'] - (float) $c['impuestos_internos'] - (float) $c['costo_total']) ?></span></div><?php endif; ?>
        </div></div>
        </div></div>
    </div>
</div>
