<?php
$iconos = ['exito' => 'check-circle-fill', 'error' => 'exclamation-triangle-fill', 'aviso' => 'info-circle-fill'];
$clases = ['exito' => 'success', 'error' => 'danger', 'aviso' => 'warning'];
foreach (flashes() as $f): ?>
    <div class="alert alert-<?= $clases[$f['tipo']] ?? 'info' ?> alert-dismissible fade show d-flex align-items-start gap-2 alerta-flash" role="alert">
        <i class="bi bi-<?= $iconos[$f['tipo']] ?? 'info-circle-fill' ?> fs-5"></i>
        <div class="flex-grow-1"><?= nl2br(e($f['mensaje'])) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endforeach; ?>
<?php if (!empty($errores) && is_array($errores)): ?>
    <div class="alert alert-danger d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>Revisá los datos marcados en rojo.</div>
    </div>
<?php endif; ?>
