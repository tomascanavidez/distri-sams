<div class="encabezado"><h1>Copias de seguridad</h1>
    <div class="acciones"><?php if (puede('backups.crear')): ?><form method="post" action="<?= url('/backups/generar') ?>" data-confirmar="¿Generar una copia de seguridad completa de la base de datos ahora?"><?= csrf_campo() ?><button class="btn btn-primary btn-touch"><i class="bi bi-database-down"></i> Generar copia ahora</button></form><?php endif; ?></div></div>
<div class="row g-3">
    <div class="col-lg-7"><div class="card shadow-sm"><div class="card-body">
        <table class="table align-middle tabla-cards mb-0"><thead><tr><th>Archivo</th><th>Fecha</th><th class="num">Tamaño</th><th></th></tr></thead><tbody>
        <?php foreach ($archivos as $a): ?><tr><td class="td-titulo"><i class="bi bi-file-earmark-zip"></i> <?= e($a['nombre']) ?></td><td data-label="Fecha"><?= date('d/m/Y H:i', $a['fecha']) ?></td><td class="num" data-label="Tamaño"><?= num($a['tamano'] / 1048576, 2) ?> MB</td>
            <td class="td-acciones text-end"><a class="btn btn-sm btn-outline-primary btn-touch" href="<?= url('/backups/descargar/' . rawurlencode($a['nombre'])) ?>"><i class="bi bi-download"></i> Descargar</a></td></tr><?php endforeach; ?>
        <?php if (!$archivos): ?><tr><td colspan="4" class="text-center text-body-secondary py-4">Todavía no hay copias.</td></tr><?php endif; ?></tbody></table>
    </div></div></div>
    <div class="col-lg-5"><div class="card shadow-sm"><div class="card-body">
        <div class="card-titulo"><i class="bi bi-clock-history"></i> Copias automáticas (cron)</div>
        <p class="small">Se generan en PHP (no hace falta <code>mysqldump</code>) y se guardan comprimidas en <code>storage/backups</code>. Se conservan las últimas <?= (int) App\Core\Config::get('backup.conservar', 15) ?>.</p>
        <p class="small mb-1">En el panel de Ferozo › <strong>Cron Jobs</strong>, programá todos los días a las 3:00:</p>
        <pre class="small bg-body-tertiary p-2 rounded"><code>/usr/bin/php <?= e(BASE_PATH) ?>/cron/tareas_diarias.php</code></pre>
        <p class="small mb-0">Además de la copia, esa tarea aplica el bloqueo automático de clientes morosos (si está activado). Descargá periódicamente una copia y guardala fuera del servidor.</p>
        <hr><p class="small mb-0"><strong>Restaurar:</strong> en phpMyAdmin, elegí la base › Importar › seleccioná el archivo <code>.sql.gz</code>.</p>
    </div></div></div>
</div>
