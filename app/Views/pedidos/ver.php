<?php
use App\Services\PedidoService;
use App\Support\Html;

$e = $p['estado'];
?>
<div class="encabezado">
    <h1>Pedido N° <?= (int) $p['numero'] ?> <?= estado_badge($e) ?></h1>
    <div class="acciones">
        <a href="<?= url('/pedidos') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i></a>
        <a href="<?= url('/pedidos/' . $p['id'] . '/imprimir') ?>" target="_blank" class="btn btn-outline-secondary btn-touch"><i class="bi bi-printer"></i> Imprimir</a>
        <?php if (in_array($e, ['borrador', 'confirmado', 'en_preparacion'], true) && puede('pedidos.editar|pedidos.crear')): ?>
            <a href="<?= url('/pedidos/' . $p['id'] . '/editar') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-pencil"></i> Editar</a>
        <?php endif; ?>
        <?php if ($e === 'borrador' && puede('pedidos.crear|pedidos.editar')): ?>
            <?= Html::botonPost('/pedidos/' . $p['id'] . '/confirmar', '<i class="bi bi-check2-circle"></i> Confirmar', 'btn-primary', $credito ? 'Atención: ' . implode(' ', $credito) . ' ¿Confirmar igual?' : null, false, $credito ? ['forzar' => 1] : []) ?>
        <?php endif; ?>
        <?php if ($e === 'confirmado' && puede('pedidos.editar')): ?><?= Html::botonPost('/pedidos/' . $p['id'] . '/estado', '<i class="bi bi-box2"></i> A preparación', 'btn-outline-primary', null, false, ['estado' => 'en_preparacion']) ?><?php endif; ?>
        <?php if ($e === 'en_preparacion' && puede('pedidos.editar')): ?><?= Html::botonPost('/pedidos/' . $p['id'] . '/estado', '<i class="bi bi-box2-fill"></i> Marcar preparado', 'btn-primary', null, false, ['estado' => 'preparado']) ?><?php endif; ?>
        <?php if (in_array($e, ['borrador', 'confirmado', 'en_preparacion', 'preparado'], true) && !$p['hoja_ruta_id'] && puede('pedidos.anular')): ?>
            <?= Html::botonPost('/pedidos/' . $p['id'] . '/anular', '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', '¿Anular el pedido? Se libera el stock reservado.', true) ?>
        <?php endif; ?>
    </div>
</div>

