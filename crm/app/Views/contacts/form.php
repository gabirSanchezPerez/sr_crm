<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useSelect2', true); ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12 ">
        <div class="card stretch stretch-full">
            <form method="post">
                <?= csrf_field() ?>
                <div class="card-body">
                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?= esc($error) ?></div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="cliente_id">Cuenta padre</label>
                            <?php $selectedParent = (int) ($contact['cliente_id'] ?? 0) > 0 ? ((int) $contact['cliente_id'] . '_1') : ((int) ($contact['cpotencial_id'] ?? 0) > 0 ? ((int) $contact['cpotencial_id'] . '_2') : (string) ($contact['cliente_id'] ?? '')); ?>
                            <select class="form-select js-select2" id="cliente_id" name="cliente_id" required data-placeholder="Cuenta padre">
                                <option value="">Seleccionar</option>
                                <?php foreach ($parents as $value => $label): ?>
                                    <option value="<?= esc($value) ?>" <?= $selectedParent === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input class="form-control" id="nombre" name="nombre" value="<?= esc($contact['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="correo">Correo</label>
                            <input class="form-control" id="correo" name="correo" type="email" value="<?= esc($contact['correo'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefono">Telefono</label>
                            <input class="form-control" id="telefono" name="telefono" value="<?= esc($contact['telefono'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="celular">Celular</label>
                            <input class="form-control" id="celular" name="celular" value="<?= esc($contact['celular'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="puesto">Puesto</label>
                            <input class="form-control" id="puesto" name="puesto" value="<?= esc($contact['puesto'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="departamento">Departamento</label>
                            <input class="form-control" id="departamento" name="departamento" value="<?= esc($contact['departamento'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="descripcion">Descripcion</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= esc($contact['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= site_url('contacto') ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
