<?php use App\Services\RepartoService; use App\Support\Html; $e = $hr['estado']; ?>
<div class="encabezado"><h1>Hoja de ruta N° <?= (int) $hr['numero'] ?> <?= estado_badge($e) ?></h1>
    <div class="acciones">
        <a href="<?= url('/reparto/' . $hr['id'] . '/imprimir') ?>" target="_blank" class="btn btn-outline-secondary btn-touch"><i class="bi bi-printer"></i> Hoja de ruta</a>
        <a href="<?= url('/reparto/' . $hr['id'] . '/carga') ?>" target="_blank" class="btn btn-outline-secondary btn-touch"><i class="bi bi-boxes"></i> Carga del camión</a>
        <?php if ($e === 'armado' && puede('reparto.editar')): ?><?= Html::botonPost('/reparto/' . $hr['id'] . '/iniciar', '<i class="bi bi-truck"></i> Dar salida', 'btn-primary', '¿Confirmás la salida? Se transfiere la mercadería al camión ' . $hr['patente'] . '.') ?><?php endif; ?>
        <?php if ($e === 'en_reparto' && puede('reparto.editar')): ?><a href="<?= url('/reparto/' . $hr['id'] . '/rendicion') ?>" class="btn btn-success btn-touch"><i class="bi bi-clipboard-check"></i> Rendición</a><?php endif; ?>
        <?php if (in_array($e, ['armado', 'en_reparto'], true) && puede('reparto.anular')): ?><?= Html::botonPost('/reparto/' . $hr['id'] . '/anular', '<i class="bi bi-x-circle"></i> Anular', 'btn-outline-danger', '¿Anular la hoja de ruta? Sólo es posible si no hay entregas registradas.', true) ?><?php endif; ?>
    </div></div>
