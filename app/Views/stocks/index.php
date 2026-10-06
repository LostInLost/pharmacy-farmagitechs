<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3"><?= esc(lang('Stock.title')) ?></h1>

<form class="card shadow-sm mb-4" method="get" action="<?= site_url('stocks') ?>">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="on_date" class="form-label"><?= esc(lang('Stock.filter.on_date')) ?></label>
                <input type="date" class="form-control" id="on_date" name="on_date" value="<?= esc($onDate) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><?= esc(lang('Stock.filter.show')) ?></button>
            </div>
        </div>
        <p class="form-text mb-0 mt-2"><?= esc(lang('Stock.filter.hint')) ?></p>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th><?= esc(lang('Stock.table.code')) ?></th>
                <th><?= esc(lang('Stock.table.medicine')) ?></th>
                <th><?= esc(lang('Stock.table.unit')) ?></th>
                <th class="text-end"><?= esc(lang('Stock.table.physical')) ?></th>
                <th class="text-end"><?= esc(lang('Stock.table.available')) ?></th>
                <th class="text-end"><?= esc(lang('Stock.table.expired')) ?></th>
                <th><?= esc(lang('Stock.table.batches')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($medicines as $medicine): ?>
                <tr>
                    <td><?= esc($medicine['code']) ?></td>
                    <td><?= esc($medicine['name']) ?></td>
                    <td><?= esc($medicine['unit']) ?></td>
                    <td class="text-end"><?= (int) $medicine['physical_quantity'] ?></td>
                    <td class="text-end"><?= (int) $medicine['available_quantity'] ?></td>
                    <td class="text-end"><?= (int) $medicine['expired_quantity'] ?></td>
                    <td>
                        <?php if ($medicine['available_batches'] === [] && $medicine['expired_batches'] === []): ?>
                            <span class="text-muted"><?= esc(lang('Stock.table.no_batch')) ?></span>
                        <?php else: ?>
                            <details>
                                <summary><?= esc(lang('Stock.table.batch_count', [count($medicine['available_batches']) + count($medicine['expired_batches'])])) ?></summary>
                                <table class="table table-sm table-bordered mb-0 mt-2">
                                    <thead class="table-light">
                                    <tr>
                                        <th><?= esc(lang('Stock.table.batch_no')) ?></th>
                                        <th><?= esc(lang('Stock.table.expires_on')) ?></th>
                                        <th class="text-end"><?= esc(lang('Stock.table.quantity')) ?></th>
                                        <th><?= esc(lang('Stock.table.status')) ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($medicine['available_batches'] as $batch): ?>
                                        <tr>
                                            <td><?= esc($batch['batch_no']) ?></td>
                                            <td><?= esc($batch['expires_on'] ?? '-') ?></td>
                                            <td class="text-end"><?= (int) $batch['quantity'] ?></td>
                                            <td><span class="badge text-bg-success"><?= esc(lang('Stock.table.status_available')) ?></span></td>
                                        </tr>
                                    <?php endforeach ?>
                                    <?php foreach ($medicine['expired_batches'] as $batch): ?>
                                        <tr>
                                            <td><?= esc($batch['batch_no']) ?></td>
                                            <td><?= esc($batch['expires_on'] ?? '-') ?></td>
                                            <td class="text-end"><?= (int) $batch['quantity'] ?></td>
                                            <td><span class="badge text-bg-danger"><?= esc(lang('Stock.table.status_expired')) ?></span></td>
                                        </tr>
                                    <?php endforeach ?>
                                    </tbody>
                                </table>
                            </details>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
