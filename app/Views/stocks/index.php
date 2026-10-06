<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<h1>Laporan Stok</h1>

<form class="card" method="get" action="<?= site_url('stocks') ?>">
    <div class="grid">
        <div>
            <label for="on_date">Tanggal pemeriksaan kedaluwarsa</label>
            <input type="date" id="on_date" name="on_date" value="<?= esc($onDate) ?>">
        </div>
        <div style="display:flex;align-items:flex-end">
            <button type="submit">Tampilkan</button>
        </div>
    </div>
    <p class="muted">Jumlah stok selalu dihitung dari seluruh transaksi tersimpan; tanggal hanya menentukan status kedaluwarsa.</p>
</form>

<table>
    <thead>
    <tr>
        <th>Kode</th>
        <th>Obat</th>
        <th>Satuan</th>
        <th class="num">Fisik</th>
        <th class="num">Tersedia</th>
        <th class="num">Kedaluwarsa</th>
        <th>Batch</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($medicines as $medicine): ?>
        <tr>
            <td><?= esc($medicine['code']) ?></td>
            <td><?= esc($medicine['name']) ?></td>
            <td><?= esc($medicine['unit']) ?></td>
            <td class="num"><?= (int) $medicine['physical_quantity'] ?></td>
            <td class="num"><?= (int) $medicine['available_quantity'] ?></td>
            <td class="num"><?= (int) $medicine['expired_quantity'] ?></td>
            <td>
                <?php if ($medicine['available_batches'] === [] && $medicine['expired_batches'] === []): ?>
                    <span class="muted">belum ada batch</span>
                <?php else: ?>
                    <details>
                        <summary><?= count($medicine['available_batches']) + count($medicine['expired_batches']) ?> batch</summary>
                        <table>
                            <thead><tr><th>Batch</th><th>Kedaluwarsa</th><th class="num">Jumlah</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($medicine['available_batches'] as $batch): ?>
                                <tr>
                                    <td><?= esc($batch['batch_no']) ?></td>
                                    <td><?= esc($batch['expires_on'] ?? '-') ?></td>
                                    <td class="num"><?= (int) $batch['quantity'] ?></td>
                                    <td><span class="badge available">tersedia</span></td>
                                </tr>
                            <?php endforeach ?>
                            <?php foreach ($medicine['expired_batches'] as $batch): ?>
                                <tr>
                                    <td><?= esc($batch['batch_no']) ?></td>
                                    <td><?= esc($batch['expires_on'] ?? '-') ?></td>
                                    <td class="num"><?= (int) $batch['quantity'] ?></td>
                                    <td><span class="badge expired">kedaluwarsa</span></td>
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
<?= $this->endSection() ?>
