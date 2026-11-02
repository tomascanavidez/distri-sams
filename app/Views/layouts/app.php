<?php
/** Layout principal: menú lateral en PC, barra superior + barra inferior en celular. */
use App\Core\View;
use App\Support\Menu;

$u = usuario();
$empresa = ajuste('empresa_nombre');
$titulo = $titulo ?? $empresa;
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
<?= View::parcial('partials/head', ['titulo' => $titulo]) ?>
</head>
<body class="app-body">
<a class="visually-hidden-focusable" href="#contenido">Saltar al contenido</a>

<!-- Barra superior (celular y tablet) -->
<header class="topbar d-lg-none">
    <button class="btn btn-topbar" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-label="Abrir menú">
        <i class="bi bi-list"></i>
    </button>
    <a class="topbar-marca" href="<?= url('/') ?>"><img src="<?= asset('assets/img/logo.svg') ?>" alt="" width="28" height="28"> <?= e($empresa) ?></a>
    <button class="btn btn-topbar" type="button" data-tema-alternar aria-label="Cambiar modo claro/oscuro"><i class="bi bi-circle-half"></i></button>
</header>

<!-- Menú lateral: fijo en PC, desplegable (offcanvas) en celular -->
<aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="menuLateral" aria-label="Menú principal">
    <div class="sidebar-marca">
        <a href="<?= url('/') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
            <img src="<?= asset('assets/img/logo.svg') ?>" alt="" width="36" height="36">
            <span><strong><?= e($empresa) ?></strong><small class="d-block">Sistema de gestión</small></span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Cerrar"></button>
    </div>
    <nav class="sidebar-nav">
        <?php foreach (Menu::secciones() as $i => [$nombre, $icono, $ruta, $permiso, $hijos]):
            if (!puede($permiso)) continue;
            $hijosVisibles = array_filter($hijos, fn ($h) => puede($h[2]));
            $abierto = false;
            foreach ($hijosVisibles as $h) {
                if (menu_activo(strtok($h[1], '?')) === 'active') { $abierto = true; }
            }
            if ($ruta !== '/' && menu_activo($ruta) === 'active') { $abierto = true; }
        ?>
            <?php if (!$hijosVisibles): ?>
                <a class="sidebar-link <?= menu_activo($ruta) ?>" href="<?= url($ruta) ?>"><i class="bi bi-<?= $icono ?>"></i> <?= e($nombre) ?></a>
            <?php else: ?>
                <button class="sidebar-link sidebar-grupo <?= $abierto ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menu<?= $i ?>" aria-expanded="<?= $abierto ? 'true' : 'false' ?>">
                    <i class="bi bi-<?= $icono ?>"></i> <?= e($nombre) ?> <i class="bi bi-chevron-down ms-auto flecha"></i>
                </button>
                <div class="collapse <?= $abierto ? 'show' : '' ?>" id="menu<?= $i ?>">
                    <?php foreach ($hijosVisibles as [$hNombre, $hRuta]): ?>
                        <a class="sidebar-sublink <?= menu_sub_activo($hRuta) ?>" href="<?= url($hRuta) ?>"><?= e($hNombre) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-usuario">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-circle fs-4"></i>
            <div class="flex-grow-1 small lh-sm">
                <strong><?= e($u['nombre'] ?? '') ?></strong><br><span class="opacity-75"><?= e($u['rol_nombre'] ?? '') ?></span>
            </div>
            <button class="btn btn-sm btn-outline-light d-none d-lg-inline-block" type="button" data-tema-alternar title="Modo claro/oscuro"><i class="bi bi-circle-half"></i></button>
        </div>
        <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm btn-outline-light flex-fill" href="<?= url('/perfil') ?>"><i class="bi bi-key"></i> Mi clave</a>
            <form method="post" action="<?= url('/logout') ?>" class="flex-fill">
                <?= csrf_campo() ?>
                <button class="btn btn-sm btn-light w-100" type="submit"><i class="bi bi-box-arrow-right"></i> Salir</button>
            </form>
        </div>
    </div>
</aside>

<main class="contenido" id="contenido">
    <?= View::parcial('partials/flash') ?>
    <?php if (puede('configuracion.editar') && App\Services\Migrador::pendientes()): ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2"><i class="bi bi-database-exclamation"></i>
            <span class="flex-grow-1">Hay <strong>actualizaciones de la base de datos pendientes</strong>: algunas funciones nuevas no van a andar hasta aplicarlas.</span>
            <a class="btn btn-sm btn-warning" href="<?= url('/configuracion/migraciones') ?>">Aplicar ahora</a></div>
    <?php endif; ?>
    <?= $contenido ?>
</main>

<!-- Barra inferior para celular (personal de oficina) -->
<nav class="bottom-nav d-lg-none" aria-label="Accesos rápidos">
    <a href="<?= url('/') ?>" class="<?= menu_activo('/') ?>"><i class="bi bi-house"></i><span>Inicio</span></a>
    <?php if (puede('pedidos.ver')): ?>
        <a href="<?= url('/pedidos') ?>" class="<?= menu_activo('/pedidos') ?>"><i class="bi bi-cart3"></i><span>Pedidos</span></a>
    <?php elseif (puede('stock.ver')): ?>
        <a href="<?= url('/stock') ?>" class="<?= menu_activo('/stock') ?>"><i class="bi bi-boxes"></i><span>Stock</span></a>
    <?php else: ?>
        <a href="<?= url('/perfil') ?>" class="<?= menu_activo('/perfil') ?>"><i class="bi bi-person"></i><span>Mi cuenta</span></a>
    <?php endif; ?>
    <?php if (puede('ventas.crear')): ?>
        <a href="<?= url('/ventas/pos') ?>" class="nav-principal"><i class="bi bi-plus-lg"></i><span>Vender</span></a>
    <?php elseif (puede('pedidos.crear')): ?>
        <a href="<?= url('/pedidos/nuevo') ?>" class="nav-principal"><i class="bi bi-plus-lg"></i><span>Pedido</span></a>
    <?php elseif (puede('reportes.ver')): ?>
        <a href="<?= url('/reportes') ?>" class="nav-principal"><i class="bi bi-bar-chart"></i><span>Reportes</span></a>
    <?php else: ?>
        <a href="<?= url('/stock') ?>" class="nav-principal"><i class="bi bi-boxes"></i><span>Stock</span></a>
    <?php endif; ?>
    <?php if (puede('clientes.ver')): ?>
        <a href="<?= url('/clientes') ?>" class="<?= menu_activo('/clientes') ?>"><i class="bi bi-shop"></i><span>Clientes</span></a>
    <?php elseif (puede('compras.ver')): ?>
        <a href="<?= url('/compras/recepciones') ?>" class="<?= menu_activo('/compras') ?>"><i class="bi bi-box-arrow-in-down"></i><span>Recepción</span></a>
    <?php else: ?>
        <a href="<?= url('/reportes') ?>" class="<?= menu_activo('/reportes') ?>"><i class="bi bi-bar-chart"></i><span>Reportes</span></a>
    <?php endif; ?>
    <a href="#" data-bs-toggle="offcanvas" data-bs-target="#menuLateral"><i class="bi bi-three-dots"></i><span>Más</span></a>
</nav>

<?= View::parcial('partials/confirmar') ?>
<?= View::parcial('partials/scripts') ?>
<?= View::bloque('scripts') ?>
</body>
</html>
