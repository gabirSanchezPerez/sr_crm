<?php foreach (['success' => 'success', 'message' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $class): ?>
    <?php if ($message = session()->getFlashdata($key)): ?>
        <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
            <?= esc(is_array($message) ? implode(' ', $message) : $message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif ?>
<?php endforeach ?>
<?php if (isset($validation) && $validation->getErrors()): ?>
    <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($validation->getErrors() as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
<?php endif ?>
