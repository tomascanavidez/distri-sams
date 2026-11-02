<div class="hoja">
    <h1 class="h5">Hoja de preparación — consolidado</h1>
    <p class="small mb-2"><?= count($pedidos) ?> pedidos · Emitida <?= date('d/m/Y H:i') ?> · Ordenado por ubicación en depósito</p>
    <table class="comp-items">
        <thead><tr><th>✔</th><th>Ubicación</th><th>Código</th><th>Producto</th><th>Rubro</th><th class="n">Unidades</th><th class="n">Bultos</th></tr></thead>
        <tbody><?php foreach ($consolidado as $c):
            [$bNombre, $bFactor] = array_pad(explode('|', (string) $c['bulto']), 2, 1);
            $bultos = (float) $bFactor > 1 ? intdiv((int) $c['unidades'], (int) $bFactor) : 0;
            $sueltas = (float) $bFactor > 1 ? (float) $c['unidades'] - $bultos * (float) $bFactor : (float) $c['unidades']; ?>
            <tr><td>☐</td><td><strong><?= e($c['ubicacion']) ?></strong></td><td><?= e($c['codigo']) ?></td><td><?= e($c['descripcion']) ?></td><td><?= e($c['rubro']) ?></td>
                <td class="n"><strong><?= cant($c['unidades']) ?></strong> <?= e($c['unidad_base']) ?></td>
                <td class="n"><?= $bultos ? $bultos . ' ' . e($bNombre) . ($sueltas ? ' + ' . cant($sueltas) . ' u.' : '') : '—' ?></td></tr>
        <?php endforeach; ?></tbody>
    </table>
</div>
<?php foreach ($pedidos as $p): ?>
<div class="hoja">
    <h2 class="h6 mb-1">Pedido N° <?= (int) $p['numero'] ?> — <?= e($p['nombre_comercio']) ?></h2>
    <p class="small mb-1"><?= e($p['direccion']) ?> · Zona <?= e($p['zona']) ?> · Entrega <?= fecha($p['fecha_entrega']) ?></p>
    <table class="comp-items"><thead><tr><th>✔</th><th>Ubic.</th><th>Producto</th><th class="n">Cant.</th><th>Presentación</th><th class="n">Bonif.</th></tr></thead><tbody>
    <?php foreach ($items[$p['id']] ?? [] as $it): ?>
        <tr><td>☐</td><td><?= e($it['ubicacion']) ?></td><td><?= e($it['descripcion']) ?></td><td class="n"><strong><?= cant($it['cantidad']) ?></strong></td><td><?= e($it['presentacion_nombre']) ?></td><td class="n"><?= (float) $it['bonificacion_base'] > 0 ? cant($it['bonificacion_base']) . ' u.' : '' ?></td></tr>
    <?php endforeach; ?></tbody></table>
</div>
<?php endforeach; ?>
