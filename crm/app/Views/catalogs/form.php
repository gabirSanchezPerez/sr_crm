<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12 col-xl-7">
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
                        <?php foreach ($catalog->fields as $field): ?>
                            <div class="col-12">
                                <label class="form-label" for="<?= esc($field->name) ?>"><?= esc($field->label) ?></label>
                                <input class="form-control" id="<?= esc($field->name) ?>" name="<?= esc($field->name) ?>" type="<?= esc($field->type) ?>" value="<?= esc($record[$field->name] ?? '') ?>" required>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= site_url($catalog->route) ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
