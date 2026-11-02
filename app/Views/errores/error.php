<div class="error-pagina text-center py-5">
    <div class="display-1 fw-bold text-body-tertiary"><?= (int) $codigo ?></div>
    <h1 class="h3"><?= e($titulo) ?></h1>
    <p class="text-body-secondary"><?= e($mensaje) ?></p>
    <a href="<?= url('/') ?>" class="btn btn-primary btn-touch"><i class="bi bi-house"></i> Ir al inicio</a>
    <button type="button" class="btn btn-outline-secondary btn-touch" onclick="history.back()">Volver</button>
</div>
