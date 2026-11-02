<?php
use App\Support\Html;

$pres = array_values(array_map(fn ($p) => [
    'id' => (int) ($p['id'] ?? 0), 'nombre' => $p['nombre'], 'factor' => (float) $p['factor'], 'barras' => $p['codigo_barras'] ?? '',
    'venta' => (int) ($p['es_venta'] ?? 1), 'compra' => (int) ($p['es_compra'] ?? 1),
    'pv' => (int) ($p['predeterminada_venta'] ?? 0), 'pc' => (int) ($p['predeterminada_compra'] ?? 0),
], $datos['presentaciones'] ?? []));
?>
<div class="encabezado">
    <h1><?= e($titulo) ?></h1>
    <div class="acciones"><a href="<?= url($id ? '/productos/' . $id : '/productos') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Volver</a></div>
</div>
<?= App\Core\View::parcial('partials/flash', ['errores' => $errores]) ?>
<form method="post" enctype="multipart/form-data" x-data="{ pres: <?= e(json_encode($pres)) ?>, pv: <?= max(0, (int) array_search(1, array_column($pres, 'pv'))) ?>, pc: <?= max(0, (int) array_search(1, array_column($pres, 'pc'))) ?> }">
    <?= csrf_campo() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3"><div class="card-body row g-3">
                <div class="card-titulo col-12 mb-0">Datos generales</div>
                <?= Html::input('codigo', 'Código interno', $datos, $errores, ['requerido' => true, 'col' => 'col-6 col-md-3', 'max' => 30]) ?>
                <?= Html::input('codigo_barras', 'Código de barras (EAN)', $datos, $errores, ['col' => 'col-6 col-md-4', 'inputmode' => 'numeric', 'max' => 30]) ?>
                <?= Html::input('unidad_base', 'Unidad base', $datos, $errores, ['requerido' => true, 'col' => 'col-12 col-md-5', 'ayuda' => 'Unidad en la que se controla el stock (Unidad, Paquete, Botella, Kg).']) ?>
                <?= Html::input('descripcion', 'Descripción', $datos, $errores, ['requerido' => true, 'max' => 200]) ?>
                <?= Html::select('rubro_id', 'Rubro', $rubros, $datos, $errores, ['vacio' => 'Sin rubro', 'col' => 'col-12 col-md-4']) ?>
                <?= Html::select('marca_id', 'Marca', $marcas, $datos, $errores, ['vacio' => 'Sin marca', 'col' => 'col-6 col-md-4']) ?>
                <?= Html::select('proveedor_id', 'Proveedor habitual', $proveedores, $datos, $errores, ['vacio' => 'Ninguno', 'col' => 'col-6 col-md-4']) ?>
            </div></div>

            <div class="card shadow-sm mb-3"><div class="card-body">
                <div class="card-titulo">Presentaciones <small class="text-body-secondary fw-normal">(ej.: 1 caja = 12 displays = 240 unidades)</small></div>
                <?php if (isset($errores['presentaciones'])): ?><div class="alert alert-danger py-2"><?= e($errores['presentaciones']) ?></div><?php endif; ?>
                <template x-for="(p, i) in pres" :key="i">
                    <div class="row g-2 align-items-end border-bottom pb-2 mb-2">
                        <input type="hidden" name="pres_id[]" :value="p.id">
                        <div class="col-6 col-md-3"><label class="form-label small">Nombre</label><input class="form-control" name="pres_nombre[]" x-model="p.nombre" placeholder="Display, Caja..."></div>
                        <div class="col-6 col-md-2"><label class="form-label small">Unidades base</label><input class="form-control" name="pres_factor[]" x-model="p.factor" inputmode="decimal" :readonly="p.factor == 1 && i === 0"></div>
                        <div class="col-12 col-md-3"><label class="form-label small">Código de barras</label><input class="form-control" name="pres_barras[]" x-model="p.barras" inputmode="numeric"></div>
                        <div class="col-12 col-md-4 d-flex flex-wrap gap-3 align-items-center">
                            <input type="hidden" :name="'pres_venta[' + i + ']'" :value="p.venta ? 1 : 0"><input type="hidden" :name="'pres_compra[' + i + ']'" :value="p.compra ? 1 : 0">
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" x-model="p.venta"> Venta</label>
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" x-model="p.compra"> Compra</label>
                            <label class="form-check-label" title="Predeterminada para vender"><input type="radio" class="form-check-input" name="pres_pv" :value="i" :checked="pv === i" @change="pv = i"> Venta pred.</label>
                            <label class="form-check-label" title="Predeterminada para comprar"><input type="radio" class="form-check-input" name="pres_pc" :value="i" :checked="pc === i" @change="pc = i"> Compra pred.</label>
                            <button type="button" class="btn btn-outline-danger btn-sm ms-auto" x-show="i > 0" @click="pres.splice(i, 1)" aria-label="Quitar"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-outline-primary btn-touch" @click="pres.push({id:0, nombre:'', factor:'', barras:'', venta:1, compra:1})"><i class="bi bi-plus"></i> Agregar presentación</button>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3"><div class="card-body row g-3">
                <div class="card-titulo col-12 mb-0">Costo e impuestos</div>
                <?= Html::decimal('costo', 'Costo neto por unidad base (sin IVA)', $datos, $errores, ['prefijo' => '$', 'decimales' => 4, 'ayuda' => 'Al cambiarlo se recalculan los precios por margen.']) ?>
                <?= Html::select('iva_alicuota', 'Alícuota de IVA', alicuotas_iva(), $datos, $errores, ['col' => 'col-6']) ?>
                <?= Html::decimal('impuesto_interno', 'Imp. internos ($/u.)', $datos, $errores, ['col' => 'col-6', 'decimales' => 4]) ?>
            </div></div>
            <div class="card shadow-sm mb-3"><div class="card-body row g-3">
                <div class="card-titulo col-12 mb-0">Stock</div>
                <?= Html::decimal('stock_minimo', 'Stock mínimo', $datos, $errores, ['col' => 'col-6', 'decimales' => 3]) ?>
                <?= Html::decimal('stock_maximo', 'Stock máximo', $datos, $errores, ['col' => 'col-6', 'decimales' => 3]) ?>
                <?= Html::input('ubicacion', 'Ubicación en depósito', $datos, $errores, ['placeholder' => 'Ej.: A-03', 'max' => 30]) ?>
                <?= Html::check('maneja_lotes', 'Maneja lotes y vencimientos', $datos) ?>
                <?= Html::check('activo', 'Producto activo', $datos) ?>
            </div></div>
            <div class="card shadow-sm mb-3"><div class="card-body">
                <div class="card-titulo">Foto</div>
                <?php if (!empty($datos['foto'])): ?><img src="<?= url('/uploads/productos/' . $datos['foto']) ?>" alt="" class="img-fluid rounded mb-2" style="max-height:160px"><?php endif; ?>
                <input type="file" class="form-control" name="foto" accept="image/*" capture="environment">
                <div class="form-text">Se reduce automáticamente para que cargue rápido en el celular.</div>
            </div></div>
        </div>
    </div>
    <div class="barra-acciones-fija d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-touch flex-fill flex-md-grow-0"><i class="bi bi-check-lg"></i> Guardar producto</button>
        <a href="<?= url($id ? '/productos/' . $id : '/productos') ?>" class="btn btn-outline-secondary btn-touch">Cancelar</a>
    </div>
</form>
