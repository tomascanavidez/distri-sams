<div class="encabezado"><h1>Actualizaciones de la base de datos</h1></div>
<div class="card shadow-sm" style="max-width:820px"><div class="card-body">
    <p>Las actualizaciones (migraciones) son archivos en <code>database/migrations</code>. Al subir una nueva versión del sistema, aplicá las pendientes desde acá o por consola con <code>php cron/migrar.php</code>. <strong>Antes hacé una copia de seguridad.</strong></p>
    <table class="table table-sm"><thead><tr><th>Versión</th><th>Descripción</th><th>Estado</th></tr></thead><tbody>
    <?php foreach ($disponibles as $v => [$nombre]): ?><tr><td><?= e($v) ?></td><td><?= e($nombre) ?></td><td><?= isset($aplicadas[$v]) ? '<span class="badge text-bg-success">Aplicada ' . fecha_hora($aplicadas[$v]) . '</span>' : '<span class="badge text-bg-warning">Pendiente</span>' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php if ($pendientes): ?>
        <form method="post" data-confirmar="¿Aplicar <?= count($pendientes) ?> actualización(es) de la base? Asegurate de tener una copia de seguridad reciente."><?= csrf_campo() ?><button class="btn btn-primary btn-touch"><i class="bi bi-database-up"></i> Aplicar pendientes</button> <a href="<?= url('/backups') ?>" class="btn btn-outline-secondary btn-touch">Ir a copias de seguridad</a></form>
    <?php else: ?><p class="text-success mb-0"><i class="bi bi-check-circle"></i> La base de datos está actualizada.</p><?php endif; ?>
</div></div>
