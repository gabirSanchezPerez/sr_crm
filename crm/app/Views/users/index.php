<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="get" action="<?= site_url('usuario') ?>" class="d-flex gap-2">
            <input type="search" class="form-control" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="Buscar usuario">
            <button class="btn btn-outline-primary" type="submit"><i class="feather-search"></i></button>
        </form>
        <?php if ($canAdd): ?>
            <a class="btn btn-primary" href="<?= site_url('usuario/add') ?>"><i class="feather-user-plus me-2"></i>Nuevo</a>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
        <?php if ($users === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Perfil</th>
                        <th>Gestion</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= esc($user['nombre']) ?></td>
                            <td><?= esc($user['usuario']) ?></td>
                            <td><?= esc($user['correo']) ?></td>
                            <td><?= esc($user['perfil'] ?? '-') ?></td>
                            <td><?= esc($user['cgestion'] ?? '-') ?></td>
                            <td class="text-end">
                                <?php if ($canEdit): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('profile/' . $user['id']) ?>" title="Editar"><i class="feather-edit-2"></i></a>
                                <?php endif ?>
                                <?php if ($canEdit && $isFullAdmin): ?>
                                    <a class="btn btn-sm btn-outline-warning" href="<?= site_url('usuario/transferAccount/' . $user['id']) ?>" title="Transferir"><i class="feather-repeat"></i></a>
                                <?php endif ?>
                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= site_url('auth/delete/' . $user['id']) ?>" class="d-inline">
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
