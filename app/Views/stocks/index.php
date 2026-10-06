<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><?= lang_html('Stock.title') ?></h1>

<form id="stocks-filter" class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="on_date" class="form-label"><?= lang_html('Stock.filter.on_date') ?></label>
                <input type="date" class="form-control" id="on_date" name="on_date">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><?= lang_html('Stock.filter.show') ?></button>
            </div>
        </div>
        <p class="form-text mb-0 mt-2"><?= lang_html('Stock.filter.hint') ?></p>
    </div>
</form>

<div id="feedback"></div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th><?= lang_html('Stock.table.code') ?></th>
                <th><?= lang_html('Stock.table.medicine') ?></th>
                <th><?= lang_html('Stock.table.unit') ?></th>
                <th class="text-end"><?= lang_html('Stock.table.physical') ?></th>
                <th class="text-end"><?= lang_html('Stock.table.available') ?></th>
                <th class="text-end"><?= lang_html('Stock.table.expired') ?></th>
                <th><?= lang_html('Stock.table.batches') ?></th>
            </tr>
            </thead>
            <tbody id="stocks-tbody">
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    window.FARMASI_BOOT = {
        endpoints: {
            stocks: '<?= site_url('api/stocks') ?>'
        },
        loginUrl: '<?= site_url('login') ?>',
        i18n: {
            loading: <?= json_encode(lang('App.js.loading'), JSON_UNESCAPED_UNICODE) ?>,
            sessionExpired: <?= json_encode(lang('App.js.session_expired'), JSON_UNESCAPED_UNICODE) ?>,
            contactFailed: <?= json_encode(lang('Stock.js.contact_failed'), JSON_UNESCAPED_UNICODE) ?>,
            loadFailed: <?= json_encode(lang('Stock.js.load_failed'), JSON_UNESCAPED_UNICODE) ?>,
            noBatch: <?= json_encode(lang('Stock.js.no_batch'), JSON_UNESCAPED_UNICODE) ?>,
            batchCount: <?= json_encode(lang('Stock.js.batch_count'), JSON_UNESCAPED_UNICODE) ?>,
            batchNo: <?= json_encode(lang('Stock.table.batch_no'), JSON_UNESCAPED_UNICODE) ?>,
            expiresOn: <?= json_encode(lang('Stock.table.expires_on'), JSON_UNESCAPED_UNICODE) ?>,
            quantity: <?= json_encode(lang('Stock.table.quantity'), JSON_UNESCAPED_UNICODE) ?>,
            status: <?= json_encode(lang('Stock.table.status'), JSON_UNESCAPED_UNICODE) ?>,
            statusAvailable: <?= json_encode(lang('Stock.js.status_available'), JSON_UNESCAPED_UNICODE) ?>,
            statusExpired: <?= json_encode(lang('Stock.js.status_expired'), JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<script src="<?= base_url('assets/js/pages/stocks.js') ?>"></script>
<?= $this->endSection() ?>
