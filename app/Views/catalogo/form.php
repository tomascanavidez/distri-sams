<?php
use App\Support\Html;
?>
<div class="encabezado">
    <h1><?= e($titulo) ?></h1>
    <div class="acciones"><a href="<?= url('/catalogo/' . $def['entidad']) ?>" class="btn btn-outline-secondary btn-touch"><i class="bi bi-arrow-left"></i> Volver</a></div>
</div>
<?= App\Core\View::parcial('partials/flash', ['errores' => $errores]) ?>
<div class="card shadow-sm" style="max-width: 760px"><div class="card-body">
<form method="post" class="row g-3">
    <?= csrf_campo() ?>
    <?php foreach ($def['campos'] as $campo => $c):
        [$etq, $tipo, $regla] = $c;
        $o = ($c[3] ?? []) + ['requerido' => str_contains($regla, 'requerido'), 'col' => 'col-12 col-md-6'];
        switch ($tipo) {
            case 'decimal': echo Html::decimal($campo, $etq, $datos, $errores, $o); break;
            case 'entero': echo Html::input($campo, $etq, $datos, $errores, $o + ['tipo' => 'number', 'inputmode' => 'numeric']); break;
            case 'fecha': echo Html::input($campo, $etq, $datos, $errores, $o + ['tipo' => 'date']); break;
            case 'select':
                $opc = $o['opciones'] instanceof Closure ? ($o['opciones'])() : $o['opciones'];
                echo Html::select($campo, $etq, $opc, $datos, $errores, $o); break;
            case 'dias': echo Html::dias($campo, $etq, $datos, ['col' => 'col-12']); break;
            case 'check': echo Html::check($campo, $etq, $datos, $o); break;
            case 'producto': ?>
                <div class="col-12">
                    <label class="form-label"><?= e($etq) ?> <span class="text-danger">*</span></label>
                    <div class="autocompletar" x-data="autocompletar({url: '/api/productos', nombre: '<?= e($campo) ?>', valor: '<?= e($datos[$campo] ?? '') ?>', texto: <?= e(json_encode($datos['producto_texto'] ?? '')) ?>})">
                        <input type="hidden" name="<?= e($campo) ?>" :value="valor">
                        <input type="search" class="form-control <?= isset($errores[$campo]) ? 'is-invalid' : '' ?>" x-model="q" @input="buscar()" @keydown="tecla($event)" placeholder="Buscá por nombre, marca o código" autocomplete="off">
                        <div class="autocompletar-lista" x-show="abierto && resultados.length" @click.outside="abierto = false" x-cloak>
                            <template x-for="(r, i) in resultados" :key="r.id"><button type="button" :class="{activo: i === indice}" @click="elegir(r)"><span x-text="r.texto"></span><br><small x-text="r.codigo + (r.marca ? ' · ' + r.marca : '')"></small></button></template>
                        </div>
                    </div>
                    <?php if (isset($errores[$campo])): ?><div class="invalid-feedback d-block"><?= e($errores[$campo]) ?></div><?php endif; ?>
                </div>
            <?php break;
            default: echo Html::input($campo, $etq, $datos, $errores, $o);
        }
    endforeach; ?>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Guardar</button>
        <a href="<?= url('/catalogo/' . $def['entidad']) ?>" class="btn btn-outline-secondary btn-touch">Cancelar</a>
    </div>
</form>
</div></div>
