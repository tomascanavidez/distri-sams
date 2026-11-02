<div class="encabezado"><h1>Actualización masiva de precios</h1></div>
<div class="card shadow-sm" style="max-width:820px"><div class="card-body">
    <p class="text-body-secondary">Aplicá un porcentaje de aumento (o rebaja con signo negativo) a un grupo de productos. Todos los cambios quedan en el historial de precios.</p>
    <form method="post" class="row g-3" x-data="{ tipo: 'costo' }" data-confirmar="¿Aplicar la actualización a todos los productos del filtro? Esta acción modifica precios de forma masiva." data-boton="Aplicar">
        <?= csrf_campo() ?>
        <div class="col-12"><label class="form-label">¿Qué se actualiza?</label>
            <div class="d-flex flex-column flex-md-row gap-2">
                <input type="radio" class="btn-check" name="tipo" id="tipo_costo" value="costo" checked x-model="tipo"><label class="btn btn-outline-primary btn-touch text-start" for="tipo_costo"><strong>Costos</strong><br><small>Sube el costo y recalcula los precios por margen (lo habitual cuando el proveedor aumenta).</small></label>
                <input type="radio" class="btn-check" name="tipo" id="tipo_precio" value="precio" x-model="tipo"><label class="btn btn-outline-primary btn-touch text-start" for="tipo_precio"><strong>Precios de venta</strong><br><small>Sube los precios de una lista (o de todas) sin tocar el costo.</small></label>
                <input type="radio" class="btn-check" name="tipo" id="tipo_margen" value="margen" x-model="tipo"><label class="btn btn-outline-primary btn-touch text-start" for="tipo_margen"><strong>Fijar margen</strong><br><small>Pone un margen propio (ej. 30 %) a estos productos en una lista. Vacío = vuelven al margen de la lista.</small></label>
            </div></div>
        <div class="col-md-4"><label class="form-label" for="rubro_id">Rubro</label><select class="form-select" id="rubro_id" name="rubro_id"><?= opciones($rubros, '', 'Todos') ?></select></div>
        <div class="col-md-4"><label class="form-label" for="marca_id">Marca</label><select class="form-select" id="marca_id" name="marca_id"><?= opciones($marcas, '', 'Todas') ?></select></div>
        <div class="col-md-4"><label class="form-label" for="proveedor_id">Proveedor</label><select class="form-select" id="proveedor_id" name="proveedor_id"><?= opciones($proveedores, '', 'Todos') ?></select></div>
        <div class="col-md-4"><label class="form-label" for="lista_id" x-text="tipo === 'margen' ? 'Lista (obligatoria)' : 'Lista (sólo para precios)'">Lista (sólo para precios)</label><select class="form-select" id="lista_id" name="lista_id"><?= opciones($listas, '', 'Todas las listas') ?></select></div>
        <div class="col-md-3" x-show="tipo !== 'margen'"><label class="form-label" for="porcentaje">Porcentaje de aumento <span class="text-danger">*</span></label>
            <div class="input-group"><input class="form-control" id="porcentaje" name="porcentaje" inputmode="decimal" :required="tipo !== 'margen'" placeholder="Ej.: 8,5"><span class="input-group-text">%</span></div></div>
        <div class="col-md-3" x-show="tipo === 'margen'" x-cloak><label class="form-label" for="margen">Margen sobre costo</label>
            <div class="input-group"><input class="form-control" id="margen" name="margen" inputmode="decimal" placeholder="Ej.: 30"><span class="input-group-text">%</span></div></div>
        <div class="col-md-5"><label class="form-label" for="motivo">Motivo</label><input class="form-control" id="motivo" name="motivo" maxlength="150" placeholder="Ej.: Lista nueva Arcor octubre"></div>
        <div class="col-12"><button class="btn btn-primary btn-touch" type="submit"><i class="bi bi-lightning-charge"></i> Aplicar actualización</button></div>
    </form>
</div></div>
