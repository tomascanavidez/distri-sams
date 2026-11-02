<?php
use App\Services\VentaService;
use App\Support\Html;
$wa = whatsapp_numero($c['whatsapp'] ?: $c['telefono']);
$msg = 'Hola ' . ($c['titular'] ?: $c['nombre_comercio']) . ', te escribimos de ' . ajuste('empresa_nombre') . '.' . ($resumen['saldo'] > 0 ? ' Te recordamos que tu saldo es de ' . money($resumen['saldo']) . '.' : '');
?>
<div class="encabezado">
    <h1><?= e($c['nombre_comercio']) ?> <?= estado_badge($c['estado']) ?></h1>
    <div class="acciones">
        <?php if (puede('pedidos.crear')): ?><a href="<?= url('/pedidos/nuevo?cliente_id=' . $c['id']) ?>" class="btn btn-primary btn-touch"><i class="bi bi-cart-plus"></i> Nuevo pedido</a><?php endif; ?>
        <?php if (puede('cobranzas.crear')): ?><a href="<?= url('/cobranzas/nueva?cliente_id=' . $c['id']) ?>" class="btn btn-outline-success btn-touch"><i class="bi bi-cash-coin"></i> Cobrar</a><?php endif; ?>
        <a href="<?= url('/clientes/' . $c['id'] . '/cuenta') ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-journal-text"></i> Cuenta corriente</a>
        <?php if (puede('clientes.editar')): ?><a href="<?= url('/clientes/' . $c['id'] . '/editar') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-3 lista-acciones-grandes">
    <?php if ($c['telefono']): ?><a class="btn btn-outline-secondary flex-fill" href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['telefono'])) ?>"><i class="bi bi-telephone-fill"></i> Llamar</a><?php endif; ?>
    <?php if ($wa): ?><a class="btn btn-success flex-fill" target="_blank" rel="noopener" href="https://wa.me/<?= $wa ?>?text=<?= rawurlencode($msg) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a><?php endif; ?>
    <a class="btn btn-outline-primary flex-fill" target="_blank" rel="noopener" href="<?= e(link_maps($c['direccion'], $c['localidad'], $c['latitud'], $c['longitud'])) ?>"><i class="bi bi-geo-alt-fill"></i> Cómo llegar</a>
