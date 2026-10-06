<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?= lang_html('Reception.title.list') ?></h1>
    <a class="btn btn-primary" href="<?= site_url('receptions/new') ?>">
        <?= lang_html('Reception.table.add') ?>
    </a>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

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
            <tbody>
            <?php foreach ($receptions as $reception): ?>
                <?php
                $canEdit = (int) $reception['created_by'] === (int) $actor['id']
                    || $permissions->roleHas($actor['role'], \Config\Permissions::RECEIPT_UPDATE_ANY);
                ?>
                <tr>
                    <td>
                        <a href="<?= site_url('receptions/' . $reception['id'] . '/edit') ?>"><?= esc($reception['reference_no']) ?></a>
                    </td>
                    <td><?= esc($reception['supplier_name'] ?? '-') ?></td>
                    <td><?= esc($reception['received_at']) ?></td>
                    <td>
                        <?= esc($reception['created_by_name'] ?? '-') ?>
                        <div class="text-muted small"><?= esc($reception['created_at']) ?></div>
                    </td>
                    <td>
                        <?php if ($reception['updated_by'] === null): ?>
                            <span class="text-muted"><?= lang_html('Reception.table.never_updated') ?></span>
                        <?php else: ?>
                            <?= esc($reception['updated_by_name'] ?? '-') ?>
                            <div class="text-muted small"><?= esc($reception['updated_at']) ?></div>
                        <?php endif ?>
                    </td>
                    <td class="text-end">
                        <?php if ($canEdit): ?>
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= site_url('receptions/' . $reception['id'] . '/edit') ?>">
                                <?= lang_html('Reception.table.edit') ?>
                            </a>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= lang_html('Reception.table.not_allowed') ?></span>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if ($receptions === []): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <?= lang_html('Reception.table.empty') ?>
                    </td>
                </tr>
            <?php endif ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
