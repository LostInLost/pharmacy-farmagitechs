<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1>Masuk</h1>
<form method="post" action="/login">
    <label>Username <input type="text" name="username" required></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit">Masuk</button>
</form>
<?= $this->endSection() ?>
