<?php use App\Controllers\ClientesController; use App\Support\Html; ?>
<div class="encabezado"><h1><?= e($titulo) ?></h1><div class="acciones"><a href="<?= url($id ? '/clientes/' . $id : '/clientes') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Volver</a></div></div>
<?= App\Core\View::parcial('partials/flash', ['errores' => $errores]) ?>
<form method="post" x-data="{ lat: '<?= e($datos['latitud'] ?? '') ?>', lng: '<?= e($datos['longitud'] ?? '') ?>', cond: '<?= e($datos['condicion_pago'] ?? 'contado') ?>',
        ubicar() { if (!navigator.geolocation) { DT.aviso('El dispositivo no permite obtener la ubicación', 'error'); return; }
            navigator.geolocation.getCurrentPosition(p => { this.lat = p.coords.latitude.toFixed(7); this.lng = p.coords.longitude.toFixed(7); DT.aviso('Ubicación cargada', 'exito'); }, () => DT.aviso('No se pudo obtener la ubicación', 'error'), { enableHighAccuracy: true, timeout: 15000 }); } }">
    <?= csrf_campo() ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm mb-3"><div class="card-body row g-3">
                <div class="card-titulo col-12 mb-0">Comercio</div>
                <?= Html::input('codigo', 'Código', $datos, $errores, ['requerido' => true, 'col' => 'col-4', 'max' => 20]) ?>
                <?= Html::input('nombre_comercio', 'Nombre del comercio', $datos, $errores, ['requerido' => true, 'col' => 'col-8', 'max' => 150]) ?>
                <?= Html::input('titular', 'Titular', $datos, $errores, ['col' => 'col-12 col-md-6', 'max' => 150]) ?>
                <?= Html::select('tipo_comercio', 'Tipo de comercio', array_combine(ClientesController::TIPOS_COMERCIO, ClientesController::TIPOS_COMERCIO), $datos, $errores, ['col' => 'col-12 col-md-6']) ?>
                <?= Html::select('condicion_iva', 'Condición frente al IVA', condiciones_iva(), $datos, $errores, ['requerido' => true, 'col' => 'col-12 col-md-6']) ?>
                <?= Html::select('tipo_documento', 'Documento', ['CUIT' => 'CUIT', 'CUIL' => 'CUIL', 'DNI' => 'DNI'], $datos, $errores, ['col' => 'col-4 col-md-2']) ?>
                <?= Html::input('documento', 'Número', $datos, $errores, ['col' => 'col-8 col-md-4', 'inputmode' => 'numeric', 'placeholder' => '20-12345678-9']) ?>
            </div></div>
            <div class="card shadow-sm mb-3"><div class="card-body row g-3">
                <div class="card-titulo col-12 mb-0">Contacto y ubicación</div>
                <?= Html::input('direccion', 'Dirección', $datos, $errores, ['col' => 'col-12 col-md-8', 'max' => 200]) ?>
                <?= Html::input('localidad', 'Localidad', $datos, $errores, ['col' => 'col-12 col-md-4', 'max' => 100]) ?>
                <?= Html::input('telefono', 'Teléfono', $datos, $errores, ['col' => 'col-6', 'tipo' => 'tel', 'inputmode' => 'tel']) ?>
                <?= Html::input('whatsapp', 'WhatsApp', $datos, $errores, ['col' => 'col-6', 'tipo' => 'tel', 'inputmode' => 'tel', 'placeholder' => '387 15 4123456']) ?>
                <?= Html::input('email', 'Correo electrónico', $datos, $errores, ['col' => 'col-12', 'tipo' => 'email', 'inputmode' => 'email']) ?>
                <div class="col-5"><label class="form-label" for="f_latitud">Latitud</label><input class="form-control" id="f_latitud" name="latitud" x-model="lat" inputmode="decimal"></div>
                <div class="col-5"><label class="form-label" for="f_longitud">Longitud</label><input class="form-control" id="f_longitud" name="longitud" x-model="lng" inputmode="decimal"></div>
                <div class="col-2 d-flex align-items-end"><button type="button" class="btn btn-outline-primary btn-touch w-100" @click="ubicar()" title="Usar mi ubicación actual" aria-label="Usar mi ubicación"><i class="bi bi-crosshair"></i></button></div>
                <?php if (isset($errores['latitud']) || isset($errores['longitud'])): ?><div class="col-12 text-danger small">Coordenadas inválidas.</div><?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm mb-3"><div class="card-body row g-3">
                <div class="card-titulo col-12 mb-0">Ruta y visitas</div>
                <?= Html::select('zona_id', 'Zona / ruta', $zonas, $datos, $errores, ['vacio' => 'Sin zona', 'col' => 'col-6']) ?>
                <?= Html::select('preventista_id', 'Preventista', $preventistas, $datos, $errores, ['vacio' => 'Sin asignar', 'col' => 'col-6']) ?>
                <?= Html::dias('dias_visita', 'Días de visita', $datos) ?>
                <?= Html::input('horario_visita', 'Horario de visita', $datos, $errores, ['col' => 'col-6', 'placeholder' => '9 a 13 h']) ?>
                <?= Html::input('orden_ruta', 'Orden en la ruta', $datos, $errores, ['col' => 'col-6', 'tipo' => 'number', 'inputmode' => 'numeric']) ?>
                <?= Html::dias('dias_entrega', 'Días de entrega', $datos) ?>
                <?= Html::input('horario_entrega', 'Horario de entrega', $datos, $errores, ['col' => 'col-12 col-md-6', 'placeholder' => '9 a 13 h']) ?>
            </div></div>
            <div class="card shadow-sm mb-3"><div class="card-body row g-3">
                <div class="card-titulo col-12 mb-0">Condiciones comerciales</div>
                <?= Html::select('lista_precio_id', 'Lista de precios', $listas, $datos, $errores, ['col' => 'col-12 col-md-6']) ?>
                <div class="col-12 col-md-6"><label class="form-label" for="f_cp">Condición de pago</label>
                    <select class="form-select" id="f_cp" name="condicion_pago" x-model="cond"><option value="contado">Contado</option><option value="cta_cte">Cuenta corriente</option></select></div>
                <div class="col-6" x-show="cond === 'cta_cte'"><label class="form-label" for="f_dc">Plazo (días)</label><input class="form-control" id="f_dc" name="dias_credito" inputmode="numeric" value="<?= e($datos['dias_credito'] ?? 7) ?>"></div>
                <?= Html::decimal('limite_credito', 'Límite de crédito ($)', $datos, $errores, ['col' => 'col-6', 'ayuda' => '0 = sin límite']) ?>
                <?= Html::textarea('observaciones', 'Observaciones', $datos, $errores) ?>
            </div></div>
        </div>
    </div>
    <div class="barra-acciones-fija d-flex gap-2"><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar cliente</button><a href="<?= url($id ? '/clientes/' . $id : '/clientes') ?>" class="btn btn-outline-secondary btn-touch">Cancelar</a></div>
</form>
