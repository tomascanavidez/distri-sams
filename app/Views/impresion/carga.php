<div class="hoja">
    <h1 class="h5">Carga consolidada — Hoja de ruta N° <?= (int) $hr['numero'] ?></h1>
    <p class="small"><?= fecha($hr['fecha']) ?> · Camión <?= e($hr['patente']) ?> · Chofer <?= e($hr['chofer']) ?></p>
    <table class="comp-items"><thead><tr><th>✔</th><th>Ubicación</th><th>Código</th><th>Producto</th><th>Rubro</th><th class="n">Unidades</th><th class="n">Bultos</th></tr></thead><tbody>
    <?php $totU = 0; foreach ($carga as $c): $totU += (float) $c['cantidad'];
        $b = $c['bulto']; $f = $b ? (float) $b['factor'] : 1; $bultos = $f > 1 ? intdiv((int) $c['cantidad'], (int) $f) : 0; $sueltas = $f > 1 ? (float) $c['cantidad'] - $bultos * $f : 0; ?>
        <tr><td>☐</td><td><?= e($c['ubicacion']) ?></td><td><?= e($c['codigo']) ?></td><td><?= e($c['descripcion']) ?></td><td><?= e($c['rubro']) ?></td><td class="n"><strong><?= cant($c['cantidad']) ?></strong> <?= e($c['unidad_base']) ?></td>
            <td class="n"><?= $bultos ? $bultos . ' ' . e($b['nombre']) . ($sueltas ? ' + ' . cant($sueltas) . ' u.' : '') : '—' ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <p class="mt-2"><strong>Total de unidades: <?= cant($totU) ?></strong></p>
    <div class="d-flex justify-content-between mt-5"><div class="firma">Preparó (depósito)</div><div class="firma">Controló (chofer)</div></div>
</div>
