<?php use App\Core\View; ?>
<div x-data="pos({ modo: 'venta', endpoint: '/ventas/emitir', listaId: <?= (int) $lista ?>, depositoId: <?= (int) $deposito ?>, clienteDefecto: <?= e(json_encode($clienteCF, JSON_UNESCAPED_UNICODE)) ?>, cabecera: { clase: 'factura', cliente_id: <?= (int) $clienteCF['id'] ?>, descuento_porc: 0, origen: 'mostrador' } })"
     @elegido="if ($event.detail.nombre === 'cliente_id') { cab.cliente_id = $event.detail.item ? $event.detail.item.id : <?= (int) $clienteCF['id'] ?>; cargarCliente(cab.cliente_id); }">
    <div class="encabezado">
        <h1><i class="bi bi-upc-scan"></i> Mostrador</h1>
        <div class="acciones small text-body-secondary d-none d-lg-flex align-items-center gap-3">
            <span><span class="atajo">F2</span> buscar</span><span><span class="atajo">F4</span> cobrar</span><span><span class="atajo">F8</span> nueva venta</span><span><span class="atajo">3*código</span> cantidad</span>
        </div>
    </div>
    <?php if (!$cajaAbierta): ?>
        <div class="alert alert-warning"><i class="bi bi-safe"></i> La caja <strong><?= e($cajaNombre) ?></strong> está cerrada: para cobrar en efectivo abrila desde <a href="<?= url('/caja') ?>">Finanzas › Caja</a>.</div>
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm"><div class="card-body">
                <?= View::parcial('partials/buscador_productos', ['autofoco' => true]) ?>
                <?= View::parcial('partials/lineas_documento', ['conDescuento' => true, 'precioEditable' => puede('productos.precios')]) ?>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3"><div class="card-body">
                <label class="form-label small">Cliente</label>
                <div class="autocompletar" x-data="autocompletar({ url: '/api/clientes', nombre: 'cliente_id', texto: <?= e(json_encode($clienteCF['texto'])) ?> })" @reiniciar-cliente.window="q = $event.detail.texto; valor = $event.detail.id">
                    <input type="search" class="form-control" x-model="q" @input="buscar()" @keydown="tecla($event)" @focus="$event.target.select()" autocomplete="off">
                    <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak>
                        <template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{ activo: i === indice }" @click="elegir(r)"><strong x-text="r.texto"></strong><br><small x-text="r.detalle"></small></button></template>
                    </div>
                </div>
                <template x-for="a in avisos"><div class="small text-danger mt-1" x-text="a"></div></template>
            </div></div>
            <div class="card shadow-sm text-center"><div class="card-body">
                <div class="text-body-secondary">Total a cobrar</div>
                <div class="pos-total" x-text="DT.dinero(total())"></div>
                <div class="small text-body-secondary mb-3" x-text="lineas.length + ' ítems · ' + DT.cant(totalUnidades()) + ' unidades'"></div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary btn-lg btn-touch" @click="abrirCobro()" :disabled="!lineas.length"><i class="bi bi-cash-coin"></i> Cobrar <span class="atajo text-white border-white d-none d-lg-inline">F4</span></button>
                    <button type="button" class="btn btn-outline-secondary btn-touch" @click="nuevaVenta()"><i class="bi bi-arrow-counterclockwise"></i> Nueva venta</button>
                </div>
            </div></div>
        </div>
    </div>

    <div class="modal fade" x-ref="modalCobro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Cobrar <span x-text="DT.dinero(total())"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <?= View::parcial('partials/pagos', ['cuentas' => $cuentas]) ?>
                <div class="alert alert-danger mt-2 mb-0" x-show="error" x-text="error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-touch" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-primary btn-lg btn-touch" :disabled="enviando" @click="cobrar()"><i class="bi bi-check-lg"></i> Confirmar y emitir ticket</button>
            </div>
        </div></div>
    </div>
    <iframe x-ref="ticket" title="Ticket" style="position:absolute;width:0;height:0;border:0" aria-hidden="true"></iframe>
    <?= View::parcial('partials/escaner') ?>
</div>
<?php View::inicio('scripts'); ?><script src="<?= asset('assets/js/documentos.js') ?>"></script><?php View::fin(); ?>
