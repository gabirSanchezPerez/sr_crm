<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <h2 class="h5 mb-0">Seguimientos</h2><?php if ($canAdd): ?><a class="btn btn-primary" href="<?= site_url('seguimiento/add') ?>"><i class="feather-plus me-2"></i>Nuevo</a><?php endif ?>
    </div>
    <div class="card-body p-0"><?= view('followups/table', ['followUps' => $followUps, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?></div>
</div>
<?= $this->endSection() ?>