<?= $this->extend('layouts/app') ?>

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
                            <input class="form-control" id="razon_social" name="razon_social" value="<?= esc($customer['razon_social'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="marca">Marca</label>
                            <input class="form-control" id="marca" name="marca" value="<?= esc($customer['marca'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="rfc">RFC</label>
                            <input class="form-control" id="rfc" name="rfc" value="<?= esc($customer['rfc'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="sector_id">Sector</label>
                            <select class="form-select" id="sector_id" name="sector_id" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($sectors as $id => $label): ?>
                                    <option value="<?= esc($id) ?>" <?= (int) ($customer['sector_id'] ?? 0) === $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ejecutivo_id">Ejecutivo</label>
                            <select class="form-select" id="ejecutivo_id" name="ejecutivo_id" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($executives as $id => $label): ?>
                                    <option value="<?= esc($id) ?>" <?= (int) ($customer['ejecutivo_id'] ?? 0) === $id ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <input type="hidden" name="cpotencial_id" value="<?= esc($customer['cpotencial_id'] ?? '') ?>">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="estado">Estado</label>
                            <input class="form-control" id="estado" name="estado" value="<?= esc($customer['estado'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ciudad">Ciudad</label>
                            <input class="form-control" id="ciudad" name="ciudad" value="<?= esc($customer['ciudad'] ?? '') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="cp">CP</label>
                            <input class="form-control" id="cp" name="cp" value="<?= esc($customer['cp'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="direccion">Direccion</label>
                            <input class="form-control" id="direccion" name="direccion" value="<?= esc($customer['direccion'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= site_url('cliente') ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if (! $isNew): ?>
    <?= view('contacts/subpanel', ['contacts' => $contacts, 'parentType' => 'cliente', 'parentId' => (int) ($customer['id'] ?? 0), 'canAddContact' => $canAddContact, 'canEditContact' => $canEditContact, 'canDeleteContact' => $canDeleteContact]) ?>
    <?= view('documents/subpanel', ['documents' => $documents, 'parentType' => 'cliente', 'parentId' => (int) ($customer['id'] ?? 0), 'canAddDocument' => $canAddDocument, 'canDeleteDocument' => $canDeleteDocument]) ?>
    <?= view('followups/subpanel', ['followUps' => $followUps, 'parentType' => 'cliente', 'parentId' => (int) ($customer['id'] ?? 0), 'canAddFollowUp' => $canAddFollowUp, 'canEditFollowUp' => $canEditFollowUp, 'canDeleteFollowUp' => $canDeleteFollowUp]) ?>
<?php endif ?>
<?= $this->endSection() ?>
