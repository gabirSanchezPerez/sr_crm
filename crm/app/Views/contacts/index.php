<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="get" action="<?= site_url('contacto') ?>" class="d-flex gap-2">
            <input type="search" class="form-control" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="Buscar contacto">
            <button class="btn btn-outline-primary" type="submit"><i class="feather-search"></i></button>
        </form>
        <?php if ($canAdd): ?>
            <a class="btn btn-primary" href="<?= site_url('contacto/add') ?>"><i class="feather-plus me-2"></i>Nuevo</a>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
        <?php if ($contacts === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Telefono</th>
                        <th>Celular</th>
                        <th>Cuenta</th>
                        <th>Tipo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contacts as $contact): ?>
                        <?php $isCustomer = (int) ($contact['cliente_id'] ?? 0) > 0; ?>
                        <tr>
                            <td><?= esc($contact['nombre']) ?></td>
                            <td><?= esc($contact['correo']) ?></td>
                            <td><?= esc($contact['telefono']) ?></td>
                            <td><?= esc($contact['celular'] ?: '-') ?></td>
                            <td><?= esc(($isCustomer ? $contact['cliente'] : $contact['cpotencial']) ?? '-') ?></td>
                            <td><span class="badge bg-<?= $isCustomer ? 'success' : 'primary' ?>"><?= $isCustomer ? 'Cliente' : 'Prospecto' ?></span></td>
                            <td class="text-end">
                                <?php if ($canEdit): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('contacto/' . $contact['id']) ?>" title="Editar"><i class="feather-edit-2"></i></a>
                                <?php endif ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= site_url('contacto/delete/' . $contact['id']) ?>" class="d-inline">
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