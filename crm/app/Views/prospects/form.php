<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useDataTables', ! $isNew); ?>
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
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="razon_social">Razon social</label>
                            <input class="form-control" id="razon_social" name="razon_social" value="<?= esc($prospect['razon_social'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="marca">Marca</label>
                            <input class="form-control" id="marca" name="marca" value="<?= esc($prospect['marca'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="rfc">RFC</label>
                            <input class="form-control" id="rfc" name="rfc" value="<?= esc($prospect['rfc'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="sector_id">Sector</label>
                            <select class="form-select js-select2" id="sector_id" name="sector_id" required data-placeholder="Sector">
                                <option value="">Seleccionar</option>
                                <?php foreach ($sectors as $id => $label): ?>
                                    <option value="<?= esc($id) ?>" <?= (int) ($prospect['sector_id'] ?? 0) === $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ejecutivo_id">Ejecutivo</label>
                            <select class="form-select js-select2" id="ejecutivo_id" name="ejecutivo_id" required data-placeholder="Ejecutivo">
                                <option value="">Seleccionar</option>
                                <?php foreach ($executives as $id => $label): ?>
                                    <option value="<?= esc($id) ?>" <?= (int) ($prospect['ejecutivo_id'] ?? 0) === $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="estado">Estado</label>
                            <input class="form-control" id="estado" name="estado" value="<?= esc($prospect['estado'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ciudad">Ciudad</label>
                            <input class="form-control" id="ciudad" name="ciudad" value="<?= esc($prospect['ciudad'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="cp">CP</label>
                            <input class="form-control" id="cp" name="cp" value="<?= esc($prospect['cp'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="direccion">Direccion</label>
                            <input class="form-control" id="direccion" name="direccion" value="<?= esc($prospect['direccion'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= site_url('cpotencial') ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if (! $isNew): ?>
    <?= view('contacts/subpanel', ['contacts' => $contacts, 'parentType' => 'cpotencial', 'parentId' => (int) ($prospect['id'] ?? 0), 'canAddContact' => $canAddContact, 'canEditContact' => $canEditContact, 'canDeleteContact' => $canDeleteContact]) ?>
    <?= view('documents/subpanel', ['documents' => $documents, 'parentType' => 'cpotencial', 'parentId' => (int) ($prospect['id'] ?? 0), 'canAddDocument' => $canAddDocument, 'canDeleteDocument' => $canDeleteDocument]) ?>
    <?= view('followups/subpanel', ['followUps' => $followUps, 'parentType' => 'cpotencial', 'parentId' => (int) ($prospect['id'] ?? 0), 'canAddFollowUp' => $canAddFollowUp, 'canEditFollowUp' => $canEditFollowUp, 'canDeleteFollowUp' => $canDeleteFollowUp]) ?>
<?php endif ?>
<?= $this->endSection() ?>
