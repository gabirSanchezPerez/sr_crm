<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<style>
    .goal-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(125px, 1fr));
        gap: 1rem
    }

    .goal-person {
        border-left: 4px solid #3454d1
    }

    .goal-month.is-locked {
        opacity: .65
    }

    .goal-total {
        font-variant-numeric: tabular-nums
    }

    .goal-actions {
        position: sticky;
        bottom: 1rem;
        z-index: 5
    }

    @media(max-width:991px) {
        .goal-grid {
            grid-template-columns: repeat(3, minmax(120px, 1fr))
        }
    }

    @media(max-width:575px) {
        .goal-grid {
            grid-template-columns: repeat(2, minmax(120px, 1fr))
        }

        .goal-person .card-body {
            padding: .85rem
        }
    }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']; ?>
<form class="d-flex gap-2 mb-3" method="get"><input class="form-control" style="max-width:140px" type="number" min="2000" max="2100" name="anio" value="<?= esc($goals['year']) ?>"><button class="btn btn-outline-primary">Ver año</button></form>
<?php if (!empty($goals['distribution'])): ?><div class="card mb-3">
        <div class="card-header"><strong>Distribución de la meta del Gerente</strong></div>
        <div class="card-body">
            <div class="goal-grid"><?php foreach ($months as $i => $label): $d = $goals['distribution'][$i + 1]; ?><div>
                        <div class="small fw-semibold"><?= esc($label) ?></div>
                        <div class="small">Meta: $<?= number_format($d['limit'], 2) ?></div>
                        <div class="small">Asignado: $<?= number_format($d['assigned'], 2) ?></div>
                        <div class="small text-success">Saldo: $<?= number_format($d['remaining'], 2) ?></div>
                    </div><?php endforeach ?></div>
        </div>
    </div><?php endif ?>
<?php if (empty($goals['people'])): ?><div class="alert alert-info">No hay usuarios disponibles para este nivel.</div><?php endif ?>
<form method="post" action="<?= site_url('meta/save') ?>"><?= csrf_field() ?><input type="hidden" name="anio" value="<?= esc($goals['year']) ?>">
    <?php foreach ($goals['people'] as $person): ?><section class="card goal-person mb-3">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between">
                <div><strong><?= esc($person['nombre']) ?></strong> <span class="text-muted">(<?= (int)$person['perfil_id'] === 2 ? 'Gerente' : 'Ejecutivo' ?>)</span>
                    <div class="small text-muted"><?= esc($person['unidad']) ?></div>
                </div>
                <div class="goal-total fw-bold">Total: $<?= number_format($person['total'], 2) ?></div>
            </div>
            <div class="card-body">
                <div class="goal-grid">
                    <?php foreach ($months as $i => $label): $m = $i + 1;
                        $editable = $canEdit && ($goals['editable'][$m] ?? false); ?><div class="goal-month<?= $editable ? '' : ' is-locked' ?>"><label class="form-label small"><?= esc($label) ?><?= $editable ? '' : ' 🔒' ?></label>
                            <div class="input-group"><span class="input-group-text">$</span><input class="form-control" type="number" min="0" step="0.01" name="metas[<?= esc($person['id']) ?>][<?= esc($person['ucomercial_id']) ?>][<?= $m ?>]" value="<?= number_format($person['months'][$m], 2, '.', '') ?>" <?= $editable ? '' : 'disabled' ?>></div>
                        </div><?php endforeach ?>
                </div>
            </div>
        </section><?php endforeach ?>
    <?php if ($canEdit && !empty($goals['people'])): ?><div class="goal-actions d-flex justify-content-end"><button class="btn btn-primary btn-lg shadow" type="submit"><i class="feather-save me-2"></i>Guardar metas</button></div><?php endif ?></form>
<?= $this->endSection() ?>
