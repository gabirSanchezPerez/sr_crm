<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useSelect2', true); ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12 col-xl-8">
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
                        <div class="col-md-6">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input class="form-control" id="nombre" name="nombre" value="<?= esc($user['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="usuario">Usuario</label>
                            <input class="form-control" id="usuario" name="usuario" value="<?= esc($user['usuario'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="correo">Correo</label>
                            <input class="form-control" id="correo" name="correo" type="email" value="<?= esc($user['correo'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contrasenia">Contrasena</label>
                            <input class="form-control" id="contrasenia" name="contrasenia" type="password" <?= $isNew ? 'required' : '' ?> autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="perfil_id">Perfil</label>
                            <select class="form-select js-select2" id="perfil_id" name="perfil_id" required data-placeholder="Perfil">
                                <option value="">Seleccionar</option>
                                <?php foreach ($profiles as $id => $name): ?>
                                    <option value="<?= esc($id) ?>" <?= (int) ($user['perfil_id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cgestion_id">Canal de gestion</label>
                            <select class="form-select js-select2" id="cgestion_id" name="cgestion_id" required data-placeholder="Canal de gestion">
                                <option value="">Seleccionar</option>
                                <?php foreach ($managementChannels as $id => $name): ?>
                                    <option value="<?= esc($id) ?>" <?= (int) ($user['cgestion_id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ucomercial_id">Unidades comerciales</label>
                            <select class="form-select js-select2" id="ucomercial_id" name="ucomercial_id[]" multiple size="6" data-placeholder="Unidades comerciales">
                                <?php foreach ($commercialUnits as $id => $name): ?>
                                    <option value="<?= esc($id) ?>" <?= in_array((int) $id, $selectedUnits, true) ? 'selected' : '' ?>><?= esc($name) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= site_url('usuario') ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
