<div class="card stretch stretch-full mt-4">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <h2 class="h6 mb-0">Documentos</h2>
        <?php if ($canAddDocument && $parentType !== '' && (int) $parentId > 0): ?>
            <form class="d-flex gap-2 align-items-center flex-wrap" method="post" action="<?= site_url('documento/addSubpanel') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="parent_type" value="<?= esc($parentType) ?>">
                <input type="hidden" name="parent_id" value="<?= esc($parentId) ?>">
                <input class="form-control form-control-sm" name="nombre" placeholder="Nombre" maxlength="245">
                <input class="form-control form-control-sm" type="file" name="archivo" required>
                <button class="btn btn-sm btn-primary text-nowrap" type="submit"><i class="feather-upload me-2"></i>Subir</button>
            </form>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
        <?php if ($documents === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle js-datatable" data-page-length="5">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Archivo</th>
                        <th>Cuenta</th>
                        <th>Tamano</th>
                        <th>Creado por</th>
                        <th class="text-end no-sort">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><?= esc($document['nombre']) ?></td>
                            <td><?= esc($document['archivo_original']) ?></td>
                            <td><?= esc($document['cliente'] ?? $document['cpotencial'] ?? '-') ?></td>
                            <td><?= number_format(((int) ($document['tamano'] ?? 0)) / 1024, 1) ?> KB</td>
                            <td><?= esc($document['creador'] ?? '-') ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url('documento/download/' . $document['id']) ?>" title="Descargar"><i class="feather-download"></i></a>
                                <?php if ($canDeleteDocument): ?>
                                    <form method="post" action="<?= site_url('documento/delete/' . $document['id']) ?>" class="d-inline">
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
