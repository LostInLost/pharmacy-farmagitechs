<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><?= esc($title) ?></h1>

<div id="feedback"></div>

<form id="reception-form" class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="reference_no" class="form-label"><?= lang_html('Reception.form.reference_no') ?></label>
                <input type="text" class="form-control" id="reference_no" name="reference_no" required>
            </div>
            <div class="col-md-4">
                <label for="supplier_id" class="form-label"><?= lang_html('Reception.form.supplier') ?></label>
                <select class="form-select" id="supplier_id" name="supplier_id" required>
                    <option value=""><?= lang_html('Reception.form.choose') ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="received_at" class="form-label"><?= lang_html('Reception.form.received_at') ?></label>
                <input type="datetime-local" class="form-control" id="received_at" name="received_at" required>
            </div>
        </div>

        <h2 class="h5 mt-4 mb-2"><?= lang_html('Reception.form.items') ?></h2>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                <tr>
                    <th><?= lang_html('Reception.form.medicine') ?></th>
                    <th><?= lang_html('Reception.form.batch_no') ?></th>
                    <th><?= lang_html('Reception.form.expires_on') ?></th>
                    <th class="text-end"><?= lang_html('Reception.form.quantity') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="item-rows"></tbody>
            </table>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="add-row">
            <?= lang_html('Reception.form.add_row') ?>
        </button>

        <div class="d-flex align-items-center gap-3 mt-4">
            <button type="submit" class="btn btn-primary"><?= lang_html('Reception.form.save') ?></button>
            <a class="btn btn-link" href="<?= site_url('receptions') ?>"><?= lang_html('Reception.form.back') ?></a>
        </div>
    </div>
</form>

<section id="log-section" class="card shadow-sm" style="display: none;">
    <div class="card-body">
        <h2 class="h5 mb-3"><?= lang_html('Reception.log.title') ?></h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th><?= lang_html('Reception.log.time') ?></th>
                    <th><?= lang_html('Reception.log.action') ?></th>
                    <th><?= lang_html('Reception.log.actor') ?></th>
                    <th><?= lang_html('Reception.log.changes') ?></th>
                </tr>
                </thead>
                <tbody id="log-tbody">
                </tbody>
            </table>
        </div>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    window.FARMASI_BOOT = {
        receptionId: <?= $receptionId === null ? 'null' : (int) $receptionId ?>,
        endpoints: {
            receipts: '<?= site_url('api/receipts') ?>',
            suppliers: '<?= site_url('api/references/suppliers') ?>',
            medicines: '<?= site_url('api/references/medicines') ?>'
        },
        loginUrl: '<?= site_url('login') ?>',
        redirectUrl: '<?= site_url('receptions') ?>',
        i18n: {
            sessionExpired: <?= json_encode(lang('App.js.session_expired'), JSON_UNESCAPED_UNICODE) ?>,
            contactFailed: <?= json_encode(lang('Reception.js.contact_failed'), JSON_UNESCAPED_UNICODE) ?>,
            saveFailed: <?= json_encode(lang('Reception.js.save_failed'), JSON_UNESCAPED_UNICODE) ?>,
            savedRedirect: <?= json_encode(lang('Reception.js.saved_redirect'), JSON_UNESCAPED_UNICODE) ?>,
            remove: <?= json_encode(lang('Reception.js.remove'), JSON_UNESCAPED_UNICODE) ?>,
            loadFailed: <?= json_encode(lang('Reception.js.load_failed'), JSON_UNESCAPED_UNICODE) ?>,
            forbidden: <?= json_encode(lang('Reception.js.forbidden'), JSON_UNESCAPED_UNICODE) ?>,
            back: <?= json_encode(lang('Reception.form.back'), JSON_UNESCAPED_UNICODE) ?>,
            choose: <?= json_encode(lang('Reception.form.choose'), JSON_UNESCAPED_UNICODE) ?>,
            logCreated: <?= json_encode(lang('Reception.log.created'), JSON_UNESCAPED_UNICODE) ?>,
            logChanged: <?= json_encode(lang('Reception.log.changed'), JSON_UNESCAPED_UNICODE) ?>,
            logUnchanged: <?= json_encode(lang('Reception.log.unchanged'), JSON_UNESCAPED_UNICODE) ?>,
            logActionCreate: <?= json_encode(lang('Reception.log.action_create'), JSON_UNESCAPED_UNICODE) ?>,
            logActionUpdate: <?= json_encode(lang('Reception.log.action_update'), JSON_UNESCAPED_UNICODE) ?>,
            logActionDelete: <?= json_encode(lang('Reception.log.action_delete'), JSON_UNESCAPED_UNICODE) ?>,
            logBefore: <?= json_encode(lang('Reception.log.before'), JSON_UNESCAPED_UNICODE) ?>,
            logAfter: <?= json_encode(lang('Reception.log.after'), JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<script src="<?= base_url('assets/js/pages/reception-form.js') ?>"></script>
<?= $this->endSection() ?>
