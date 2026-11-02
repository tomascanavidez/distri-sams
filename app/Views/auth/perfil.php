<div class="encabezado"><h1>Mi contraseña</h1></div>
<div class="card shadow-sm" style="max-width:520px"><div class="card-body">
    <form method="post" action="<?= url('/perfil') ?>">
        <?= csrf_campo() ?>
        <div class="mb-3"><label class="form-label" for="actual">Contraseña actual</label>
            <input type="password" class="form-control" id="actual" name="actual" autocomplete="current-password" required></div>
        <div class="mb-3"><label class="form-label" for="nueva">Nueva contraseña (mínimo 8 caracteres)</label>
            <input type="password" class="form-control" id="nueva" name="nueva" minlength="8" autocomplete="new-password" required></div>
        <div class="mb-3"><label class="form-label" for="repetir">Repetir nueva contraseña</label>
            <input type="password" class="form-control" id="repetir" name="repetir" minlength="8" autocomplete="new-password" required></div>
        <button class="btn btn-primary btn-touch" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
    </form>
</div></div>
