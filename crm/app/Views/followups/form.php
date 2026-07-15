<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useSelect2', true); ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12 ">
        <div class="card stretch stretch-full">
            <form method="post"><?= csrf_field() ?><div class="card-body"><?php if ($errors !== []): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= esc($error) ?></div><?php endforeach ?></div><?php endif ?><div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Actividad</label><select class="form-select js-select2" name="actividad_id" required data-placeholder="Actividad">
                                <option value="">Seleccionar</option><?php foreach ($activities as $id => $label): ?><option value="<?= esc($id) ?>" <?= (int)($followUp['actividad_id'] ?? 0) === $id ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
                            </select></div>
                        <div class="col-md-4"><label class="form-label">Fecha</label><input class="form-control fecha" type="text" name="fecha" value="<?= esc($followUp['fecha'] ?? '') ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Hora</label><input class="form-control hora" type="text" name="hora" value="<?= esc(substr((string)($followUp['hora'] ?? ''), 0, 5)) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Cuenta</label><?php if ($isNew): ?><select class="form-select js-select2" name="cliente_id" required data-placeholder="Cuenta">
                                    <option value="">Seleccionar</option><?php foreach ($parents as $parent): ?><option value="<?= esc($parent['id']) ?>" <?= (string)($followUp['cliente_id'] ?? '') === (string)$parent['id'] ? 'selected' : '' ?>><?= esc($parent['text']) ?></option><?php endforeach ?>
                                </select><?php else: ?><input class="form-control" value="<?= esc($followUp['cliente'] ?? $followUp['cpotencial'] ?? '-') ?>" readonly><?php endif ?></div>
                        <div class="col-md-3"><label class="form-label">Monto</label><input class="form-control" type="number" step="0.01" name="monto" value="<?= esc($followUp['monto'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Estado</label><select class="form-select js-select2" name="estado_id" required data-placeholder="Estado">
                                <option value="">Seleccionar</option><?php foreach ($states as $id => $label): ?><option value="<?= esc($id) ?>" <?= (int)($followUp['estado_id'] ?? 0) === $id ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
                            </select></div>
                        <div class="col-md-6"><label class="form-label">Ejecutivo</label><select class="form-select js-select2" name="ejecutivo_id" required data-placeholder="Ejecutivo">
                                <option value="">Seleccionar</option><?php foreach ($executives as $id => $label): ?><option value="<?= esc($id) ?>" <?= (int)($followUp['ejecutivo_id'] ?? 0) === $id ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
                            </select></div>
                        <div class="col-md-6"><label class="form-label">Adjunto</label><input class="form-control" name="adjunto" value="<?= esc($followUp['adjunto'] ?? '') ?>" maxlength="250"></div>
                        <div class="col-12"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion" rows="4"><?= esc($followUp['descripcion'] ?? '') ?></textarea></div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="<?= site_url('seguimiento') ?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button></div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>