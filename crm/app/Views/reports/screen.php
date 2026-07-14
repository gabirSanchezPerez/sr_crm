<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
$isWallet = ($report ?? '') === 'wallet';
$selected = static fn (string $name, int|string $value): string => (string) ($filters[$name] ?? '-1') === (string) $value ? ' selected' : '';
$query = http_build_query(['u' => $filters['u'] ?? '-1', 'e' => $filters['e'] ?? '-1', 't' => $filters['t'] ?? '-1', 'o' => 1]);
?>
<div class="card stretch stretch-full mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="fs-5 mb-1"><?= esc($heading) ?></h2>
            <div class="text-muted fs-12">Total: <?= number_format((int) ($totals['all'] ?? 0)) ?> · Anunciantes: <?= number_format((int) ($totals['a'] ?? 0)) ?> · Potenciales: <?= number_format((int) ($totals['cp'] ?? 0)) ?></div>
        </div>
        <a class="btn btn-primary" target="_blank" href="<?= esc($exportUrl . '?' . $query, 'attr') ?>"><i class="feather-download me-2"></i>Exportar</a>
    </div>
    <div class="card-body">
        <form method="get" action="<?= current_url() ?>" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="u">Unidad comercial</label>
                <select class="form-select" id="u" name="u">
                    <?php foreach (($options['units'] ?? []) as $value => $label): ?>
                        <option value="<?= esc((string) $value, 'attr') ?>"<?= $selected('u', $value) ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="e">Ejecutivo</label>
                <select class="form-select" id="e" name="e">
                    <?php foreach (($options['executives'] ?? []) as $value => $label): ?>
                        <option value="<?= esc((string) $value, 'attr') ?>"<?= $selected('e', $value) ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="t">Tipo</label>
                <select class="form-select" id="t" name="t">
                    <?php foreach (($options['types'] ?? []) as $value => $label): ?>
                        <option value="<?= esc((string) $value, 'attr') ?>"<?= $selected('t', $value) ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-outline-primary" type="submit" title="Buscar"><i class="feather-search"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card stretch stretch-full">
    <div class="card-body p-0">
        <?php if (($rows ?? []) === []): ?>
            <?= $this->include('components/empty_state') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Unidad comercial</th>
                        <th>Ejecutivo</th>
                        <th>Marca</th>
                        <th>Razon social</th>
                        <th>Sector</th>
                        <th>Tipo</th>
                        <th>Creacion</th>
                        <?php if (! $isWallet): ?><th>Ultimo seguimiento</th><?php endif ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc($row['unidad'] ?? '-') ?></td>
                            <td><?= esc($row['ejecutivo'] ?? '-') ?></td>
                            <td><?= esc($row['marca'] ?? '-') ?></td>
                            <td><?= esc($row['razon_social'] ?? '-') ?></td>
                            <td><?= esc($row['sector'] ?? '-') ?></td>
                            <td><span class="badge <?= ($row['tipo'] ?? '') === 'Anunciante' ? 'bg-success' : 'bg-primary' ?>"><?= esc($row['tipo'] ?? '-') ?></span></td>
                            <td><?= esc($row['f_creacion'] ?? '-') ?></td>
                            <?php if (! $isWallet): ?><td><?= esc($row['f_seguimiento'] ?? '-') ?></td><?php endif ?>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>