<?php
/**
 * Líneas del editor de documentos. Variables: $conPrecio, $conDescuento, $conBonificacion, $conLotes, $etiquetaPrecio
 */
$conPrecio = $conPrecio ?? true;
$conDescuento = $conDescuento ?? false;
$conBonificacion = $conBonificacion ?? false;
$conLotes = $conLotes ?? false;
$etiquetaPrecio = $etiquetaPrecio ?? 'Precio';
?>
<div class="lineas-doc">
    <template x-if="!lineas.length">
        <div class="text-center text-body-secondary py-4"><i class="bi bi-cart fs-1 d-block"></i>Buscá productos o escaneá el código de barras para agregarlos.</div>
    </template>
    <template x-for="(l, i) in lineas" :key="i">
        <div class="linea">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-4 min-w-0">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate" x-text="l.descripcion"></div>
                            <small class="text-body-secondary"><span x-text="l.codigo"></span>
                                <template x-if="l.disponible !== undefined"><span :class="sinStock(l) ? 'text-danger fw-bold' : ''"> · Disp. <span x-text="DT.cant(l.disponible)"></span> <span x-text="l.unidad"></span></span></template>
                            </small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger d-lg-none" @click="quitar(i)" aria-label="Quitar"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="col-5 col-lg-2">
                    <select class="form-select form-select-sm" :value="l.presentacion_id" @change="cambiarPresentacion(l, $event.target.value)" aria-label="Presentación">
                        <template x-for="p in l.presentaciones" :key="p.id"><option :value="p.id" :selected="p.id == l.presentacion_id" x-text="p.nombre + (p.factor != 1 ? ' x' + DT.cant(p.factor) : '')"></option></template>
                    </select>
                </div>
                <div class="col-7 col-lg-2">
                    <div class="cantidad-control">
                        <button type="button" class="btn btn-outline-secondary" @click="sumar(l, -1)" aria-label="Restar">−</button>
                        <input type="text" class="form-control" inputmode="decimal" x-model="l.cantidad" aria-label="Cantidad" @focus="$event.target.select()">
                        <button type="button" class="btn btn-outline-secondary" @click="sumar(l, 1)" aria-label="Sumar">+</button>
                    </div>
                    <small class="text-body-secondary" x-show="l.factor != 1" x-text="'= ' + DT.cant(unidades(l)) + ' ' + (l.unidad || 'u.')"></small>
                </div>
                <?php if ($conPrecio): ?>
                <div class="col-6 col-lg-1">
                    <label class="small text-body-secondary d-lg-none"><?= e($etiquetaPrecio) ?></label>
                    <input type="text" class="form-control form-control-sm text-end" inputmode="decimal" x-model="l.precio" :readonly="!<?= !empty($precioEditable) ? 'true' : 'false' ?>" aria-label="<?= e($etiquetaPrecio) ?>">
                </div>
                <?php endif; ?>
                <?php if ($conDescuento): ?>
                <div class="col-3 col-lg-1">
                    <label class="small text-body-secondary d-lg-none">Desc. %</label>
                    <input type="text" class="form-control form-control-sm text-end" inputmode="decimal" x-model="l.descuento" aria-label="Descuento %">
                </div>
                <?php endif; ?>
                <?php if ($conBonificacion): ?>
                <div class="col-3 col-lg-1">
                    <label class="small text-body-secondary d-lg-none">Bonif. u.</label>
                    <input type="text" class="form-control form-control-sm text-end" inputmode="numeric" x-model="l.bonificacion" title="Unidades sin cargo" aria-label="Unidades bonificadas">
                </div>
                <?php endif; ?>
                <?php if ($conLotes): ?>
                <div class="col-6 col-lg-2" x-show="l.lotes">
                    <input type="text" class="form-control form-control-sm mb-1" placeholder="Lote" x-model="l.lote" aria-label="Lote">
                    <input type="date" class="form-control form-control-sm" x-model="l.vencimiento" aria-label="Vencimiento">
                </div>
                <?php endif; ?>
                <?php if ($conPrecio): ?>
                <div class="col-6 col-lg text-end fw-bold text-monto" x-text="DT.dinero(importe(l))"></div>
                <?php endif; ?>
                <div class="col-auto d-none d-lg-block ms-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="quitar(i)" aria-label="Quitar"><i class="bi bi-trash"></i></button></div>
            </div>
            <div class="small text-danger" x-show="sinStock(l)"><i class="bi bi-exclamation-triangle"></i> Supera el stock disponible.</div>
        </div>
    </template>
</div>
