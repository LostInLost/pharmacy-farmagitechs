---
title: "Optimasi loop service: laporan stok 1 query, validator bebas N+1"
type: decision
summary: "Laporan stok jadi 1 query (reportRows: LEFT JOIN derived table batch ke medicines) dan validator penerimaan bebas query per baris (activeFlags + knownExpiries, 2 query tetap); jumlah query tidak lagi tumbuh mengikuti jumlah obat/item. Angka respons API tidak berubah."
tags: ["stok", "query-optimization", "n+1", "foreach", "stockreport", "validator", "knownExpiries", "mysql", "farmagitechs"]
source: "Sesi optimasi loop service 2026-10-07"
confidence: high
scope: project
created_at: "2026-10-07T13:50:18Z"
updated_at: "2026-10-07T13:50:18Z"
---

Konteks: user meminta optimasi kode ber-`foreach` tanpa paginasi — "aku gak butuh dipaginasi, itu code dengan foreach di optimasi, minimalisir foreach". Hasil eksekusi:

## Yang diubah

1. `StockRepository::reportRows($onDate)` — satu SELECT: subquery batch (dinetted per `(medicine_id, batch_no)` lewat `SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END)`) di-`LEFT JOIN` ke `medicines`, filter `is_active = 1`, urut `medicine_id, expires_on, batch_no`. Obat tanpa gerak tetap muncul sebagai satu baris `batch_no` NULL. `is_expired` dihitung di subquery yang sama, jadi total dan penanda batch tak mungkin berbeda definisi.
2. `StockService::report()` — satu lintasan atas `reportRows()` memakai indeks obat terakhir (`$index = array_key_last($report)`), tanpa query kedua dan tanpa `array_filter` per obat. Bentuk respons lama dipertahankan byte-compatible (`on_date` + `medicines[]` + `available_batches`/`expired_batches`, `is_expired` boolean).
3. `MedicineRepository::activeFlags($ids)` — satu `whereIn` mengembalikan `id => is_active`, menggantikan `find()` per baris di `ReceptionValidator`.
4. `StockRepository::knownExpiries($batches)` — dua query (satu per tabel `seed_batch_stock` lalu `reception_items`), menggantikan `knownExpiry()` per pasangan. Precedence lama dipertahankan: seed menang (`??=`), lalu `reception_items` urut `id ASC`.
5. `ReceptionService` — `normalizeItem()` dipakai bersama oleh `mapItems()` dan `snapshot()` (satu definisi normalisasi, bukan dua `array_map` serupa).

Dihapus: `StockRepository::batches()`, `summaries()`, `activeMedicines()` (beserta dependensi `MedicineModel` di konstruktor). `batchReferences()` sengaja tetap terpisah dan tanpa filter obat aktif (dropdown form tetap perlu daftar batch lintas obat).

## Hasil ukur

- Laporan stok: **3 query -> 1 query**, konstan berapa pun jumlah obat/batch.
- Validator penerimaan 5 item: query konstan (dibuktikan `tests/Feature/QueryCountTest.php`); pencarian kedaluwarsa **216 query -> 2 query** pada 113 pasangan (dibuktikan `tests/Feature/KnownExpiriesTest.php`).
- Suite: 153 test/510 assertion -> **160 test/560 assertion hijau**.

## Jebakan yang diverifikasi (penting)

`knownExpiries()` memakai `whereIn('medicine_id', ...)` **dan** `whereIn('batch_no', ...)` — pasangan silang (obat A + batch milik obat B) ikut ter-SELECT. Aman karena kunci array dibangun dari nilai baris hasil, bukan dari input, sehingga pasangan-silang hanya bisa cocok bila benar-benar ada di DB. Sudah diuji diferensial vs logika per-pasangan (0 perbedaan) dan diuji negatif (`102|PCT-2601` tidak muncul). Kalau nanti tinggal `whereIn` dihapus/diubah, uji ini yang menangkap.

Catatan MySQL: `FROM (subquery) AS b` adalah derived table yang butuh alias — alias `AS b` wajib ada di MySQL; sudah diverifikasi jalan di MySQL 8.4 (suite berjalan di MySQL `pharmacy_farmagitechs_test`, bukan SQLite).