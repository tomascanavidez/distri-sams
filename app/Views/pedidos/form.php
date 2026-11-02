<?php use App\Core\View; ?>
<div x-data="editorDocumento({ modo: 'venta', endpoint: '/pedidos/guardar', items: <?= e(json_encode($items, JSON_UNESCAPED_UNICODE)) ?>,
        cabecera: <?= e(json_encode($cab, JSON_UNESCAPED_UNICODE)) ?>, listaId: <?= (int) ($pedido['lista_precio_id'] ?? 0) ?: 'null' ?>,
        depositoId: <?= (int) $deposito ?>, clienteId: <?= (int) ($cab['cliente_id'] ?? 0) ?: 'null' ?> })"
     @elegido="if ($event.detail.nombre === 'cliente_id') { cab.cliente_id = $event.detail.item ? $event.detail.item.id : null; cargarCliente(cab.cliente_id); }">
    <div class="encabezado">
        <h1><?= e($titulo) ?></h1>
        <div class="acciones"><a href="<?= url($pedido ? '/pedidos/' . $pedido['id'] : '/pedidos') ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Volver</a></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3"><div class="card-body">
                <label class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                <div class="autocompletar" x-data="autocompletar({ url: '/api/clientes', nombre: 'cliente_id', valor: '<?= (int) ($cab['cliente_id'] ?? 0) ?: '' ?>', texto: <?= e(json_encode($clienteTexto)) ?> })">
                    <input type="search" class="form-control form-control-lg" x-model="q" @input="buscar()" @keydown="tecla($event)" placeholder="Nombre del comercio, titular, código o CUIT" autocomplete="off" <?= $pedido ? 'disabled' : '' ?>>
                    <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak>
                        <template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)"><strong x-text="r.texto"></strong><br><small x-text="r.detalle"></small></button></template>
                    </div>
                </div>
                <template x-if="cliente">
                    <div class="mt-2 small d-flex flex-wrap gap-3">
                        <span>Lista: <strong x-text="cliente.lista || '—'"></strong></span>
                        <span>Saldo: <strong :class="cliente.resumen.saldo > 0 ? 'saldo-deuda' : ''" x-text="DT.dinero(cliente.resumen.saldo)"></strong></span>
                        <span x-show="cliente.resumen.vencido > 0" class="text-danger">Vencido: <strong x-text="DT.dinero(cliente.resumen.vencido)"></strong></span>
                        <span x-show="cliente.resumen.limite > 0">Límite: <strong x-text="DT.dinero(cliente.resumen.limite)"></strong></span>
                        <button type="button" class="btn btn-sm btn-outline-primary" x-show="!lineas.length" @click="repetirUltimo()"><i class="bi bi-arrow-repeat"></i> Repetir último pedido</button>
                    </div>
                </template>
                <template x-for="a in avisos"><div class="alert alert-warning py-2 mt-2 mb-0 small"><i class="bi bi-exclamation-triangle"></i> <span x-text="a"></span></div></template>
            </div></div>

            <div class="card shadow-sm mb-3"><div class="card-body">
                <?= View::parcial('partials/buscador_productos', ['autofoco' => (bool) $pedido]) ?>
                <?= View::parcial('partials/lineas_documento', ['conDescuento' => true, 'conBonificacion' => true, 'precioEditable' => puede('productos.precios')]) ?>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3"><div class="card-body row g-2">
                <div class="col-6 col-lg-12"><label class="form-label" for="fecha_entrega">Fecha de entrega</label><input type="date" class="form-control" id="fecha_entrega" x-model="cab.fecha_entrega"></div>
                <div class="col-6 col-lg-12"><label class="form-label" for="dep">Depósito</label>
                    <select class="form-select" id="dep" x-model="depositoId"><?= opciones($depositos, $deposito) ?></select></div>
                <div class="col-12"><label class="form-label" for="desc">Descuento general %</label><input type="text" class="form-control" id="desc" inputmode="decimal" x-model="cab.descuento_porc"></div>
                <div class="col-12"><label class="form-label" for="obs">Observaciones</label><textarea class="form-control" id="obs" rows="2" maxlength="255" x-model="cab.observaciones"></textarea></div>
                <div class="col-12 form-check ms-2"><input type="checkbox" class="form-check-input" id="promos" x-model="cab.aplicar_promociones"><label class="form-check-label" for="promos">Aplicar promociones (ej. 12+1) automáticamente</label></div>
            </div></div>
            <div class="card shadow-sm totales-doc"><div class="card-body">
                <div class="d-flex justify-content-between"><span>Subtotal</span><span x-text="DT.dinero(subtotal())"></span></div>
                <div class="d-flex justify-content-between" x-show="cab.descuento_porc > 0"><span>Descuento</span><span x-text="'- ' + DT.dinero(subtotal() - total())"></span></div>
                <div class="d-flex justify-content-between total mt-1"><span>Total</span><span x-text="DT.dinero(total())"></span></div>
                <small class="text-body-secondary" x-text="lineas.length + ' productos · ' + DT.cant(totalUnidades()) + ' unidades'"></small>
            </div></div>
        </div>
    </div>
    <div class="alert alert-danger mt-3" x-show="error" x-cloak style="white-space: pre-line"><i class="bi bi-exclamation-triangle"></i> <span x-text="error"></span></div>
    <div class="barra-acciones-fija d-flex flex-wrap gap-2 align-items-center">
        <div class="fw-bold fs-5 me-auto d-lg-none" x-text="DT.dinero(total())"></div>
        <button type="button" class="btn btn-outline-primary btn-touch" :disabled="enviando || !cab.cliente_id" @click="guardar({ confirmar: false })"><i class="bi bi-save"></i> Guardar<span class="d-none d-md-inline"> borrador</span></button>
        <?php if (!$pedido || $pedido['estado'] === 'borrador'): ?>
            <button type="button" class="btn btn-primary btn-touch" :disabled="enviando || !cab.cliente_id" @click="guardar({ confirmar: true })"><i class="bi bi-check2-circle"></i> Confirmar pedido</button>
        <?php endif; ?>
    </div>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