<div class="row g-3 mb-3">
    <div class="col-md-8"><div class="card shadow-sm h-100"><div class="card-body ficha-dato row g-2">
        <div class="col-6 col-md-3"><small>Fecha</small><?= fecha($hr['fecha']) ?></div><div class="col-6 col-md-3"><small>Chofer</small><?= e($hr['chofer']) ?></div>
        <div class="col-6 col-md-3"><small>Vehículo</small><?= e($hr['patente']) ?></div><div class="col-6 col-md-3"><small>Zona</small><?= e($hr['zona'] ?? 'Varias') ?></div>
        <div class="col-6 col-md-3"><small>Salida</small><?= fecha_hora($hr['salida_en']) ?: '—' ?></div><div class="col-6 col-md-3"><small>Rendida</small><?= fecha_hora($hr['rendida_en']) ?: '—' ?></div>
        <div class="col-12 col-md-6"><small>Depósito de carga</small><?= e($hr['deposito']) ?></div>
        <?php if ($hr['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= e($hr['observaciones']) ?></div><?php endif; ?>
    </div></div></div>
    <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
        <div class="card-titulo">Cobrado en el reparto</div>
        <?php foreach ($cobrado as $c): ?><div class="d-flex justify-content-between"><span><?= e(forma_pago($c['forma_pago'])) ?></span><strong><?= money($c['importe']) ?></strong></div><?php endforeach; ?>
        <?php if (!$cobrado): ?><p class="text-body-secondary mb-0">Sin cobros todavía.</p><?php endif; ?>
        <?php if ($rendicion): ?><hr><div class="small">Rendición: efectivo esperado <?= money($rendicion['efectivo_esperado']) ?>, rendido <?= money($rendicion['efectivo_rendido']) ?>, diferencia <strong class="<?= $rendicion['diferencia_efectivo'] < 0 ? 'text-danger' : '' ?>"><?= money($rendicion['diferencia_efectivo']) ?></strong>. Diferencia de mercadería (a costo): <?= money($rendicion['diferencia_mercaderia']) ?>.</div><?php endif; ?>
    </div></div></div>
</div>

<form method="post" action="<?= url('/reparto/' . $hr['id'] . '/paradas') ?>" id="formOrden"><?= csrf_campo() ?><input type="hidden" name="accion" value="ordenar"></form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table align-middle tabla-cards mb-0">
    <thead><tr><th>Orden</th><th>Cliente</th><th>Pedido</th><th class="num">Total</th><th>Estado</th><th>Detalle</th><th class="text-end">Acciones</th></tr></thead>
    <tbody><?php foreach ($paradas as $pa): ?>
        <tr>
            <td data-label="Orden"><?php if ($e === 'armado' && puede('reparto.editar')): ?><input form="formOrden" class="form-control form-control-sm text-center" style="width:70px" name="orden[<?= (int) $pa['id'] ?>]" value="<?= (int) $pa['orden'] ?>" inputmode="numeric" aria-label="Orden"><?php else: ?><span class="badge text-bg-secondary fs-6"><?= (int) $pa['orden'] ?></span><?php endif; ?></td>
            <td class="td-titulo"><?= e($pa['nombre_comercio']) ?><div class="small fw-normal text-body-secondary"><?= e($pa['direccion']) ?> · <?= e($pa['horario_entrega']) ?></div></td>
            <td data-label="Pedido"><a href="<?= url('/pedidos/' . $pa['pedido_id']) ?>">N° <?= (int) $pa['pedido_numero'] ?></a></td>
            <td class="num" data-label="Total"><?= money($pa['total']) ?></td>
            <td data-label="Estado"><?= estado_badge($pa['estado']) ?><?= (int) $pa['reprogramado'] ? ' <span class="badge text-bg-info">Reprogramado</span>' : '' ?></td>
            <td data-label="Detalle"><small><?= e($pa['motivo']) ?><?= $pa['comprobante_id'] ? '<a href="' . url('/ventas/' . $pa['comprobante_id']) . '">Comprobante ' . money($pa['comprobante_total']) . '</a>' : '' ?><?= $pa['recibo_id'] ? ' · Cobró ' . money($pa['recibo_total']) : '' ?></small></td>
            <td class="td-acciones text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary btn-touch" href="<?= e(link_maps($pa['direccion'], $pa['localidad'], $pa['latitud'], $pa['longitud'])) ?>" target="_blank" rel="noopener" aria-label="Mapa"><i class="bi bi-geo-alt"></i></a>
                <?php if ($e === 'armado' && puede('reparto.editar')): ?><?= Html::botonPost('/reparto/' . $hr['id'] . '/paradas', '<i class="bi bi-x"></i>', 'btn-sm btn-outline-danger', '¿Quitar el pedido de la hoja de ruta?', false, ['accion' => 'quitar', 'pedido_id' => $pa['pedido_id']]) ?><?php endif; ?>
                <?php if ($e === 'en_reparto' && $pa['estado'] === 'pendiente' && puede('reparto.editar')): ?><button type="button" class="btn btn-sm btn-primary btn-touch" data-bs-toggle="collapse" data-bs-target="#ent<?= (int) $pa['id'] ?>"><i class="bi bi-check2-square"></i> Registrar</button><?php endif; ?>
            </td>
        </tr>
        <?php if ($e === 'en_reparto' && $pa['estado'] === 'pendiente' && puede('reparto.editar')): ?>
        <tr class="collapse" id="ent<?= (int) $pa['id'] ?>"><td colspan="7">
            <form method="post" action="<?= url('/reparto/parada/' . $pa['id'] . '/entrega') ?>" class="row g-2 p-2 bg-body-tertiary rounded" x-data="{ r: 'entregado' }">
                <?= csrf_campo() ?>
                <div class="col-md-3"><label class="form-label small">Resultado</label><select class="form-select" name="resultado" x-model="r"><?= opciones(RepartoService::RESULTADOS, 'entregado') ?></select></div>
                <div class="col-md-3" x-show="r === 'rechazado' || r === 'parcial'"><label class="form-label small">Motivo</label><select class="form-select" name="motivo"><?= opciones(RepartoService::MOTIVOS_RECHAZO, '') ?></select></div>
                <div class="col-md-3" x-show="r !== 'cerrado'"><label class="form-label small">Cobro</label><div class="input-group"><select class="form-select" name="cobros[0][forma]" style="max-width:130px"><?= opciones(formas_pago(), 'efectivo') ?></select><input class="form-control" name="cobros[0][importe]" inputmode="decimal" placeholder="Importe"></div></div>
                <div class="col-12" x-show="r === 'parcial'"><div class="row g-1">
                    <?php foreach ($items[$pa['pedido_id']] ?? [] as $it): ?>
                        <div class="col-md-6 d-flex align-items-center gap-2"><span class="small flex-grow-1"><?= e($it['descripcion']) ?> (<?= cant($it['cantidad_base']) ?> <?= e($it['unidad_base']) ?>)</span><input class="form-control form-control-sm text-end" style="width:90px" name="items[<?= (int) $it['id'] ?>]" value="<?= e(cant($it['cantidad_base'])) ?>" inputmode="decimal" aria-label="Entregado"></div>
                    <?php endforeach; ?>
                </div></div>
                <div class="col-12"><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar entrega</button></div>
            </form>
        </td></tr>
        <?php endif; ?>
    <?php endforeach; ?></tbody>
</table></div></div>
<?php if ($e === 'armado' && puede('reparto.editar')): ?>
<div class="row g-3 mt-1">
    <div class="col-md-6"><button form="formOrden" class="btn btn-outline-primary btn-touch"><i class="bi bi-sort-numeric-down"></i> Guardar orden de paradas</button></div>
    <?php if ($disponibles): ?><div class="col-md-6"><form method="post" action="<?= url('/reparto/' . $hr['id'] . '/paradas') ?>" class="d-flex gap-2"><?= csrf_campo() ?><input type="hidden" name="accion" value="agregar">
        <select class="form-select" name="pedidos[]"><?= opciones($disponibles, '') ?></select><button class="btn btn-outline-primary btn-touch text-nowrap"><i class="bi bi-plus"></i> Agregar</button></form></div><?php endif; ?>
</div>
<?php endif; ?>
