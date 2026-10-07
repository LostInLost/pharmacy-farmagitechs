<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= lang_html('Reception.title.list') ?></h1>
    <a class="btn btn-primary" href="<?= site_url('receptions/new') ?>">
        <?= lang_html('Reception.table.add') ?>
    </a>
</div>

<div id="feedback"></div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th><?= lang_html('Reception.table.reference') ?></th>
                <th><?= lang_html('Reception.table.supplier') ?></th>
                <th><?= lang_html('Reception.table.received_at') ?></th>
                <th><?= lang_html('Reception.table.created_by') ?></th>
                <th><?= lang_html('Reception.table.updated_by') ?></th>
                <th class="text-end"><?= lang_html('Reception.table.actions') ?></th>
            </tr>
            </thead>
            <tbody id="receptions-tbody">
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    window.FARMASI_BOOT = {
        endpoints: {
            receipts: '<?= site_url('api/receipts') ?>',
            editBase: '<?= site_url('receptions') ?>/'
        },
        loginUrl: '<?= site_url('login') ?>',
        i18n: {
            loading: <?= json_encode(lang('App.js.loading'), JSON_UNESCAPED_UNICODE) ?>,
            sessionExpired: <?= json_encode(lang('App.js.session_expired'), JSON_UNESCAPED_UNICODE) ?>,
            contactFailed: <?= json_encode(lang('Reception.js.contact_failed'), JSON_UNESCAPED_UNICODE) ?>,
            loadFailed: <?= json_encode(lang('Reception.js.load_failed'), JSON_UNESCAPED_UNICODE) ?>,
            edit: <?= json_encode(lang('Reception.table.edit'), JSON_UNESCAPED_UNICODE) ?>,
            empty: <?= json_encode(lang('Reception.table.empty'), JSON_UNESCAPED_UNICODE) ?>,
            neverUpdated: <?= json_encode(lang('Reception.js.never_updated'), JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<script src="<?= base_url('assets/js/pages/receptions.js') ?>"></script>
<?= $this->endSection() ?>
