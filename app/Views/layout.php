<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? lang('App.brand')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">
<?php
$currentPath = uri_string();
$isReceptions = str_starts_with($currentPath, 'receptions');
$isStocks = str_starts_with($currentPath, 'stocks');
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?= site_url(session()->get('user_id') !== null ? 'receptions' : 'login') ?>">
            <?= lang_html('App.brand') ?>
        </a>
        <?php if (session()->get('user_id') !== null): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link<?= $isReceptions ? ' active' : '' ?>"
                           <?= $isReceptions ? 'aria-current="page"' : '' ?>
                           href="<?= site_url('receptions') ?>"><?= lang_html('App.nav.receptions') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $isStocks ? ' active' : '' ?>"
                           <?= $isStocks ? 'aria-current="page"' : '' ?>
                           href="<?= site_url('stocks') ?>"><?= lang_html('App.nav.stocks') ?></a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <span class="navbar-text small">
                        <?= esc(session()->get('user_name')) ?> (<?= esc(session()->get('role')) ?>)
                    </span>
                    <a class="btn btn-outline-light btn-sm" href="<?= site_url('logout') ?>">
                        <?= lang_html('App.nav.logout') ?>
                    </a>
                </div>
            </div>
        <?php endif ?>
    </div>
</nav>
<main class="container py-4 flex-grow-1">
<?= $this->renderSection('content') ?>
</main>
<footer class="border-top bg-white py-3">
    <div class="container text-muted small">
        <?= lang_html('App.brand') ?>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
