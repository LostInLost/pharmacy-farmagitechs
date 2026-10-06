<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1>Penerimaan</h1>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert error"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="row-actions">
    <a href="<?= site_url('receptions/new') ?>"><button type="button">Tambah Penerimaan</button></a>
</div>

<table>
    <thead>
    <tr>
        <th>Reference</th>
        <th>Pemasok</th>
        <th>Diterima</th>
        <th>Pembuat</th>
        <th>Pengubah terakhir</th>
        <th>Aksi</th>
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
                <div class="muted"><?= esc($reception['created_at']) ?></div>
            </td>
            <td>
                <?php if ($reception['updated_by'] === null): ?>
                    <span class="muted">belum pernah diubah</span>
                <?php else: ?>
                    <?= esc($reception['updated_by_name'] ?? '-') ?>
                    <div class="muted"><?= esc($reception['updated_at']) ?></div>
                <?php endif ?>
            </td>
            <td>
                <?php if ($canEdit): ?>
                    <a href="<?= site_url('receptions/' . $reception['id'] . '/edit') ?>">Ubah</a>
                <?php else: ?>
                    <span class="muted">tidak berhak</span>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    <?php if ($receptions === []): ?>
        <tr><td colspan="6" class="muted">Belum ada penerimaan.</td></tr>
    <?php endif ?>
    </tbody>
</table>
<?= $this->endSection() ?>
