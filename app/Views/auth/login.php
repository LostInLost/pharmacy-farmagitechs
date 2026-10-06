<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="card-title h4 mb-3"><?= lang_html('Auth.login.title') ?></h1>

                <div id="feedback"></div>

                <form id="login-form">
                    <div class="mb-3">
                        <label for="username" class="form-label"><?= lang_html('Auth.login.username') ?></label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><?= lang_html('Auth.login.password') ?></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= lang_html('Auth.login.submit') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    window.FARMASI_BOOT = {
        endpoints: {
            login: '<?= site_url('api/login') ?>'
        },
        loginUrl: '<?= site_url('login') ?>',
        redirectUrl: '<?= site_url('receptions') ?>',
        i18n: {
            sessionExpired: <?= json_encode(lang('App.js.session_expired'), JSON_UNESCAPED_UNICODE) ?>,
            loginFailed: <?= json_encode(lang('Auth.js.login_failed'), JSON_UNESCAPED_UNICODE) ?>,
            contactFailed: <?= json_encode(lang('Auth.js.contact_failed'), JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<script src="<?= base_url('assets/js/pages/login.js') ?>"></script>
<?= $this->endSection() ?>
