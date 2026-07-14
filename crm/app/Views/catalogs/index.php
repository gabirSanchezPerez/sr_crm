<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card stretch stretch-full">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <form method="get" action="<?= site_url($catalog->route) ?>" class="d-flex gap-2">
            <input type="search" class="form-control" name="q" value="<?= esc(service('request')->getGet('q') ?? '') ?>" placeholder="Buscar">
            <button class="btn btn-outline-primary" type="submit"><i class="feather-search"></i></button>
        </form>
        <?php foreach ($pageActions as $action): ?>
            <a class="btn btn-<?= esc($action['style']) ?>" href="<?= esc($action['url'], 'attr') ?>">
                <i class="<?= esc($action['icon']) ?> me-2"></i><?= esc($action['label']) ?>
            </a>
        <?php endforeach ?>
    </div>
    <div class="card-body p-0">
        <?php if ($rows === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <?php foreach ($listFields as $field): ?>
                            <th><?= esc($field->label) ?></th>
                        <?php endforeach ?>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($listFields as $field): ?>
                                <td><?= esc($row[$field->name] ?? '') ?></td>
                            <?php endforeach ?>
                            <td class="text-end flex">
                                <?php foreach ($rowActions[(int) ($row['id'] ?? 0)] ?? [] as $action): ?>
                                    <?php if ($action['method'] === 'GET'): ?>
                                        <a class="btn btn-sm btn-<?= esc($action['style']) ?>" href="<?= esc($action['url'], 'attr') ?>" title="<?= esc($action['label'], 'attr') ?>">
                                            <i class="<?= esc($action['icon']) ?>"></i>
                                        </a>
                                    <?php else: ?>
                                        <form method="post" action="<?= esc($action['url'], 'attr') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-<?= esc($action['style']) ?>" type="submit" title="<?= esc($action['label'], 'attr') ?>">
                                                <i class="<?= esc($action['icon']) ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif ?>
                                <?php endforeach ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
