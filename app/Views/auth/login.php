<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="card-title h4 mb-3"><?= esc(lang('Auth.login.title')) ?></h1>

                <?php if (! empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
                <?php endif ?>

                <form method="post" action="<?= site_url('login') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="username" class="form-label"><?= esc(lang('Auth.login.username')) ?></label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= esc(old('username') ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><?= esc(lang('Auth.login.password')) ?></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= esc(lang('Auth.login.submit')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
