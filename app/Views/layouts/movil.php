<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
<?= App\Core\View::parcial('partials/head', ['titulo' => $titulo ?? 'Distribuidora Toro']) ?>
<link rel="stylesheet" href="<?= asset('assets/css/movil.css') ?>">
</head>
<body class="movil-body">
<?= $contenido ?>
<?= App\Core\View::parcial('partials/escaner') ?>
<script src="<?= asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script src="<?= asset('assets/js/db.js') ?>"></script>
<script src="<?= asset('assets/js/documentos.js') ?>"></script>
<?= App\Core\View::bloque('scripts') ?>
<script defer src="<?= asset('assets/vendor/alpine/alpine.min.js') ?>"></script>
</body>
</html>
