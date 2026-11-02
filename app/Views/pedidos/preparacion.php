<div class="encabezado"><h1>Preparación de pedidos</h1></div>
<form class="filtros row g-2 align-items-end" method="get">
    <div class="col-6 col-md-3"><label class="form-label" for="entrega">Entrega hasta</label><input type="date" class="form-control" id="entrega" name="entrega" value="<?= e($_GET['entrega'] ?? '') ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="zona_id">Zona</label><select class="form-select" id="zona_id" name="zona_id" data-autoenviar><?= opciones($zonas, $_GET['zona_id'] ?? '', 'Todas') ?></select></div>
    <div class="col-12 col-md-3"><button class="btn btn-outline-primary w-100 btn-touch"><i class="bi bi-funnel"></i> Filtrar</button></div>
</form>
<form method="post" action="<?= url('/pedidos/preparacion') ?>" x-data="{ todos: false }" id="formPrep">
    <?= csrf_campo() ?>
    <div class="card shadow-sm"><div class="card-body p-0 p-md-2">
    <table class="table align-middle tabla-cards mb-0">
        <thead><tr><th><input type="checkbox" class="form-check-input" @change="document.querySelectorAll('.chk-ped').forEach(c => c.checked = $event.target.checked)" aria-label="Seleccionar todos"></th><th>N°</th><th>Cliente</th><th>Zona</th><th>Entrega</th><th class="num">Líneas</th><th class="num">Total</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($pedidos as $p): ?>
            <tr>
                <td data-label="Elegir"><input type="checkbox" class="form-check-input chk-ped" name="ids[]" value="<?= (int) $p['id'] ?>" aria-label="Elegir pedido <?= (int) $p['numero'] ?>"></td>
                <td data-label="N°"><a href="<?= url('/pedidos/' . $p['id']) ?>"><?= (int) $p['numero'] ?></a></td>
                <td class="td-titulo"><?= e($p['nombre_comercio']) ?></td>
                <td data-label="Zona"><?= e($p['zona'] ?? '') ?></td>
                <td data-label="Entrega"><?= fecha($p['fecha_entrega']) ?></td>
                <td class="num" data-label="Líneas"><?= (int) $p['lineas'] ?></td>
                <td class="num" data-label="Total"><?= money($p['total']) ?></td>
                <td data-label="Estado"><?= estado_badge($p['estado']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pedidos): ?><tr><td colspan="8" class="text-center text-body-secondary py-4">No hay pedidos pendientes de preparar.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div></div>
    <div class="barra-acciones-fija d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary btn-touch" onclick="var ids=[...document.querySelectorAll('.chk-ped:checked')].map(c=>'ids[]='+c.value).join('&'); if(!ids){DT.aviso('Seleccioná pedidos','aviso');return;} window.open(DT.url('/pedidos/picking?'+ids),'_blank')"><i class="bi bi-printer"></i> Hoja de preparación</button>
        <button type="submit" name="estado" value="en_preparacion" class="btn btn-outline-primary btn-touch"><i class="bi bi-box2"></i> En preparación</button>
        <button type="submit" name="estado" value="preparado" class="btn btn-primary btn-touch"><i class="bi bi-box2-fill"></i> Marcar preparados</button>
        <?php if (puede('reparto.crear')): ?><a href="<?= url('/reparto/nueva') ?>" class="btn btn-outline-primary btn-touch ms-md-auto"><i class="bi bi-truck"></i> Armar hoja de ruta</a><?php endif; ?>
    </div>
</form>
