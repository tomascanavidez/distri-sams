<!-- Diálogo de confirmación reutilizable (acciones irreversibles y anulaciones con motivo) -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> <span data-titulo>Confirmar</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p data-mensaje class="mb-2"></p>
                <div data-motivo-grupo class="d-none">
                    <label class="form-label" for="confirmarMotivo">Motivo <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="confirmarMotivo" rows="2" maxlength="255"></textarea>
                    <div class="invalid-feedback">Indicá el motivo.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-touch" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-touch" data-aceptar>Confirmar</button>
            </div>
        </div>
    </div>
</div>
