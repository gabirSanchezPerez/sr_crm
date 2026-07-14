<?php
$path = trim(service('uri')->getPath(), '/');
$grants = $permissions ?? session('permissions') ?? [];
$items = [
    ['permission' => 'dashboard.index', 'path' => 'home', 'match' => ['', 'home'], 'icon' => 'feather-airplay', 'label' => 'Dashboard'],
    ['permission' => 'cpotencial.index', 'path' => 'cpotencial', 'match' => ['cpotencial'], 'icon' => 'feather-target', 'label' => 'Prospectos'],
    ['permission' => 'cliente.index', 'path' => 'cliente', 'match' => ['cliente'], 'icon' => 'feather-users', 'label' => 'Clientes'],
    ['permission' => 'contacto.index', 'path' => 'contacto', 'match' => ['contacto'], 'icon' => 'feather-book', 'label' => 'Contactos'],
    ['permission' => 'seguimiento.index', 'path' => 'seguimiento', 'match' => ['seguimiento'], 'icon' => 'feather-calendar', 'label' => 'Seguimientos'],
    ['permission' => 'reporte.index', 'path' => 'reporte/seguimiento', 'match' => ['reporte'], 'icon' => 'feather-bar-chart-2', 'label' => 'Reportes'],
    ['permission' => 'marca.index', 'path' => 'marca', 'match' => ['marca'], 'icon' => 'feather-tag', 'label' => 'Marcas'],
    ['permission' => 'ucomercial.index', 'path' => 'ucomercial', 'match' => ['ucomercial'], 'icon' => 'feather-briefcase', 'label' => 'Unidades comerciales'],
    ['permission' => 'cgestion.index', 'path' => 'cgestion', 'match' => ['cgestion'], 'icon' => 'feather-compass', 'label' => 'Canales de gestion'],
    ['permission' => 'estado.index', 'path' => 'estado', 'match' => ['estado'], 'icon' => 'feather-check-square', 'label' => 'Estados'],
    ['permission' => 'sector.index', 'path' => 'sector', 'match' => ['sector'], 'icon' => 'feather-grid', 'label' => 'Sectores'],
    ['permission' => 'usuario.index', 'path' => 'usuario', 'match' => ['usuario', 'profile'], 'icon' => 'feather-user', 'label' => 'Usuarios'],
    ['permission' => 'perfil.index', 'path' => 'perfil', 'match' => ['perfil'], 'icon' => 'feather-shield', 'label' => 'Perfiles'],
];
$isActive = static function (array $prefixes) use ($path): bool {
    foreach ($prefixes as $prefix) {
        if ($path === $prefix || ($prefix !== '' && str_starts_with($path, $prefix . '/'))) {
            return true;
        }
    }
    return false;
};
?>
<nav class="nxl-navigation" aria-label="Navegación principal">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="<?= site_url('/') ?>" class="b-brand" aria-label="CRM - Inicio">
                <img src="<?= base_url('assets/images/logo-full.png') ?>" alt="CRM" class="logo logo-lg">
                <img src="<?= base_url('assets/images/logo-abbr.png') ?>" alt="" class="logo logo-sm">
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                <li class="nxl-item nxl-caption"><label>CRM</label></li>
                <?php foreach ($items as $item): ?>
                    <?php if (in_array($item['permission'], $grants, true)): ?>
                        <li class="nxl-item"><a class="nxl-link<?= $isActive($item['match']) ? ' active' : '' ?>" href="<?= site_url($item['path']) ?>"><span class="nxl-micon"><i class="<?= esc($item['icon'], 'attr') ?>"></i></span><span class="nxl-mtext"><?= esc($item['label']) ?></span></a></li>
                    <?php endif ?>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</nav>
