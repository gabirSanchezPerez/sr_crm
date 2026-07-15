<div class="card stretch stretch-full mt-4">
    <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h2 class="h6 mb-0">Propuestas</h2>
        <?php if ($canAddProposal): ?><a class="btn btn-sm btn-primary" href="<?= site_url('propuesta/add?parent_type=' . $parentType . '&parent_id=' . $parentId) ?>"><i class="feather-plus me-2"></i>Nueva</a><?php endif ?>
    </div>
    <div class="card-body p-0">
        <?php if ($proposals === []): ?><?= $this->include('components/empty_state') ?><?php else: ?>
            <div class="table-responsive"><table class="table table-hover mb-0 align-middle js-datatable" data-page-length="5"><thead><tr><th>Nombre</th><th>Contacto</th><th>Status</th><th>Monto</th><th class="text-end no-sort">Acciones</th></tr></thead><tbody><?php foreach ($proposals as $proposal): ?><tr><td><?= esc($proposal['nombre']) ?></td><td><?= esc($proposal['contacto'] ?? '-') ?></td><td><?= esc($proposal['estado'] ?? '-') ?></td><td><?= number_format((float) ($proposal['monto'] ?? 0), 2) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('propuesta/' . $proposal['id']) ?>" title="Ver"><i class="feather-eye"></i></a><?php if ($canEditProposal): ?> <a class="btn btn-sm btn-outline-primary" href="<?= site_url('propuesta/' . $proposal['id'] . '/edit') ?>" title="Editar"><i class="feather-edit-2"></i></a><?php endif ?></td></tr><?php endforeach ?></tbody></table></div>
        <?php endif ?>
    </div>
</div>
