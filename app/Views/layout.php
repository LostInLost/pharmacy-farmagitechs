<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Pharmacy Farmagitechs') ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body>
<header>
    <strong>Pharmacy Farmagitechs</strong>
    <?php if (session()->get('user_id') !== null): ?>
        <nav>
            <a href="<?= site_url('receptions') ?>">Penerimaan</a>
            <a href="<?= site_url('stocks') ?>">Stok</a>
            <span class="who"><?= esc(session()->get('user_name')) ?> (<?= esc(session()->get('role')) ?>)</span>
            <a href="<?= site_url('logout') ?>">Keluar</a>
        </nav>
    <?php endif ?>
</header>
<main>
<?= $this->renderSection('content') ?>
</main>
</body>
</html>