</div>
<?php if ($problemas): ?><div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle"></i> <?= e(implode(' ', $problemas)) ?></div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="row g-2 text-center mb-2">
                <div class="col-6"><div class="kpi-etiqueta">Saldo</div><div class="kpi-valor fs-4 <?= $resumen['saldo'] > 0 ? 'saldo-deuda' : 'saldo-favor' ?>"><?= money($resumen['saldo']) ?></div></div>
                <div class="col-6"><div class="kpi-etiqueta">Vencido</div><div class="kpi-valor fs-4 <?= $resumen['vencido'] > 0 ? 'text-danger' : '' ?>"><?= money($resumen['vencido']) ?></div></div>
            </div>
            <div class="small d-flex justify-content-between"><span>Límite de crédito</span><strong><?= $resumen['limite'] > 0 ? money($resumen['limite']) : 'Sin límite' ?></strong></div>
            <?php if ($resumen['disponible'] !== null): ?><div class="small d-flex justify-content-between"><span>Disponible</span><strong class="<?= $resumen['disponible'] < 0 ? 'text-danger' : '' ?>"><?= money($resumen['disponible']) ?></strong></div><?php endif; ?>
            <div class="small d-flex justify-content-between"><span>Compras últimos 90 días</span><strong><?= money($compras90) ?></strong></div>
            <hr>
            <div class="small fw-semibold mb-1">Antigüedad de la deuda</div>
            <?php foreach (['a_vencer' => 'A vencer', 'd30' => '1 a 30 días', 'd60' => '31 a 60 días', 'd90' => '61 a 90 días', 'mas90' => 'Más de 90 días'] as $k => $t): ?>
                <div class="small d-flex justify-content-between"><span><?= $t ?></span><span class="<?= $k !== 'a_vencer' && ($antiguedad[$k] ?? 0) > 0 ? 'text-danger fw-bold' : '' ?>"><?= money($antiguedad[$k] ?? 0) ?></span></div>
            <?php endforeach; ?>
        </div></div>
        <div class="card shadow-sm mb-3"><div class="card-body ficha-dato row g-2">
            <div class="col-6"><small>Código</small><?= e($c['codigo']) ?></div><div class="col-6"><small>Tipo</small><?= e($c['tipo_comercio']) ?></div>
            <div class="col-12"><small>Titular</small><?= e($c['titular']) ?></div>
            <div class="col-6"><small><?= e($c['tipo_documento']) ?></small><?= e($c['documento']) ?></div><div class="col-6"><small>IVA</small><?= e(condicion_iva($c['condicion_iva'])) ?></div>
            <div class="col-12"><small>Dirección</small><?= e($c['direccion']) ?>, <?= e($c['localidad']) ?></div>
            <div class="col-6"><small>Zona</small><?= e($c['zona'] ?? '—') ?></div><div class="col-6"><small>Preventista</small><?= e($c['preventista'] ?? '—') ?></div>
            <div class="col-6"><small>Visita</small><?= e(dias_texto($c['dias_visita'])) ?> <?= e($c['horario_visita']) ?></div><div class="col-6"><small>Entrega</small><?= e(dias_texto($c['dias_entrega'])) ?> <?= e($c['horario_entrega']) ?></div>
            <div class="col-6"><small>Lista</small><?= e($c['lista'] ?? '—') ?></div><div class="col-6"><small>Pago</small><?= $c['condicion_pago'] === 'cta_cte' ? 'Cta. cte. ' . (int) $c['dias_credito'] . ' días' : 'Contado' ?></div>
            <div class="col-6"><small>Última visita</small><?= fecha_hora($c['ultima_visita']) ?: '—' ?></div><div class="col-6"><small>Última compra</small><?= fecha_hora($c['ultima_compra']) ?: '—' ?></div>
            <?php if ($c['observaciones']): ?><div class="col-12"><small>Observaciones</small><?= nl2br(e($c['observaciones'])) ?></div><?php endif; ?>
        </div></div>
        <?php if (puede('clientes.editar')): ?>
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo">Estado del cliente</div>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach (['activo' => 'Activar', 'bloqueado' => 'Bloquear por deuda', 'inactivo' => 'Desactivar'] as $est => $t): if ($est === $c['estado']) continue; ?>
                    <?= Html::botonPost('/clientes/' . $c['id'] . '/estado', $t, $est === 'activo' ? 'btn-outline-success' : 'btn-outline-danger', '¿Cambiar el estado del cliente a "' . $est . '"?', false, ['estado' => $est]) ?>
                <?php endforeach; ?>
            </div>
        </div></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="card-titulo">Productos que compra habitualmente</div>
            <table class="table table-sm tabla-cards mb-0"><thead><tr><th>Producto</th><th class="num">Unidades</th><th class="num">Total</th><th>Última</th></tr></thead><tbody>
            <?php foreach ($top as $t): ?><tr><td class="td-titulo"><?= e($t['descripcion']) ?></td><td class="num" data-label="Unidades"><?= cant($t['unidades']) ?></td><td class="num" data-label="Total"><?= money($t['total']) ?></td><td data-label="Última"><?= fecha($t['ultima']) ?></td></tr><?php endforeach; ?>
            <?php if (!$top): ?><tr><td colspan="4" class="text-body-secondary">Todavía no compró.</td></tr><?php endif; ?></tbody></table>
        </div></div>
        <div class="row g-3">
            <div class="col-md-6"><div class="card shadow-sm h-100"><div class="card-body">
                <div class="card-titulo d-flex justify-content-between">Últimos pedidos <a class="small fw-normal" href="<?= url('/pedidos?q=' . rawurlencode($c['codigo'])) ?>">Ver todos</a></div>
                <?php foreach ($pedidos as $p): ?><div class="d-flex justify-content-between border-bottom py-1"><a href="<?= url('/pedidos/' . $p['id']) ?>">N° <?= (int) $p['numero'] ?> · <?= fecha($p['fecha']) ?></a><span><?= money($p['total']) ?> <?= estado_badge($p['estado']) ?></span></div><?php endforeach; ?>
                <?php if (!$pedidos): ?><p class="text-body-secondary mb-0">Sin pedidos.</p><?php endif; ?>
            </div></div></div>
            <div class="col-md-6"><div class="card shadow-sm h-100"><div class="card-body">
                <div class="card-titulo d-flex justify-content-between">Últimos comprobantes <a class="small fw-normal" href="<?= url('/ventas?cliente_id=' . $c['id']) ?>">Ver todos</a></div>
                <?php foreach ($comprobantes as $cp): ?><div class="d-flex justify-content-between border-bottom py-1"><a href="<?= url('/ventas/' . $cp['id']) ?>"><?= e(VentaService::numero($cp)) ?></a><span><?= money($cp['total']) ?> <?= $cp['estado'] === 'anulado' ? estado_badge('anulado') : '' ?></span></div><?php endforeach; ?>
                <?php if (!$comprobantes): ?><p class="text-body-secondary mb-0">Sin comprobantes.</p><?php endif; ?>
            </div></div></div>
            <div class="col-12"><div class="card shadow-sm"><div class="card-body">
                <div class="card-titulo">Últimas visitas del preventista</div>
                <?php foreach ($visitas as $v): ?><div class="d-flex justify-content-between border-bottom py-1 small"><span><?= fecha_hora($v['fecha']) ?> · <?= e($v['preventista']) ?></span><span><?= $v['resultado'] === 'pedido' ? '<span class="text-success">Con pedido</span>' : 'Sin pedido: ' . e(str_replace('_', ' ', (string) $v['motivo'])) ?></span></div><?php endforeach; ?>
                <?php if (!$visitas): ?><p class="text-body-secondary mb-0">Sin visitas registradas.</p><?php endif; ?>
            </div></div></div>
        </div>
    </div>
</div>
