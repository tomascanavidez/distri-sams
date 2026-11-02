<div class="encabezado"><h1>Cargar factura de proveedor</h1></div>
<div class="card shadow-sm" style="max-width:900px" x-data="{ v: { neto_gravado: '', no_gravado: '', iva_21: '', iva_105: '', iva_27: '', percepcion_iva: '', percepcion_iibb: '', impuestos_internos: '', otros: '' },
        total() { return Object.values(this.v).reduce((s, x) => s + DT.parse(x), 0); }, calcIva() { this.v.iva_21 = DT.numero(DT.parse(this.v.neto_gravado) * 0.21).replace(/\./g, ''); } }"><div class="card-body">
<form method="post" class="row g-3">
    <?= csrf_campo() ?>
    <div class="col-md-6"><label class="form-label">Proveedor <span class="text-danger">*</span></label><select class="form-select" name="proveedor_id" required><?= opciones($proveedores, $prov, 'Elegí el proveedor') ?></select></div>
    <div class="col-md-6"><label class="form-label">Recepción asociada (opcional)</label><select class="form-select" name="recepcion_id"><?= opciones($recepciones, '', 'Ninguna') ?></select></div>
    <div class="col-4 col-md-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo"><?= opciones(['FA' => 'Factura A', 'FB' => 'Factura B', 'FC' => 'Factura C', 'NCA' => 'NC A', 'NCB' => 'NC B', 'NCC' => 'NC C', 'NDA' => 'ND A', 'NDB' => 'ND B', 'NDC' => 'ND C'], 'FA') ?></select></div>
    <div class="col-4 col-md-2"><label class="form-label">Punto de venta</label><input class="form-control" name="punto_venta" inputmode="numeric" required></div>
    <div class="col-4 col-md-3"><label class="form-label">Número</label><input class="form-control" name="numero" inputmode="numeric" required></div>
    <div class="col-6 col-md-2"><label class="form-label">Fecha</label><input type="date" class="form-control" name="fecha" value="<?= hoy() ?>" required></div>
    <div class="col-6 col-md-3"><label class="form-label">Vencimiento</label><input type="date" class="form-control" name="vencimiento"><div class="form-text">Vacío = plazo del proveedor.</div></div>
    <?php foreach (['neto_gravado' => 'Neto gravado', 'no_gravado' => 'No gravado / exento', 'iva_21' => 'IVA 21 %', 'iva_105' => 'IVA 10,5 %', 'iva_27' => 'IVA 27 %', 'percepcion_iva' => 'Percepción IVA', 'percepcion_iibb' => 'Percepción IIBB', 'impuestos_internos' => 'Impuestos internos', 'otros' => 'Otros'] as $k => $t): ?>
        <div class="col-6 col-md-4"><label class="form-label"><?= $t ?></label><input class="form-control text-end" name="<?= $k ?>" inputmode="decimal" x-model="v.<?= $k ?>" <?= $k === 'neto_gravado' ? '@change="if (!v.iva_21) calcIva()"' : '' ?>></div>
    <?php endforeach; ?>
    <div class="col-md-6"><label class="form-label">Total</label><input class="form-control form-control-lg text-end fw-bold" name="total" :value="DT.numero(total()).replace(/\./g, '')" readonly></div>
    <div class="col-md-6"><label class="form-label">Observaciones</label><input class="form-control" name="observaciones" maxlength="255"></div>
    <div class="col-12"><button class="btn btn-primary btn-touch"><i class="bi bi-check-lg"></i> Registrar</button></div>
</form>
</div></div>
