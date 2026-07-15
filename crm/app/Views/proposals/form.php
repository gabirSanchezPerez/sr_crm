<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useSelect2', true); ?>

<?= $this->section('content') ?>
<?php $selectedParent = (string) ($proposal['cliente_id'] ?? ''); ?>
<?php if (! str_contains($selectedParent, '_')): ?>
    <?php $selectedParent = (int) ($proposal['cliente_id'] ?? 0) > 0 ? ((int) $proposal['cliente_id'] . '_1') : ((int) ($proposal['cpotencial_id'] ?? 0) > 0 ? ((int) $proposal['cpotencial_id'] . '_2') : ''); ?>
<?php endif ?>
<div class="row">
    <div class="col-12 ">
        <div class="card stretch stretch-full">
            <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="card-body"><?php if ($errors !== []): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= esc($error) ?></div><?php endforeach ?></div><?php endif ?><div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" value="<?= esc($proposal['nombre'] ?? '') ?>" required></div>
                        <div class="col-md-3"><label class="form-label" for="canal_id">Canal</label><select class="form-select js-select2" id="canal_id" name="canal_id" required data-placeholder="Canal">
                                <option value="">Seleccionar</option><?php foreach ($channels as $id => $label): ?><option value="<?= esc($id) ?>" <?= (int) ($proposal['canal_id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
                            </select></div>
                        <div class="col-md-3"><label class="form-label" for="monto">Monto</label><input class="form-control" id="monto" name="monto" type="number" step="0.01" value="<?= esc($proposal['monto'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="cliente_id">Cliente o prospecto</label><select class="form-select js-select2" id="cliente_id" name="cliente_id" required data-placeholder="Cuenta">
                                <option value="">Seleccionar</option><?php foreach ($parents as $parent): ?><option value="<?= esc($parent['id']) ?>" <?= $selectedParent === (string) $parent['id'] ? 'selected' : '' ?>><?= esc($parent['text']) ?></option><?php endforeach ?>
                            </select></div>
                        <div class="col-md-3"><label class="form-label" for="contacto_id">Contacto</label><select class="form-select js-select2" id="contacto_id" name="contacto_id" required data-placeholder="Contacto">
                                <option value="">Seleccionar</option><?php foreach ($contacts as $id => $label): ?><option value="<?= esc($id) ?>" <?= (int) ($proposal['contacto_id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
                            </select></div>
                        <div class="col-md-3"><label class="form-label" for="estado_id">Status</label><select class="form-select js-select2" id="estado_id" name="estado_id" required data-placeholder="Status">
                                <option value="">Seleccionar</option><?php foreach ($states as $id => $label): ?><option value="<?= esc($id) ?>" <?= (int) ($proposal['estado_id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
                            </select></div>
                        <div class="col-12"><label class="form-label" for="descripcion">Descripcion</label><textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= esc($proposal['descripcion'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label" for="documentos">Documentos</label><input class="form-control" id="documentos" name="documentos[]" type="file" multiple></div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="<?= site_url('propuesta') ?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button></div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>