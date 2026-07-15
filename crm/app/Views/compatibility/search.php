<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useSelect2', true); ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header">
        <h2 class="fs-5 mb-0">Busqueda de cuentas</h2>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('auth/searchClient') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label" for="r">Razon social</label>
                <input class="form-control" id="r" name="r" type="search">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="m">Marca</label>
                <input class="form-control" id="m" name="m" type="search">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="t">Tipo</label>
                <select class="form-select js-select2" id="t" name="t" data-placeholder="Tipo">
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= esc((string) $value, 'attr') ?>"><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-1 d-grid align-self-end">
                <button class="btn btn-outline-primary" type="submit" title="Buscar"><i class="feather-search"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
