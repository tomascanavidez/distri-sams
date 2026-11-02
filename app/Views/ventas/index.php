<?php
use App\Services\VentaService;
use App\Support\Html;
$esPR = ($_GET['tipo'] ?? '') === 'PR';
?>
<div class="encabezado">
    <h1><?= e($titulo) ?></h1>
    <div class="acciones">
        <?php if (puede('ventas.exportar')): ?><?= Html::exportar('/ventas/exportar') ?><?php endif; ?>
        <?php if (puede('ventas.crear')): ?>
            <a href="<?= url('/ventas/nueva' . ($esPR ? '?clase=presupuesto' : '')) ?>" class="btn btn-outline-primary btn-touch"><i class="bi bi-plus-lg"></i> <?= $esPR ? 'Nuevo presupuesto' : 'Nueva venta' ?></a>
            <a href="<?= url('/ventas/pos') ?>" class="btn btn-primary btn-touch"><i class="bi bi-upc-scan"></i> Mostrador</a>
        <?php endif; ?>
    </div>
</div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-12 col-lg-3"><label class="form-label" for="q">Cliente o número</label><input type="search" class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="tipo">Tipo</label><select class="form-select" id="tipo" name="tipo" data-autoenviar><?= opciones(['facturas' => 'Facturas', 'notas' => 'Notas de crédito/débito', 'RM' => 'Remitos', 'PR' => 'Presupuestos'] + tipos_comprobante(), $_GET['tipo'] ?? '', 'Todos (sin presupuestos)') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="origen">Origen</label><select class="form-select" id="origen" name="origen" data-autoenviar><?= opciones(['mostrador' => 'Mostrador', 'reparto' => 'Reparto', 'pedido' => 'Pedido', 'oficina' => 'Oficina'], $_GET['origen'] ?? '', 'Todos') ?></select></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($_GET['desde'] ?? '') ?>"></div>
    <div class="col-6 col-lg-2"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($_GET['hasta'] ?? '') ?>"></div>
    <div class="col-6 col-lg-1"><button class="btn btn-outline-primary w-100 btn-touch" aria-label="Filtrar"><i class="bi bi-search"></i></button></div>
</form>
<div class="card shadow-sm"><div class="card-body p-0 p-md-2">
<table class="table table-hover align-middle tabla-cards mb-0">
    <thead><tr><?= $pag->th('fecha', 'Fecha') ?><th>Comprobante</th><?= $pag->th('cliente', 'Cliente') ?><th>Origen</th><th>Condición</th><?= $pag->th('total', 'Total', 'num') ?><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pag->items as $c): ?>
        <tr class="<?= $c['estado'] === 'anulado' ? 'text-decoration-line-through opacity-75' : '' ?>">
            <td data-label="Fecha"><?= fecha_hora($c['fecha']) ?></td>
            <td class="td-titulo"><a href="<?= url('/ventas/' . $c['id']) ?>"><?= e(VentaService::numero($c)) ?></a></td>
            <td data-label="Cliente"><?= e($c['cliente_nombre']) ?></td>
            <td data-label="Origen"><?= e(ucfirst($c['origen'])) ?></td>
            <td data-label="Condición"><?= $c['condicion_venta'] === 'cta_cte' ? 'Cta. cte.' : 'Contado' ?></td>
            <td class="num fw-semibold" data-label="Total"><?= str_starts_with($c['tipo'], 'NC') ? '-' : '' ?><?= money($c['total']) ?></td>
            <td data-label="Estado"><?= estado_badge($c['estado']) ?></td>
            <td class="td-acciones text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/ventas/' . $c['id']) ?>"><i class="bi bi-eye"></i></a>
                <a class="btn btn-sm btn-outline-secondary btn-touch" target="_blank" href="<?= url('/ventas/' . $c['id'] . '/imprimir') ?>" aria-label="Imprimir"><i class="bi bi-printer"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pag->items): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay comprobantes.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total emitido (<?= (int) $totales['n'] ?>)</td><td class="num"><?= money($totales['total']) ?></td><td colspan="2"></td></tr></tfoot>
</table>
</div></div>
<?= $pag->enlaces() ?>
