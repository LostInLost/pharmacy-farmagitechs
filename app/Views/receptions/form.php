<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><?= esc($title) ?></h1>

<div id="feedback"></div>

<form id="reception-form" class="card shadow-sm mb-4">
    <div class="card-body">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="reference_no" class="form-label"><?= esc(lang('Reception.form.reference_no')) ?></label>
                <input type="text" class="form-control" id="reference_no" name="reference_no" required
                       value="<?= esc($reception['reference_no'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="supplier_id" class="form-label"><?= esc(lang('Reception.form.supplier')) ?></label>
                <select class="form-select" id="supplier_id" name="supplier_id" required>
                    <option value=""><?= esc(lang('Reception.form.choose')) ?></option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier['id'] ?>"
                            <?= (int) ($reception['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                            <?= esc($supplier['name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="received_at" class="form-label"><?= esc(lang('Reception.form.received_at')) ?></label>
                <input type="datetime-local" class="form-control" id="received_at" name="received_at" required
                       value="<?= esc(isset($reception['received_at']) ? str_replace(' ', 'T', substr($reception['received_at'], 0, 16)) : '') ?>">
            </div>
        </div>

        <h2 class="h5 mt-4 mb-2"><?= esc(lang('Reception.form.items')) ?></h2>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                <tr>
                    <th><?= esc(lang('Reception.form.medicine')) ?></th>
                    <th><?= esc(lang('Reception.form.batch_no')) ?></th>
                    <th><?= esc(lang('Reception.form.expires_on')) ?></th>
                    <th class="text-end"><?= esc(lang('Reception.form.quantity')) ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="item-rows"></tbody>
            </table>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="add-row">
            <?= esc(lang('Reception.form.add_row')) ?>
        </button>

        <div class="d-flex align-items-center gap-3 mt-4">
            <button type="submit" class="btn btn-primary"><?= esc(lang('Reception.form.save')) ?></button>
            <a class="btn btn-link" href="<?= site_url('receptions') ?>"><?= esc(lang('Reception.form.back')) ?></a>
        </div>
    </div>
</form>

<?php if ($reception !== null && $reception['logs'] !== []): ?>
    <section class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3"><?= esc(lang('Reception.log.title')) ?></h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th><?= esc(lang('Reception.log.time')) ?></th>
                        <th><?= esc(lang('Reception.log.action')) ?></th>
                        <th><?= esc(lang('Reception.log.actor')) ?></th>
                        <th><?= esc(lang('Reception.log.changes')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reception['logs'] as $log): ?>
                        <tr>
                            <td><?= esc($log['created_at']) ?></td>
                            <td><?= esc($log['action']) ?></td>
                            <td><?= esc($log['actor_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($log['data_before'] === null && $log['data_after'] === null): ?>
                                    -
                                <?php else: ?>
                                    <?php
                                    $before = $log['data_before'];
                                    $after  = $log['data_after'];
                                    $summary = $log['action'] === 'CREATE'
                                        ? lang('Reception.log.created')
                                        : ($before === $after ? lang('Reception.log.unchanged') : lang('Reception.log.changed'));
                                    ?>
                                    <details>
                                        <summary><?= esc($summary) ?></summary>
                                        <pre class="bg-light p-2 rounded small mb-0"><?= esc(lang('Reception.log.before')) ?>: <?= esc(json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>

<?= esc(lang('Reception.log.after')) ?>: <?= esc(json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                    </details>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    window.RECEPTION_DATA = {
        id: <?= $reception === null ? 'null' : (int) $reception['id'] ?>,
        items: <?= json_encode($reception['items'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
        medicines: <?= json_encode(array_map(static fn ($m) => ['id' => (int) $m['id'], 'name' => $m['name'], 'unit' => $m['unit']], $medicines), JSON_UNESCAPED_UNICODE) ?>,
        endpoints: {
            create: '<?= site_url('api/receipts') ?>',
            update: '<?= site_url('api/receipts') ?>/'
        },
        redirectUrl: '<?= site_url('receptions') ?>',
        i18n: {
            saveFailed: <?= json_encode(lang('Reception.js.save_failed'), JSON_UNESCAPED_UNICODE) ?>,
            contactFailed: <?= json_encode(lang('Reception.js.contact_failed'), JSON_UNESCAPED_UNICODE) ?>,
            savedRedirect: <?= json_encode(lang('Reception.js.saved_redirect'), JSON_UNESCAPED_UNICODE) ?>,
            remove: <?= json_encode(lang('Reception.js.remove'), JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<script src="<?= base_url('assets/reception-form.js') ?>"></script>
<?= $this->endSection() ?>
