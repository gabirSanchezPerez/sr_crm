<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="get" action="<?= site_url('cpotencial') ?>" class="d-flex gap-2">
            <input type="search" class="form-control" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="Buscar prospecto">
            <button class="btn btn-outline-primary" type="submit"><i class="feather-search"></i></button>
        </form>
        <?php if ($canAdd): ?>
            <a class="btn btn-primary" href="<?= site_url('cpotencial/add') ?>"><i class="feather-plus me-2"></i>Nuevo</a>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
        <?php if ($prospects === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Razon social</th>
                        <th>Marca</th>
                        <th>RFC</th>
                        <th>Sector</th>
                        <th>Ejecutivo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($prospects as $prospect): ?>
                        <tr>
                            <td><?= esc($prospect['razon_social']) ?></td>
                            <td><?= esc($prospect['marca']) ?></td>
                            <td><?= esc($prospect['rfc'] ?: '-') ?></td>
                            <td><?= esc($prospect['sector'] ?? '-') ?></td>
                            <td><?= esc($prospect['ejecutivo'] ?? '-') ?></td>
                            <td class="text-end">
                                <?php if ($canEdit): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('cpotencial/' . $prospect['id']) ?>" title="Editar"><i class="feather-edit-2"></i></a>
                                <?php endif ?>
                                <?php if ($canConvert && trim((string) ($prospect['rfc'] ?? '')) !== ''): ?>
                                    <form method="post" action="<?= site_url('cpotencial/convert/' . $prospect['id']) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-success" type="submit" title="Convertir"><i class="feather-repeat"></i></button>
                                    </form>
                                <?php endif ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= site_url('cpotencial/delete/' . $prospect['id']) ?>" class="d-inline">
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

