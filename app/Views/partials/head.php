<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#9e1b1b">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(base_path()) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= e($titulo ?? 'Distribuidora Toro') ?></title>
<link rel="manifest" href="<?= url('/manifest.webmanifest') ?>">
<link rel="icon" href="<?= asset('assets/img/logo.svg') ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= asset('assets/img/icono-192.png') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<script>
// Aplica el tema guardado antes de pintar (evita parpadeo)
(function () {
    try {
        var t = localStorage.getItem('dt-tema');
        if (!t) { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
        document.documentElement.setAttribute('data-bs-theme', t);
    } catch (e) {}
})();
</script>
