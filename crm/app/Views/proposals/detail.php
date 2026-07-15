<?= $this->extend('layouts/app') ?>
<?php $this->setVar('useDataTables', true); ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="fs-5 mb-1"><?= esc($proposal['nombre']) ?></h2>
            <div class="text-muted fs-12"><?= esc($proposal['cliente'] ?? $proposal['cpotencial'] ?? '-') ?> · <?= esc($proposal['contacto'] ?? '-') ?></div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canAddFollowUp): ?><a class="btn btn-outline-primary" href="<?= site_url('seguimiento/add?propuesta_id=' . $proposal['id']) ?>"><i class="feather-calendar me-2"></i>Seguimiento</a><?php endif ?>
            <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= site_url('propuesta/' . $proposal['id'] . '/edit') ?>"><i class="feather-edit-2 me-2"></i>Editar</a><?php endif ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="text-muted small">Canal</div><div class="fw-semibold"><?= esc($proposal['canal'] ?? '-') ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Status</div><div class="fw-semibold"><?= esc($proposal['estado'] ?? '-') ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Monto</div><div class="fw-semibold"><?= number_format((float) ($proposal['monto'] ?? 0), 2) ?></div></div>
            <div class="col-md-3"><div class="text-muted small">Ejecutivo</div><div class="fw-semibold"><?= esc($proposal['ejecutivo'] ?? '-') ?></div></div>
            <?php if (trim((string) ($proposal['descripcion'] ?? '')) !== ''): ?><div class="col-12"><div class="text-muted small">Descripcion</div><div><?= esc($proposal['descripcion']) ?></div></div><?php endif ?>
        </div>
    </div>
</div>

<?= view('proposals/documents', ['proposal' => $proposal, 'documents' => $documents, 'canAddDocument' => $canAddDocument, 'canDeleteDocument' => $canDeleteDocument]) ?>
<?= view('followups/subpanel', ['followUps' => $followUps, 'parentType' => (int) ($proposal['cliente_id'] ?? 0) > 0 ? 'cliente' : 'cpotencial', 'parentId' => (int) (($proposal['cliente_id'] ?? 0) ?: ($proposal['cpotencial_id'] ?? 0)), 'proposalId' => (int) $proposal['id'], 'canAddFollowUp' => $canAddFollowUp, 'canEditFollowUp' => $canEditFollowUp, 'canDeleteFollowUp' => $canDeleteFollowUp]) ?>
<?= $this->endSection() ?>
