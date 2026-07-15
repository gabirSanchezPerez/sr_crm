<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useDataTables', true); ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="get" action="<?= site_url('cliente') ?>" class="d-flex gap-2">
            <input type="search" class="form-control" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="Buscar cliente">
            <button class="btn btn-outline-primary" type="submit"><i class="feather-search"></i></button>
        </form>
        <?php if ($canAdd): ?>
            <a class="btn btn-primary" href="<?= site_url('cliente/add') ?>"><i class="feather-plus me-2"></i>Nuevo</a>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
        <?php if ($customers === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle js-datatable">
                    <thead>
                    <tr>
                        <th>Razon social</th>
                        <th>Marca</th>
                        <th>RFC</th>
                        <th>Sector</th>
                        <th>Gestion</th>
                        <th>Ejecutivo</th>
                        <th class="text-end no-sort">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= esc($customer['razon_social']) ?></td>
                            <td><?= esc($customer['marca']) ?></td>
                            <td><?= esc($customer['rfc'] ?: '-') ?></td>
                            <td><?= esc($customer['sector'] ?? '-') ?></td>
                            <td><?= esc($customer['cgestion'] ?? '-') ?></td>
                            <td><?= esc($customer['ejecutivo'] ?? '-') ?></td>
                            <td class="text-end d-flex gap-1 justify-content-end">
                                <?php if ($canEdit): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('cliente/' . $customer['id']) ?>" title="Editar"><i class="feather-edit-2"></i></a>
                                <?php endif ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= site_url('cliente/delete/' . $customer['id']) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Desactivar"><i class="feather-trash-2"></i></button>
                                    </form>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
