<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CRM comercial">
    <title><?= esc($title ?? 'CRM') ?></title>
    <link rel="icon" href="<?= base_url('assets/images/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendors/css/vendors.min.css') ?>">
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
<script src="<?= base_url('assets/js/common-init.min.js') ?>"></script>
<script src="<?= base_url('assets/js/theme-customizer-init.min.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
