<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= esc($heading ?? $title ?? 'CRM') ?></h5></div>
        <?php if (! empty($breadcrumbs ?? [])): ?>
            <ul class="breadcrumb ms-3 mb-0">
                <?php foreach ($breadcrumbs as $label => $url): ?>
                    <li class="breadcrumb-item"><?= $url ? '<a href="' . esc($url, 'attr') . '">' . esc($label) . '</a>' : esc($label) ?></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
    <?= $this->renderSection('pageActions') ?>
</div>
