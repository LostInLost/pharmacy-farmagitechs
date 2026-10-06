<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1>Masuk</h1>

<?php if (! empty($error)): ?>
    <p role="alert"><?= esc($error) ?></p>
<?php endif ?>

<form method="post" action="<?= site_url('login') ?>">
    <?= csrf_field() ?>
    <p>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= esc(old('username') ?? '') ?>" required>
    </p>
    <p>
        <label for="password">Kata sandi</label>
        <input type="password" id="password" name="password" required>
    </p>
    <button type="submit">Masuk</button>
</form>
<?= $this->endSection() ?>
