<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1><?= esc($title) ?></h1>

<div id="feedback"></div>

<form id="reception-form" class="card">
    <?= csrf_field() ?>
    <div class="grid">
        <div>
            <label for="reference_no">Reference No</label>
            <input type="text" id="reference_no" name="reference_no" required
                   value="<?= esc($reception['reference_no'] ?? '') ?>">
        </div>
        <div>
            <label for="supplier_id">Pemasok</label>
            <select id="supplier_id" name="supplier_id" required>
                <option value="">- pilih -</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= (int) $supplier['id'] ?>"
                        <?= (int) ($reception['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                        <?= esc($supplier['name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label for="received_at">Diterima pada</label>
            <input type="datetime-local" id="received_at" name="received_at" required
                   value="<?= esc(isset($reception['received_at']) ? str_replace(' ', 'T', substr($reception['received_at'], 0, 16)) : '') ?>">
        </div>
    </div>

    <h2>Item</h2>
    <table>
        <thead>
        <tr>
            <th>Obat</th>
            <th>Batch No</th>
            <th>Kedaluwarsa</th>
            <th class="num">Jumlah</th>
            <th></th>
        </tr>
        </thead>
        <tbody id="item-rows"></tbody>
    </table>
    <div class="row-actions">
        <button type="button" class="secondary" id="add-row">Tambah baris</button>
    </div>

    <div class="row-actions">
        <button type="submit">Simpan</button>
        <a href="<?= site_url('receptions') ?>">Kembali</a>
    </div>
</form>

<?php if ($reception !== null && $reception['logs'] !== []): ?>
    <section class="card">
        <h2>Riwayat Aksi</h2>
        <table>
            <thead><tr><th>Waktu</th><th>Aksi</th><th>Petugas</th></tr></thead>
            <tbody>
            <?php foreach ($reception['logs'] as $log): ?>
                <tr>
                    <td><?= esc($log['created_at']) ?></td>
                    <td><?= esc($log['action']) ?></td>
                    <td><?= esc($log['actor_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>
<?php endif ?>

<script>
    window.RECEPTION_DATA = {
        id: <?= $reception === null ? 'null' : (int) $reception['id'] ?>,
        items: <?= json_encode($reception['items'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
        medicines: <?= json_encode(array_map(static fn ($m) => ['id' => (int) $m['id'], 'name' => $m['name'], 'unit' => $m['unit']], $medicines), JSON_UNESCAPED_UNICODE) ?>,
        endpoints: {
            create: '<?= site_url('api/receipts') ?>',
            update: '<?= site_url('api/receipts') ?>/'
        }
    };
</script>
<script src="<?= base_url('assets/reception-form.js') ?>"></script>
<?= $this->endSection() ?>
