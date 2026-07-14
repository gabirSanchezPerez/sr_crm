<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12 col-xl-9">
        <div class="card stretch stretch-full">
            <form method="post" action="<?= site_url('auth/transfering') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="fromUser" value="<?= esc($user['id']) ?>">
                <div class="card-body">
                    <div class="mb-4">
                        <div class="text-muted small">Origen</div>
                        <h2 class="h5 mb-0"><?= esc($user['nombre']) ?></h2>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="newEjecutivo">Ejecutivo destino</label>
                        <select class="form-select" id="newEjecutivo" name="newEjecutivo" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($users as $candidate): ?>
                                <?php if ((int) $candidate['id'] !== (int) $user['id']): ?>
                                    <option value="<?= esc($candidate['id']) ?>"><?= esc($candidate['nombre']) ?></option>
                                <?php endif ?>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h3 class="h6">Clientes</h3>
                            <?php foreach ($clients as $client): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accounts[A][]" value="<?= esc($client['id']) ?>" id="client-<?= esc($client['id']) ?>">
                                    <label class="form-check-label" for="client-<?= esc($client['id']) ?>"><?= esc($client['nombre']) ?></label>
                                </div>
                            <?php endforeach ?>
                            <?php if ($clients === []): ?><p class="text-muted mb-0">Sin clientes asignados.</p><?php endif ?>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6">Prospectos</h3>
                            <?php foreach ($prospects as $prospect): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accounts[CP][]" value="<?= esc($prospect['id']) ?>" id="prospect-<?= esc($prospect['id']) ?>">
                                    <label class="form-check-label" for="prospect-<?= esc($prospect['id']) ?>"><?= esc($prospect['nombre']) ?></label>
                                </div>
                            <?php endforeach ?>
                            <?php if ($prospects === []): ?><p class="text-muted mb-0">Sin prospectos asignados.</p><?php endif ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= site_url('usuario') ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-repeat me-2"></i>Transferir</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
