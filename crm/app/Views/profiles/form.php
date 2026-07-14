<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12 col-xl-6">
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
                    <label class="form-label" for="nombre">Nombre</label>
                    <input class="form-control" id="nombre" name="nombre" value="<?= esc($profile['nombre'] ?? '') ?>" required>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= site_url('perfil') ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
