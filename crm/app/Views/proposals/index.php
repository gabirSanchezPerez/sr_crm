<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useDataTables', true); ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="get" action="<?= site_url('propuesta') ?>" class="d-flex gap-2">
            <input type="search" class="form-control" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="Buscar propuesta">
            <button class="btn btn-outline-primary" type="submit"><i class="feather-search"></i></button>
        </form>
        <?php if ($canAdd): ?><a class="btn btn-primary" href="<?= site_url('propuesta/add') ?>"><i class="feather-plus me-2"></i>Nueva</a><?php endif ?>
    </div>
    <div class="card-body p-0">
        <?php if ($proposals === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle js-datatable">
                    <thead><tr><th>Nombre</th><th>Cuenta</th><th>Contacto</th><th>Canal</th><th>Status</th><th>Monto</th><th class="text-end no-sort">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($proposals as $proposal): ?>
                        <tr>
                            <td><?= esc($proposal['nombre']) ?></td>
                            <td><?= esc($proposal['cliente'] ?? $proposal['cpotencial'] ?? '-') ?></td>
                            <td><?= esc($proposal['contacto'] ?? '-') ?></td>
                            <td><?= esc($proposal['canal'] ?? '-') ?></td>
                            <td><?= esc($proposal['estado'] ?? '-') ?></td>
                            <td><?= number_format((float) ($proposal['monto'] ?? 0), 2) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('propuesta/' . $proposal['id']) ?>" title="Ver"><i class="feather-eye"></i></a><?php if ($canEdit): ?> <a class="btn btn-sm btn-outline-primary" href="<?= site_url('propuesta/' . $proposal['id'] . '/edit') ?>" title="Editar"><i class="feather-edit-2"></i></a><?php endif ?><?php if ($canDelete): ?> <form method="post" action="<?= site_url('propuesta/delete/' . $proposal['id']) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit" title="Desactivar"><i class="feather-trash-2"></i></button></form><?php endif ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
