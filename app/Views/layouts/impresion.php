<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? 'Imprimir') ?></title>
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/impresion.css') ?>">
</head>
<body class="<?= e($claseBody ?? 'hoja-a4') ?>">
<div class="barra-imprimir no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    <?php if (!empty($urlPdf)): ?><a class="btn btn-outline-danger" href="<?= e($urlPdf) ?>"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a><?php endif; ?>
    <?php if (!empty($formatos)): foreach ($formatos as $etiqueta => $href): ?>
        <a class="btn btn-outline-secondary" href="<?= e($href) ?>"><?= e($etiqueta) ?></a>
    <?php endforeach; endif; ?>
    <button type="button" class="btn btn-outline-secondary" onclick="history.length > 1 ? history.back() : window.close()">Volver</button>
</div>
<?= $contenido ?>
<?php if (!empty($autoImprimir)): ?><script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script><?php endif; ?>
</body>
</html>
