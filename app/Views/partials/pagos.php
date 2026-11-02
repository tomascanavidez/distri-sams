<?php /** Formas de pago (requiere en x-data: pagos[], agregarPago(), totalPagos(), total(), saldoACuenta(), vuelto()). $cuentas: [id => nombre] */ ?>
<div class="pagos">
    <div class="d-flex flex-wrap gap-2 mb-2">
        <?php foreach (formas_pago() as $k => $t): if ($k === 'retencion') continue; ?>
            <button type="button" class="btn btn-outline-secondary btn-touch" @click="agregarPago('<?= $k ?>')"><i class="bi bi-plus"></i> <?= e($t) ?></button>
        <?php endforeach; ?>
    </div>
    <template x-for="(p, i) in pagos" :key="i">
        <div class="border rounded p-2 mb-2">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3"><label class="form-label small mb-0">Forma</label>
                    <select class="form-select" x-model="p.forma"><?= opciones(formas_pago(), '') ?></select></div>
                <div class="col-6 col-md-3"><label class="form-label small mb-0">Importe</label>
                    <input class="form-control text-end fw-bold" inputmode="decimal" x-model="p.importe" @focus="$event.target.select()"></div>
                <template x-if="p.forma === 'efectivo'"><div class="col-6 col-md-3"><label class="form-label small mb-0">Paga con</label>
                    <input class="form-control text-end" inputmode="decimal" x-model="p.entregado" placeholder="Para calcular vuelto"></div></template>
                <template x-if="['transferencia', 'mercadopago', 'tarjeta'].includes(p.forma)"><div class="col-6 col-md-3"><label class="form-label small mb-0">Cuenta destino</label>
                    <select class="form-select" x-model="p.cuenta_bancaria_id"><?= opciones($cuentas ?? [], '', 'Automática') ?></select></div></template>
                <template x-if="p.forma !== 'efectivo'"><div class="col-6 col-md-2"><label class="form-label small mb-0">Referencia</label>
                    <input class="form-control" x-model="p.referencia" placeholder="N° operación"></div></template>
                <div class="col-auto ms-auto"><button type="button" class="btn btn-outline-danger" @click="pagos.splice(i, 1)" aria-label="Quitar pago"><i class="bi bi-x-lg"></i></button></div>
            </div>
            <template x-if="p.forma === 'cheque'">
                <div class="row g-2 mt-1">
                    <div class="col-6 col-md-3"><input class="form-control" placeholder="N° de cheque" x-model="p.cheque.numero" inputmode="numeric"></div>
                    <div class="col-6 col-md-4"><input class="form-control" placeholder="Banco" x-model="p.cheque.banco"></div>
                    <div class="col-12 col-md-3"><label class="form-label small mb-0">Fecha de pago</label><input type="date" class="form-control" x-model="p.cheque.fecha_pago"></div>
                </div>
            </template>
        </div>
    </template>
    <div class="d-flex flex-wrap justify-content-between gap-2 small">
        <span>Pagado: <strong x-text="DT.dinero(totalPagos())"></strong></span>
        <span x-show="saldoACuenta() > 0.009" class="text-warning-emphasis">A cuenta corriente: <strong x-text="DT.dinero(saldoACuenta())"></strong></span>
        <span x-show="saldoACuenta() < -0.009" class="text-danger">Los pagos superan el total</span>
        <span x-show="vuelto() > 0" class="fs-6">Vuelto: <strong class="text-success" x-text="DT.dinero(vuelto())"></strong></span>
    </div>
</div>
