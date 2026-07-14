<?php if ($followUps === []): ?>
    <?= $this->include('components/empty_state') ?>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Actividad</th>
                    <th>Estado</th>
                    <th>Cuenta</th>
                    <th>Ejecutivo</th>
                    <th>Monto</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($followUps as $followUp): ?>
                    <tr>
                        <td><?= esc($followUp['fecha']) ?> <?= esc(substr((string)$followUp['hora'], 0, 5)) ?></td>
                        <td><?= esc($followUp['actividad']) ?></td>
                        <td><?= esc($followUp['estado']) ?></td>
                        <td><?= esc($followUp['cliente'] ?? $followUp['cpotencial'] ?? '-') ?></td>
                        <td><?= esc($followUp['ejecutivo']) ?></td>
                        <td><?= $followUp['monto'] === null ? '-' : number_format((float)$followUp['monto'], 2) ?></td>
                        <td class="text-end"><?php if ($canEdit): ?><a class="btn btn-sm btn-outline-primary" href="<?= site_url('seguimiento/' . $followUp['id']) ?>" title="Editar"><i class="feather-edit-2"></i></a><?php endif ?><?php if ($canDelete): ?><form method="post" action="<?= site_url('seguimiento/delete/' . $followUp['id']) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit" title="Desactivar"><i class="feather-trash-2"></i></button></form><?php endif ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>