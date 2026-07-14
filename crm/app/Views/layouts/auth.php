<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acceso al CRM comercial">
    <title><?= esc($title ?? 'Acceso | CRM') ?></title>
    <link rel="icon" href="<?= base_url('assets/images/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendors/css/vendors.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/theme.min.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>
<body class="bg-light">
<main class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="w-100" style="max-width: 440px">
        <?= $this->include('components/alerts') ?>
        <?= $this->renderSection('content') ?>
    </div>
</main>
<script src="<?= base_url('assets/vendors/js/vendors.min.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
