<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h1 class="card-title h4 mb-3"><?= lang_html('Auth.already.title') ?></h1>

                <p class="text-muted mb-4"><?= lang_html('Auth.already.message') ?></p>

                <div class="d-grid gap-2">
                    <a href="<?= site_url('receptions') ?>" class="btn btn-primary">
                        <?= lang_html('Auth.already.back') ?>
                    </a>
                    <form method="post" action="<?= site_url('logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <?= lang_html('Auth.already.logout') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
