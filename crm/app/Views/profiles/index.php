<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useDataTables', true); ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex justify-content-end">
        <?php if ($canAdd): ?>
            <a class="btn btn-primary" href="<?= site_url('perfil/add') ?>"><i class="feather-plus me-2"></i>Nuevo</a>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle js-datatable">
                <thead><tr><th>Nombre</th><th class="text-end no-sort">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($profiles as $profile): ?>
                    <tr>
                        <td><?= esc($profile['nombre']) ?></td>
                        <td class="text-end d-flex gap-1 justify-content-end">
                            <?php if ($canEdit): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url('perfil/' . $profile['id']) ?>" title="Editar"><i class="feather-edit-2"></i></a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
