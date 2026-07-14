<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?= view('documents/subpanel', ['documents' => $documents, 'parentType' => $parentType, 'parentId' => $parentId, 'canAddDocument' => false, 'canDeleteDocument' => $canDeleteDocument]) ?>
<?= $this->endSection() ?>