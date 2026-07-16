<?php
$path = trim(service('uri')->getPath(), '/');
$grants = $permissions ?? session('permissions') ?? [];
$items = [
    ['permission' => 'dashboard.index', 'path' => 'home', 'match' => ['', 'home'], 'icon' => 'feather-airplay', 'label' => 'Dashboard', 'grupo' => 'Dashboard'],
    ['permission' => 'cpotencial.index', 'path' => 'cpotencial', 'match' => ['cpotencial'], 'icon' => 'feather-target', 'label' => 'Clientes Potenciales', 'grupo' => 'Cartera'],
    ['permission' => 'cliente.index', 'path' => 'cliente', 'match' => ['cliente'], 'icon' => 'feather-users', 'label' => 'Anunciantes', 'grupo' => 'Cartera'],
    ['permission' => 'contacto.index', 'path' => 'contacto', 'match' => ['contacto'], 'icon' => 'feather-book', 'label' => 'Contactos', 'grupo' => 'Cartera'],
    ['permission' => 'propuesta.index', 'path' => 'propuesta', 'match' => ['propuesta'], 'icon' => 'feather-file-text', 'label' => 'Propuestas', 'grupo' => 'Gestión'],
    ['permission' => 'meta.index', 'path' => 'meta', 'match' => ['meta'], 'icon' => 'feather-target', 'label' => 'Metas', 'grupo' => 'Gestión'],
    ['permission' => 'seguimiento.index', 'path' => 'seguimiento', 'match' => ['seguimiento'], 'icon' => 'feather-calendar', 'label' => 'Seguimientos', 'grupo' => 'Gestión'],
    ['permission' => 'reporte.index', 'path' => 'reporte/seguimiento', 'match' => ['reporte'], 'icon' => 'feather-bar-chart', 'label' => 'Reporte Seguimiento', 'grupo' => 'Reportes'],
    ['permission' => 'reporte.index', 'path' => 'reporte/cartera', 'match' => ['reporte'], 'icon' => 'feather-bar-chart-2', 'label' => 'Reporte Cartera', 'grupo' => 'Reportes'],
    ['permission' => 'ucomercial.index', 'path' => 'ucomercial', 'match' => ['ucomercial'], 'icon' => 'feather-briefcase', 'label' => 'Unidades comerciales', 'grupo' => 'Administración'],
    ['permission' => 'cgestion.index', 'path' => 'cgestion', 'match' => ['cgestion'], 'icon' => 'feather-compass', 'label' => 'Centros de gestion', 'grupo' => 'Administración'],
    ['permission' => 'estado.index', 'path' => 'estado', 'match' => ['estado'], 'icon' => 'feather-check-square', 'label' => 'Estatus', 'grupo' => 'Administración'],
    ['permission' => 'sector.index', 'path' => 'sector', 'match' => ['sector'], 'icon' => 'feather-grid', 'label' => 'Sectores', 'grupo' => 'Administración'],
    ['permission' => 'usuario.index', 'path' => 'usuario', 'match' => ['usuario', 'profile'], 'icon' => 'feather-user', 'label' => 'Usuarios', 'grupo' => 'Administración'],
    ['permission' => 'perfil.index', 'path' => 'perfil', 'match' => ['perfil'], 'icon' => 'feather-shield', 'label' => 'Perfiles', 'grupo' => 'Administración'],
];
$groups = [
    ["name" => "Dashboard", 'icon' => 'feather-airplay'],
    ["name" => "Cartera", 'icon' => 'feather-users'],
    ["name" => "Gestión", 'icon' => 'feather-calendar'],
    ["name" => "Reportes", 'icon' => 'feather-list'],
    ["name" => "Administración", 'icon' => 'feather-settings']
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
                <li class="nxl-item nxl-caption"><label>CRM Radiopolis</label></li>
                <?php foreach ($groups as $group): ?>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="<?= esc($group['icon'], 'attr') ?>"></i></span>
                            <span class="nxl-mtext"><?= esc($group['name']) ?></span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <?php foreach ($items as $item): ?>
                                <?php if (in_array($item['permission'], $grants, true) && $group['name'] === $item['grupo']): ?>
                                    <li class="nxl-item">
                                        <a class="nxl-link<?= $isActive($item['match']) ? ' active' : '' ?>" href="<?= site_url($item['path']) ?>">
                                            <span class="nxl-micon">
                                                <i class="<?= esc($item['icon'], 'attr') ?>"></i>
                                            </span>
                                            <span class="nxl-mtext"><?= esc($item['label']) ?></span>
                                        </a>
                                    </li>
                                <?php endif ?>
                            <?php endforeach ?>
                        </ul>
                    </li>

                <?php endforeach ?>

            </ul>
        </div>
    </div>
</nav>
