---
title: "Ledger stock_movements sebagai sumber tunggal laporan stok"
type: decision
summary: "Ledger stock_movements jadi sumber tunggal laporan stok: skema, write-through, is_expired computed, dan jebakan (netting per batch, self::CONST, UNSIGNED)"
tags: ["stock", "ledger", "migration", "repository", "is_expired"]
source: "sesi implementasi ledger stok 2026-10-07"
confidence: high
scope: project
created_at: "2026-10-07T01:19:22Z"
updated_at: "2026-10-07T01:19:22Z"
---

## Keputusan
Stok dilaporkan dari satu ledger `stock_movements`; tabel domain tetap ditulis sebagai rincian.

## Skema (migrasi 2026-10-07-000012_CreateStockMovements, commit b1243c1)
`id, medicine_id FK CASCADE, batch_no VARCHAR(50), expires_on DATE NULL, movement_type VARCHAR(20) (seed|receipt|usage), direction VARCHAR(10) (in|out), quantity INT UNSIGNED (selalu positif), reception_id FK CASCADE NULL, moved_at DATETIME, unit_name NULL, created_at`.
Indeks: `(medicine_id, batch_no)`, `(direction, moved_at)`, `(movement_type, moved_at)`, `(reception_id)`.
VARCHAR bukan ENUM agar portabel SQLite. `created_at = moved_at` pada backfill supaya deterministik.

## Aliran
- Write-through: `ReceptionRepository::replaceStockMovements()` dipanggil dari `ReceptionService::create()/update()` dalam transaksi yang sama (delete by reception_id + insertBatch). `StockSeeder::syncMovements()` menghapus hanya tipe seed/usage (baris receipt milik penerimaan nyata dibiarkan).
- Baca: `StockRepository::batches($onDate)` + `summaries($onDate)` membaca ledger saja; `StockService::report()` hanya memetakan flag.
- `is_expired` dihitung saat SELECT: `CASE WHEN MAX(expires_on) IS NOT NULL AND MAX(expires_on) < :onDate THEN 1 ELSE 0 END`. Tidak disimpan. `expires_on == on_date` masih tersedia; NULL = tanpa kedaluwarsa.

## Jebakan yang terbukti
1. `summaries()` WAJIB menet-apkan per batch dulu (subquery `fromSubquery`) baru menjumlah per obat. Menjumlah langsung dengan CASE per baris salah: baris `out` tidak dikaitkan ke batch sehingga `available` membengkak (uji: obat 101 jadi 140, seharusnya 134).
2. Di CI4 Model, konstanta kelas harus diakses `self::CONST`, bukan `$this->CONST` — `$this->CONST` jatuh ke `Model::__get()` dan mengembalikan null, membuat guard arah selalu menolak.
3. MySQL `INT UNSIGNED`: `quantity - 9` langsung melempar `BIGINT UNSIGNED value is out of range`; `SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END)` aman (hasil DECIMAL) dan tetap negatif saat over-usage.
4. `emptyTable()` = `DELETE FROM`, jadi urutan hapus anak dulu penting (`stock_movements` sebelum `medicines`/`receptions`).
5. Test yang menyisipkan baris domain langsung harus ikut menulis ledger, jika tidak laporan tidak berubah (karena sumber baca hanya ledger).

## Verifikasi
115 test / 368 assertion hijau. SQL asli repository dijalankan di SQLite memberi angka identik dengan MySQLi (101=142/134/8, 107=6/0/6). Rollback + migrate ulang di dev DB aman, backfill cocok dengan tabel domain (seed 10 baris, usage 3 baris, arah & qty konsisten).

## UI
Badge batch dari `is_expired`; `#status_filter` (semua/tersedia/kedaluwarsa) menyaring tampilan di klien — tanpa parameter API baru. Commit b3dacd6.