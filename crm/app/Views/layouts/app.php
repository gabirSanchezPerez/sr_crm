<!doctype html>
<?php
$useDataTables = (bool) ($useDataTables ?? false);
$useSelect2 = (bool) ($useSelect2 ?? false);
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CRM comercial">
    <title><?= esc($title ?? 'CRM') ?></title>
    <link rel="icon" href="<?= base_url('assets/images/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendors/css/vendors.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendors/css/sweetalert2.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url(); ?>assets/vendors/css/jquery.datetimepicker.min.css">
    <?php if ($useDataTables): ?>
        <link rel="stylesheet" href="<?= base_url('assets/vendors/css/dataTables.bs5.min.css') ?>">
    <?php endif ?>
    <?php if ($useSelect2): ?>
        <link rel="stylesheet" href="<?= base_url('assets/vendors/css/select2.min.css') ?>">
        <link rel="stylesheet" href="<?= base_url('assets/vendors/css/select2-theme.min.css') ?>">
    <?php endif ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/theme.min.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body>
<?= $this->include('components/sidebar') ?>
<?= $this->include('components/topbar') ?>
<main class="nxl-container">
    <div class="nxl-content">
        <?= $this->include('components/page_header') ?>
        <div class="main-content">
            <?= $this->include('components/alerts') ?>
            <?= $this->renderSection('content') ?>
        </div>
        <?= $this->include('components/footer') ?>
    </div>
</main>
<script src="<?= base_url('assets/vendors/js/vendors.min.js') ?>"></script>
<script src="<?= base_url('assets/vendors/js/sweetalert2.all.min.js') ?>"></script>
<script src="<?php echo base_url(); ?>/assets/vendors/js/jquery.datetimepicker.full.min.js"></script>  
<?php if ($useDataTables): ?>
    <script src="<?= base_url('assets/vendors/js/dataTables.min.js') ?>"></script>
    <script src="<?= base_url('assets/vendors/js/dataTables.bs5.min.js') ?>"></script>
<?php endif ?>
<?php if ($useSelect2): ?>
    <script src="<?= base_url('assets/vendors/js/select2.min.js') ?>"></script>
<?php endif ?>
<script src="<?= base_url('assets/js/common-init.min.js') ?>"></script>
<script src="<?= base_url('assets/js/theme-customizer-init.min.js') ?>"></script>
<?php if ($useDataTables || $useSelect2): ?>
    <script src="<?= base_url('assets/js/crm-enhancements.js') ?>"></script>
<?php endif ?>
<script src="<?= base_url('assets/js/site.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
