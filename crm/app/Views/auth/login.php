<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4"><img src="<?= base_url('assets/images/logo-full.png') ?>" alt="CRM" class="img-fluid" style="max-height: 48px"></div>
        <h1 class="h4 text-center mb-4">Iniciar sesión</h1>
        <form action="<?= site_url('login') ?>" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3"><label for="identity" class="form-label">Usuario o correo</label><input type="text" class="form-control" id="identity" name="identity" value="<?= old('identity') ?>" required autocomplete="username"></div>
            <div class="mb-4"><label for="password" class="form-label">Contraseña</label><input type="password" class="form-control" id="password" name="password" required autocomplete="current-password"></div>
            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
