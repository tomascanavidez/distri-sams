<!-- Lector de códigos de barras con la cámara del celular -->
<div class="modal fade" id="modalEscaner" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upc-scan"></i> Escanear código</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="lectorCamara" style="width:100%;min-height:240px;background:#000;border-radius:8px;overflow:hidden"></div>
                <p class="text-danger small mt-2 mb-0" id="lectorError"></p>
                <p class="text-body-secondary small mt-2 mb-0">Apuntá la cámara al código de barras del producto o del bulto.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-touch w-100" data-bs-dismiss="modal">Cancelar</button></div>
        </div>
    </div>
</div>
