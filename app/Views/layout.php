<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? lang('App.brand')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">
<?php
$isAuthenticated = session()->get('user_id') !== null;
$currentPath     = uri_string();
$isReceptions    = str_starts_with($currentPath, 'receptions');
$isStocks        = str_starts_with($currentPath, 'stocks');

// Menu sidebar mengikuti contoh resmi Bootstrap "Sidebars": brand di atas,
// daftar nav-pills di tengah, blok pengguna + keluar di bawah. Tambah menu
// baru cukup dengan menambah entri di sini; urutan array = urutan tampil.
$navItems = [
    [
        'href'   => site_url('receptions'),
        'label'  => lang_html('App.nav.receptions'),
        'icon'   => 'sidebar-icon-receptions',
        'active' => $isReceptions,
    ],
    [
        'href'   => site_url('stocks'),
        'label'  => lang_html('App.nav.stocks'),
        'icon'   => 'sidebar-icon-stocks',
        'active' => $isStocks,
    ],
];
?>
<?php if ($isAuthenticated): ?>
    <?php /* Sprite SVG inline (bukan CDN): ikon menu sidebar, mengikuti contoh Bootstrap. */ ?>
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none" aria-hidden="true">
        <symbol id="sidebar-icon-receptions" viewBox="0 0 16 16">
            <path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-11zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7zM0 13a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 16 13V6a1.5 1.5 0 0 0-1.5-1.5h-13A1.5 1.5 0 0 0 0 6v7zm1.5.5A.5.5 0 0 1 1 13V6a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-13z"/>
        </symbol>
        <symbol id="sidebar-icon-stocks" viewBox="0 0 16 16">
            <path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2zm15 2h-4v3h4V4zm0 4h-4v3h4V8zm0 4h-4v3h3a1 1 0 0 0 1-1v-2zm-5 3v-3H6v3h4zm-5 0v-3H1v2a1 1 0 0 0 1 1h3zm-4-4h4V8H1v3zm0-4h4V4H1v3zm5-3v3h4V4H6zm4 4H6v3h4V8z"/>
        </symbol>
    </svg>
    <nav class="navbar bg-white border-bottom d-lg-none">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#appSidebar" aria-controls="appSidebar"
                    aria-label="<?= esc(lang('App.nav.sidebar')) ?>">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand ms-2" href="<?= site_url('receptions') ?>">
                <?= lang_html('App.brand') ?>
            </a>
        </div>
    </nav>
    <div class="app-shell d-flex flex-grow-1">
        <aside id="appSidebar"
               class="app-sidebar offcanvas-lg offcanvas-start d-flex flex-column flex-shrink-0"
               tabindex="-1" aria-label="<?= esc(lang('App.nav.sidebar')) ?>">
            <div class="offcanvas-body d-flex flex-column">
                <div class="app-sidebar-inner">
                    <div class="d-flex align-items-center mb-3">
                        <a class="app-sidebar-brand d-flex align-items-center me-auto link-body-emphasis text-decoration-none"
                           href="<?= site_url('receptions') ?>">
                            <span class="fs-5 fw-semibold"><?= lang_html('App.brand') ?></span>
                        </a>
                        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas"
                                data-bs-target="#appSidebar" aria-label="<?= esc(lang('App.nav.close')) ?>"></button>
                    </div>
                    <hr class="mt-0">
                    <ul class="nav nav-pills flex-column">
                        <?php foreach ($navItems as $item): ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $item['active'] ? ' active' : '' ?>"
                                   <?= $item['active'] ? 'aria-current="page"' : '' ?>
                                   href="<?= esc($item['href']) ?>"><svg class="bi me-2" width="16" height="16"
                                        aria-hidden="true"><use href="#<?= esc($item['icon']) ?>"></use></svg><?= $item['label'] ?></a>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <hr>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="app-avatar rounded-circle bg-primary text-white" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr((string) session()->get('user_name'), 0, 1))) ?></span>
                        <div class="lh-sm text-truncate">
                            <div class="fw-semibold text-truncate"><?= esc(session()->get('user_name')) ?></div>
                            <div class="small text-body-secondary text-truncate"><?= esc(session()->get('role')) ?></div>
                        </div>
                    </div>
                    <form method="post" action="<?= site_url('logout') ?>" class="d-grid">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <?= lang_html('App.nav.logout') ?>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        <main class="container py-4 flex-grow-1">
            <?= $this->renderSection('content') ?>
        </main>
    </div>
<?php else: ?>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= site_url('login') ?>">
                <?= lang_html('App.brand') ?>
            </a>
        </div>
    </nav>
    <main class="container py-4 flex-grow-1">
        <?= $this->renderSection('content') ?>
    </main>
<?php endif ?>
<footer class="border-top bg-white py-3">
    <div class="container text-muted small">
        <?= lang_html('App.brand') ?>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<?php /* jQuery tanpa SRI: hash belum bisa diverifikasi dari sandbox; tambah integrity setelah dicek dari mesin berinternet. */ ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
        crossorigin="anonymous"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script src="<?= base_url('assets/js/lib/csrf.js') ?>"></script>
<script src="<?= base_url('assets/js/lib/api.js') ?>"></script>
<script src="<?= base_url('assets/js/lib/ui.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
