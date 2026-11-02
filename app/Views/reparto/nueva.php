<div class="encabezado"><h1>Nueva hoja de ruta</h1></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="zona_id">Zona</label><select class="form-select" id="zona_id" name="zona_id" data-autoenviar><?= opciones($zonas, $_GET['zona_id'] ?? '', 'Todas') ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="entrega">Entrega hasta</label><input type="date" class="form-control" id="entrega" name="entrega" value="<?= e($_GET['entrega'] ?? '') ?>" onchange="this.form.submit()"></div>
</form>
<form method="post" action="<?= url('/reparto/nueva') ?>" x-data="{ n: 0, total: 0, unidades: 0, calc() { const s = [...document.querySelectorAll('.chk-p:checked')]; this.n = s.length; this.total = s.reduce((a, c) => a + Number(c.dataset.total), 0); this.unidades = s.reduce((a, c) => a + Number(c.dataset.u), 0); } }">
    <?= csrf_campo() ?>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card shadow-sm"><div class="card-body p-0 p-md-2">
            <table class="table align-middle tabla-cards mb-0">
                <thead><tr><th><input type="checkbox" class="form-check-input" aria-label="Todos" @change="document.querySelectorAll('.chk-p').forEach(c => c.checked = $event.target.checked); calc()"></th><th>Pedido</th><th>Cliente</th><th>Zona</th><th>Entrega</th><th class="num">Unid.</th><th class="num">Total</th><th>Estado</th></tr></thead>
                <tbody><?php foreach ($pedidos as $p): ?>
                    <tr><td data-label="Incluir"><input type="checkbox" class="form-check-input chk-p" name="pedidos[]" value="<?= (int) $p['id'] ?>" data-total="<?= (float) $p['total'] ?>" data-u="<?= (float) $p['unidades'] ?>" @change="calc()" <?= $p['estado'] === 'preparado' ? '' : '' ?> aria-label="Incluir"></td>
                        <td data-label="Pedido"><a href="<?= url('/pedidos/' . $p['id']) ?>" target="_blank">N° <?= (int) $p['numero'] ?></a></td><td class="td-titulo"><?= e($p['nombre_comercio']) ?><div class="small fw-normal text-body-secondary"><?= e($p['direccion']) ?></div></td>
                        <td data-label="Zona"><?= e($p['zona']) ?></td><td data-label="Entrega"><?= fecha($p['fecha_entrega']) ?></td><td class="num" data-label="Unidades"><?= cant($p['unidades']) ?></td>
                        <td class="num" data-label="Total"><?= money($p['total']) ?></td><td data-label="Estado"><?= estado_badge($p['estado']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$pedidos): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay pedidos confirmados o preparados sin hoja de ruta.</td></tr><?php endif; ?></tbody>
            </table>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm"><div class="card-body row g-2">
            <div class="col-6"><label class="form-label">Fecha</label><input type="date" class="form-control" name="fecha" value="<?= hoy() ?>"></div>
            <div class="col-6"><label class="form-label">Zona</label><select class="form-select" name="zona_id"><?= opciones($zonas, $_GET['zona_id'] ?? '', 'Varias') ?></select></div>
            <div class="col-12"><label class="form-label">Vehículo <span class="text-danger">*</span></label><select class="form-select" name="vehiculo_id" required><?= opciones($vehiculos, '') ?></select></div>
            <div class="col-12"><label class="form-label">Chofer <span class="text-danger">*</span></label><select class="form-select" name="chofer_id" required><?= opciones($choferes, '') ?></select></div>
            <div class="col-12"><label class="form-label">Observaciones</label><input class="form-control" name="observaciones"></div>
            <div class="col-12 border-top pt-2"><div class="d-flex justify-content-between"><span>Pedidos</span><strong x-text="n"></strong></div>
                <div class="d-flex justify-content-between"><span>Unidades</span><strong x-text="DT.cant(unidades)"></strong></div>
                <div class="d-flex justify-content-between"><span>Total a entregar</span><strong x-text="DT.dinero(total)"></strong></div></div>
            <div class="col-12"><button class="btn btn-primary btn-touch w-100" :disabled="!n"><i class="bi bi-truck"></i> Crear hoja de ruta</button></div>
        </div></div></div>
    </div>
</form>
