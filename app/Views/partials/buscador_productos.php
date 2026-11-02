<?php /** Buscador de productos del editor de documentos (requiere x-data="editorDocumento"). */ ?>
<div class="autocompletar mb-2">
    <div class="input-group input-group-lg">
        <span class="input-group-text"><i class="bi" :class="buscando ? 'bi-hourglass-split' : 'bi-search'"></i></span>
        <input type="search" class="form-control" x-ref="buscador" x-model="q" @input="buscar()" @keydown="teclaBusqueda($event)"
               placeholder="Producto, marca, código o código de barras" autocomplete="off" aria-label="Buscar producto" <?= !empty($autofoco) ? 'autofocus' : '' ?>>
        <button type="button" class="btn btn-outline-secondary" @click="escanear()" title="Escanear con la cámara" aria-label="Escanear código de barras"><i class="bi bi-upc-scan"></i></button>
    </div>
    <div class="autocompletar-lista" x-show="resultados.length" @click.outside="resultados = []" x-cloak>
        <template x-for="(r, i) in resultados" :key="r.id">
            <button type="button" :class="{ activo: i === indice }" @click="agregar(r)">
                <div class="d-flex justify-content-between gap-2">
                    <span><strong x-text="r.descripcion"></strong><br><small x-text="r.codigo + (r.marca ? ' · ' + r.marca : '')"></small></span>
                    <span class="text-end text-nowrap">
                        <template x-if="modo !== 'compra' && modo !== 'stock'"><strong x-text="DT.dinero(r.precio)"></strong></template>
                        <template x-if="modo === 'compra'"><strong x-text="'Costo ' + DT.dinero(r.costo)"></strong></template>
                        <br><small :class="r.disponible > 0 ? 'text-success' : 'text-danger'" x-text="'Disp. ' + DT.cant(r.disponible)"></small>
                    </span>
                </div>
            </button>
        </template>
    </div>
</div>
