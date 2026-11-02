<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
<?= App\Core\View::parcial('partials/head', ['titulo' => $titulo ?? 'Distribuidora Toro']) ?>
</head>
<body class="simple-body">
<main class="simple-contenido">
    <?= App\Core\View::parcial('partials/flash') ?>
    <?= $contenido ?>
</main>
<?= App\Core\View::parcial('partials/scripts') ?>
<?= App\Core\View::bloque('scripts') ?>
</body>
</html>
