<div class="login-caja">
    <div class="text-center mb-4">
        <img src="<?= asset('assets/img/logo.svg') ?>" alt="Distribuidora Toro" width="88" height="88" class="mb-2">
        <h1 class="h3 mb-0"><?= e(ajuste('empresa_nombre')) ?></h1>
        <p class="text-body-secondary mb-0">Sistema de gestión</p>
    </div>
    <form method="post" action="<?= url('/login') ?>" class="card card-body shadow-sm p-4" novalidate>
        <?= csrf_campo() ?>
        <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" class="form-control form-control-lg" id="usuario" name="usuario" value="<?= e($usuario ?? '') ?>"
                   autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
        </div>
        <div class="mb-3" x-data="{ ver: false }">
            <label for="clave" class="form-label">Contraseña</label>
            <div class="input-group">
                <input :type="ver ? 'text' : 'password'" type="password" class="form-control form-control-lg" id="clave" name="clave" autocomplete="current-password" required>
                <button class="btn btn-outline-secondary" type="button" @click="ver = !ver" aria-label="Mostrar u ocultar contraseña"><i class="bi" :class="ver ? 'bi-eye-slash' : 'bi-eye'"></i></button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
    </form>
    <p class="text-center small text-body-secondary mt-3">
        <button type="button" class="btn btn-link btn-sm" data-tema-alternar><i class="bi bi-circle-half"></i> Modo claro / oscuro</button>
    </p>
</div>