<?php if ((int) $p['revision_requerida'] && $e === 'borrador'): ?>
    <div class="alert alert-warning"><strong><i class="bi bi-phone"></i> Pedido cargado desde la app que necesita revisión:</strong>
        <ul class="mb-0 mt-1"><?php foreach ((array) json_decode((string) $p['revision_detalle'], true) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
        <div class="small mt-1">Revisá cantidades o precios con <em>Editar</em> y luego confirmalo.</div>
    </div>
<?php endif; ?>
<?php if ($credito && in_array($e, ['borrador', 'confirmado'], true)): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon"></i> <?= e(implode(' ', $credito)) ?></div><?php endif; ?>
<?php if ($p['motivo_anulacion']): ?><div class="alert alert-secondary py-2">Motivo de anulación: <?= e($p['motivo_anulacion']) ?></div><?php endif; ?>
<?php if ($p['motivo_rechazo']): ?><div class="alert alert-danger py-2">Rechazado en la entrega: <?= e($p['motivo_rechazo']) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-body ficha-dato row g-2">
            <div class="col-12"><small>Cliente</small><a href="<?= url('/clientes/' . $p['cliente_id']) ?>" class="fw-bold"><?= e($p['nombre_comercio']) ?></a><br><span class="small"><?= e($p['direccion']) ?> · <?= e($p['zona']) ?></span></div>
            <div class="col-6"><small>Fecha</small><?= fecha_hora($p['fecha']) ?></div>
            <div class="col-6"><small>Entrega</small><?= fecha($p['fecha_entrega']) ?></div>
            <div class="col-6"><small>Preventista</small><?= e($p['preventista'] ?? '—') ?></div>
            <div class="col-6"><small>Cargó</small><?= e($p['usuario'] ?? '') ?> <?= $p['origen'] === 'movil' ? '<i class="bi bi-phone" title="App"></i>' : '' ?></div>
            <div class="col-6"><small>Lista</small><?= e($p['lista'] ?? '') ?></div>
            <div class="col-6"><small>Depósito</small><?= e($p['deposito']) ?></div>
            <div class="col-6"><small>Condición</small><?= $p['condicion_pago'] === 'cta_cte' ? 'Cuenta corriente' : 'Contado' ?></div>
            <?php if ($p['hr_numero']): ?><div class="col-6"><small>Hoja de ruta</small><a href="<?= url('/reparto/' . $p['hoja_ruta_id']) ?>">N° <?= (int) $p['hr_numero'] ?></a></div><?php endif; ?>
            <?php if ($comprobante): ?><div class="col-12"><small>Comprobante</small><a href="<?= url('/ventas/' . $comprobante['id']) ?>"><?= e(App\Services\VentaService::numero($comprobante)) ?></a> <?= estado_badge($comprobante['estado']) ?></div><?php endif; ?>
            <?php if ($p['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($p['observaciones']) ?></div><?php endif; ?>
        </div></div>
        <?php if (in_array($e, ['borrador', 'confirmado', 'en_preparacion', 'preparado'], true) && !$p['hoja_ruta_id'] && puede('ventas.crear')): ?>
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo"><i class="bi bi-receipt"></i> Convertir en venta</div>
            <p class="small text-body-secondary">Para pedidos que el cliente retira o se facturan desde la oficina (sin reparto).</p>
            <form method="post" action="<?= url('/pedidos/' . $p['id'] . '/facturar') ?>" class="row g-2" data-confirmar="¿Emitir el comprobante de este pedido?">
                <?= csrf_campo() ?>
                <?php if ($credito): ?><input type="hidden" name="forzar" value="1"><?php endif; ?>
                <div class="col-12"><select class="form-select" name="clase"><option value="factura">Factura</option><option value="remito">Remito</option></select></div>
                <div class="col-6"><select class="form-select" name="pago_forma"><?= opciones(formas_pago(), 'efectivo') ?></select></div>
                <div class="col-6"><input class="form-control" name="pago_importe" inputmode="decimal" placeholder="Cobro (opcional)"></div>
                <div class="col-12"><button class="btn btn-primary btn-touch w-100"><i class="bi bi-receipt"></i> Emitir comprobante</button></div>
            </form>
        </div></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm"><div class="card-body">
            <table class="table align-middle tabla-cards mb-0">
                <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Bonif.</th><th class="num">Precio</th><th class="num">Desc.</th><th class="num">Subtotal</th><?php if (in_array($e, ['entregado', 'entregado_parcial'], true)): ?><th class="num">Entregado</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="td-titulo"><?= e($it['descripcion']) ?><div class="small text-body-secondary fw-normal"><?= e($it['codigo']) ?></div></td>
                        <td class="num" data-label="Cantidad"><?= cant($it['cantidad']) ?> <?= e($it['presentacion_nombre']) ?><?= (float) $it['factor'] != 1 ? '<br><small class="text-body-secondary">' . cant($it['cantidad_base']) . ' u.</small>' : '' ?></td>
                        <td class="num" data-label="Bonificación"><?= (float) $it['bonificacion_base'] > 0 ? cant($it['bonificacion_base']) . ' u.' : '—' ?></td>
                        <td class="num" data-label="Precio"><?= money($it['precio_unitario']) ?></td>
                        <td class="num" data-label="Desc."><?= (float) $it['descuento_porc'] > 0 ? num($it['descuento_porc'], 1) . ' %' : '—' ?></td>
                        <td class="num fw-semibold" data-label="Subtotal"><?= money($it['subtotal']) ?></td>
                        <?php if (in_array($e, ['entregado', 'entregado_parcial'], true)): ?><td class="num <?= (float) $it['entregado_base'] < (float) $it['cantidad_base'] ? 'text-danger fw-bold' : '' ?>" data-label="Entregado"><?= cant($it['entregado_base']) ?> u.</td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php if ((float) $p['descuento_importe'] > 0): ?><tr><td colspan="5" class="text-end">Descuento general (<?= num($p['descuento_porc'], 1) ?> %)</td><td class="num">- <?= money($p['descuento_importe']) ?></td></tr><?php endif; ?>
                    <tr class="fs-5"><td colspan="5" class="text-end fw-bold">Total</td><td class="num fw-bold"><?= money($p['total']) ?></td></tr>
                </tfoot>
            </table>
        </div></div>
    </div>
</div>
