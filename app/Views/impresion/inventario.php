<div class="hoja">
    <h1 class="h5">Planilla de inventario N° <?= (int) $inv['id'] ?> — <?= e($inv['deposito']) ?></h1>
    <p class="small"><?= e($inv['rubro'] ?? 'Todos los rubros') ?> · Generada <?= fecha_hora($inv['fecha']) ?> · Contó: ______________________</p>
    <table class="comp-items"><thead><tr><th>Ubic.</th><th>Código</th><th>Producto</th><th>Unidad</th><?php if ($conStock): ?><th class="n">Sistema</th><?php endif; ?><th class="n" style="width:120px">Contado</th></tr></thead><tbody>
    <?php foreach ($items as $it): ?><tr><td><?= e($it['ubicacion']) ?></td><td><?= e($it['codigo']) ?></td><td><?= e($it['descripcion']) ?></td><td><?= e($it['unidad_base']) ?></td><?php if ($conStock): ?><td class="n"><?= cant($it['stock_sistema']) ?></td><?php endif; ?><td class="n">__________</td></tr><?php endforeach; ?>
    </tbody></table>
    <p class="small mt-2 no-print"><a href="?con_stock=1">Mostrar stock del sistema</a> (para control; para el conteo a ciegas no mostrarlo).</p>
</div>
