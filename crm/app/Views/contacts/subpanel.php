<div class="card stretch stretch-full mt-4">
    <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h2 class="h6 mb-0">Contactos</h2>
        <?php if ($canAddContact): ?>
            <a class="btn btn-sm btn-primary" href="<?= site_url('contacto/add?parent_type=' . $parentType . '&parent_id=' . $parentId) ?>"><i class="feather-plus me-2"></i>Nuevo</a>
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
                        <th>Telefono</th>
                        <th>Celular</th>
                        <th>Correo</th>
                        <th>Puesto</th>
                        <th>Departamento</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td><?= esc($contact['nombre']) ?></td>
                            <td><?= esc($contact['telefono']) ?></td>
                            <td><?= esc($contact['celular'] ?: '-') ?></td>
                            <td><?= esc($contact['correo']) ?></td>
                            <td><?= esc($contact['puesto'] ?: '-') ?></td>
                            <td><?= esc($contact['departamento'] ?: '-') ?></td>
                            <td class="text-end">
                                <?php if ($canEditContact): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= site_url('contacto/' . $contact['id']) ?>" title="Editar"><i class="feather-edit-2"></i></a>
                                <?php endif ?>
                                <?php if ($canDeleteContact): ?>
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