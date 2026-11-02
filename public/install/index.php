<?php
/**
 * Instalador web de Distribuidora Toro.
 * Verifica requisitos, crea la base y el usuario administrador, escribe config/config.php
 * y se bloquea creando storage/instalado.lock.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Core\Validador;
use App\Support\DatosDemo;
use App\Support\Instalacion;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
session_name('DTINSTALADOR');
session_start();
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}

$bloqueado = is_file(STORAGE_PATH . '/instalado.lock');
$requisitos = Instalacion::requisitos();
$okRequisitos = !in_array(false, array_column($requisitos, 1), true);
$errores = [];
$exito = false;
$d = [
    'db_host' => 'localhost', 'db_puerto' => '3306', 'db_nombre' => '', 'db_usuario' => '', 'db_clave' => '',
    'empresa_nombre' => 'Distribuidora Toro', 'empresa_razon_social' => '', 'empresa_cuit' => '', 'empresa_domicilio' => '',
    'admin_nombre' => 'Administrador', 'admin_usuario' => 'admin', 'admin_clave' => '', 'admin_clave2' => '', 'demo' => '1',
];

if (!$bloqueado && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($d as $k => $v) {
        $d[$k] = trim((string) ($_POST[$k] ?? ($k === 'demo' ? '0' : '')));
    }
    if (!hash_equals($_SESSION['token'], (string) ($_POST['token'] ?? ''))) {
        $errores[] = 'El formulario venció. Recargá la página.';
    }
    if (!$okRequisitos) {
        $errores[] = 'El servidor no cumple los requisitos.';
    }
    foreach (['db_host' => 'Servidor', 'db_nombre' => 'Base de datos', 'db_usuario' => 'Usuario de la base', 'admin_usuario' => 'Usuario administrador', 'empresa_nombre' => 'Nombre de la empresa'] as $k => $t) {
        if ($d[$k] === '') {
            $errores[] = "Completá: $t.";
        }
    }
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', strtolower($d['admin_usuario']))) {
        $errores[] = 'El usuario administrador debe tener entre 3 y 50 letras, números, punto o guion.';
    }
    if (mb_strlen($d['admin_clave']) < 8) {
        $errores[] = 'La contraseña del administrador debe tener al menos 8 caracteres.';
    } elseif ($d['admin_clave'] !== $d['admin_clave2']) {
        $errores[] = 'Las contraseñas del administrador no coinciden.';
    }
    if ($d['empresa_cuit'] !== '' && !Validador::cuitValido($d['empresa_cuit'])) {
        $errores[] = 'El CUIT de la empresa no es válido.';
    }
    if (!$errores) {
        $db = ['host' => $d['db_host'], 'puerto' => (int) $d['db_puerto'] ?: 3306, 'nombre' => $d['db_nombre'], 'usuario' => $d['db_usuario'], 'clave' => $d['db_clave']];
        try {
            try {
                $pdo = DB::conectar($db);
            } catch (PDOException $e) {
                // En un VPS con permisos se intenta crear la base; en hosting compartido se crea desde el panel.
                $tmp = new PDO("mysql:host={$db['host']};port={$db['puerto']};charset=utf8mb4", $db['usuario'], $db['clave'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $tmp->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['nombre']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo = DB::conectar($db);
            }
            $existe = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
            if ($existe) {
                $errores[] = 'La base de datos ya tiene tablas del sistema. Usá una base vacía (o borrá las tablas desde phpMyAdmin) para instalar.';
            } else {
                @set_time_limit(600);
                Config::set('db', $db);
                DB::usar($pdo);
                Instalacion::crearEstructura();
                Instalacion::datosIniciales(
                    ['nombre' => $d['admin_nombre'], 'usuario' => strtolower($d['admin_usuario']), 'clave' => $d['admin_clave']],
                    array_filter(['empresa_nombre' => $d['empresa_nombre'], 'empresa_razon_social' => $d['empresa_razon_social'], 'empresa_cuit' => $d['empresa_cuit'], 'empresa_domicilio' => $d['empresa_domicilio']])
                );
                if ($d['demo'] === '1') {
                    DatosDemo::cargar();
                }
                Instalacion::escribirConfig($db, false);
                Instalacion::bloquear();
                $exito = true;
                $bloqueado = true;
                session_regenerate_id(true);
            }
        } catch (Throwable $e) {
            App\Core\Log::error('Instalación: ' . $e->getMessage());
            $errores[] = 'No se pudo completar la instalación: ' . $e->getMessage();
        }
    }
}
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = base_path();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalación — Distribuidora Toro</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<style>body{background:linear-gradient(160deg,#9e1b1b 0%,#4a0d0d 55%,#1c1f24 100%);min-height:100vh;padding:16px}.caja{max-width:860px;margin:2rem auto}.form-control{min-height:46px;font-size:16px}.btn{min-height:48px}</style>
</head>
<body>
<div class="caja">
    <div class="text-center text-white mb-3"><img src="../assets/img/logo.svg" width="80" height="80" alt="" style="background:#fff;border-radius:50%;padding:4px"><h1 class="h3 mt-2">Instalación de Distribuidora Toro</h1></div>
    <div class="card shadow"><div class="card-body p-4">
    <?php if ($exito): ?>
        <div class="text-center">
            <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
            <h2 class="h4">¡Instalación completa!</h2>
            <p>El sistema quedó instalado y el instalador se bloqueó automáticamente.</p>
            <p>Ingresá con el usuario <strong><?= $h(strtolower($d['admin_usuario'])) ?></strong> y la contraseña que elegiste.<?= $d['demo'] === '1' ? ' Se cargaron datos de demostración (ver usuarios demo en el README).' : '' ?></p>
            <p class="small text-body-secondary">Por seguridad podés borrar la carpeta <code>public/install</code>. Recordá configurar el cron de copias de seguridad.</p>
            <a class="btn btn-danger btn-lg" href="<?= $h($base . '/login') ?>">Ir al sistema</a>
        </div>
    <?php elseif ($bloqueado): ?>
        <div class="text-center"><i class="bi bi-lock-fill text-secondary" style="font-size:3rem"></i><h2 class="h4">El sistema ya está instalado</h2>
            <p>El instalador está bloqueado. Para reinstalar (¡se pierden los datos!) borrá el archivo <code>storage/instalado.lock</code> y vaciá la base de datos.</p>
            <a class="btn btn-danger" href="<?= $h($base . '/login') ?>">Ir al sistema</a></div>
    <?php else: ?>
        <h2 class="h5">1. Requisitos del servidor</h2>
        <ul class="list-group mb-4">
            <?php foreach ($requisitos as [$desc, $ok, $det]): ?>
                <li class="list-group-item d-flex justify-content-between"><span><i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i> <?= $h($desc) ?></span><small class="text-body-secondary"><?= $h($det) ?></small></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$okRequisitos): ?><div class="alert alert-danger">Corregí los requisitos marcados en rojo (versión de PHP o extensiones desde el panel; permisos de carpetas en 755/775) y recargá esta página.</div><?php endif; ?>
        <?php foreach ($errores as $e): ?><div class="alert alert-danger py-2"><?= $h($e) ?></div><?php endforeach; ?>
        <form method="post" class="row g-3" autocomplete="off">
            <input type="hidden" name="token" value="<?= $h($_SESSION['token']) ?>">
            <h2 class="h5 mt-2">2. Base de datos MySQL</h2>
            <p class="small text-body-secondary mb-0">En DonWeb/Ferozo creala desde el panel › Bases de datos › MySQL, y copiá acá el nombre, usuario y contraseña.</p>
            <div class="col-md-8"><label class="form-label">Servidor</label><input class="form-control" name="db_host" value="<?= $h($d['db_host']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Puerto</label><input class="form-control" name="db_puerto" value="<?= $h($d['db_puerto']) ?>" inputmode="numeric"></div>
            <div class="col-md-4"><label class="form-label">Nombre de la base</label><input class="form-control" name="db_nombre" value="<?= $h($d['db_nombre']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Usuario</label><input class="form-control" name="db_usuario" value="<?= $h($d['db_usuario']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Contraseña</label><input class="form-control" type="password" name="db_clave" value="<?= $h($d['db_clave']) ?>"></div>
            <h2 class="h5 mt-4">3. Empresa</h2>
            <div class="col-md-6"><label class="form-label">Nombre comercial</label><input class="form-control" name="empresa_nombre" value="<?= $h($d['empresa_nombre']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Razón social</label><input class="form-control" name="empresa_razon_social" value="<?= $h($d['empresa_razon_social']) ?>"></div>
            <div class="col-md-4"><label class="form-label">CUIT</label><input class="form-control" name="empresa_cuit" value="<?= $h($d['empresa_cuit']) ?>" placeholder="30-12345678-9"></div>
            <div class="col-md-8"><label class="form-label">Domicilio</label><input class="form-control" name="empresa_domicilio" value="<?= $h($d['empresa_domicilio']) ?>"></div>
            <h2 class="h5 mt-4">4. Usuario administrador</h2>
            <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="admin_nombre" value="<?= $h($d['admin_nombre']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Usuario</label><input class="form-control" name="admin_usuario" value="<?= $h($d['admin_usuario']) ?>" autocapitalize="none" required></div>
            <div class="col-md-6"><label class="form-label">Contraseña (mínimo 8)</label><input class="form-control" type="password" name="admin_clave" minlength="8" required autocomplete="new-password"></div>
            <div class="col-md-6"><label class="form-label">Repetir contraseña</label><input class="form-control" type="password" name="admin_clave2" minlength="8" required autocomplete="new-password"></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="demo" value="1" id="demo" <?= $d['demo'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="demo">Cargar datos de demostración (productos, clientes de Salta, pedidos, ventas, reparto, usuarios demo). Recomendado para probar; para trabajar en serio, instalar sin demo.</label></div>
                <div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-shield-exclamation"></i> Los usuarios demo (admin/admin123, oficina/oficina123, etc.) figuran en la documentación pública del proyecto: si instalás la demo en un servidor accesible desde internet, cambiá esas contraseñas o desactivá esos usuarios antes de compartir la dirección.</div></div>
            <div class="col-12"><button class="btn btn-danger btn-lg w-100" <?= $okRequisitos ? '' : 'disabled' ?>><i class="bi bi-rocket-takeoff"></i> Instalar</button>
                <p class="small text-body-secondary mt-2 mb-0">Con datos de demostración la instalación puede tardar hasta un minuto.</p></div>
        </form>
    <?php endif; ?>
    </div></div>
</div>
</body>
</html>
