---
title: "Laporan stok: satu daftar `batches` ber-flag is_expired; `INNER JOIN medicines` (kehilangan obat tanpa gerakan: 23→8 dev, 400→350 skala), filter `moved_at`, dan pembuangan `net <= 0` DITOLAK. Bagian A dibatalkan karena hanya -5,9% terukur. reportRows tetap 1 query + LEFT JOIN."
type: decision
summary: "Laporan stok: satu daftar `batches` ber-flag is_expired; `INNER JOIN medicines` (kehilangan obat tanpa gerakan: 23→8 dev, 400→350 skala), filter `moved_at`, dan pembuangan `net <= 0` DITOLAK. Bagian A dibatalkan karena hanya -5,9% terukur. reportRows tetap 1 query + LEFT JOIN."
tags: ["stok", "laporan", "stock_movements", "on_date", "api-shape", "benchmark", "optimasi"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T14:51:11Z"
updated_at: "2026-10-07T14:51:11Z"
---

# Laporan stok: satu daftar `batches` ber-flag; `INNER JOIN medicines` dan filter `moved_at` ditolak

**Tanggal:** 2026-10-07
**Status:** keputusan

## Keputusan

1. **Bentuk respons berubah (breaking):** `available_batches` + `expired_batches` digabung menjadi **satu** `batches[]` dengan flag `is_expired` (boolean). Tiga angka ringkasan (`physical_quantity`, `available_quantity`, `expired_quantity`) **tetap** ada dan dihitung dari daftar yang sama — bukan query ringkasan terpisah.
2. **`INNER JOIN medicines` untuk agregat batch DITOLAK.**
3. **Filter `moved_at <= onDate` DITOLAK.**
4. **Pembuangan `net <= 0` DITOLAK.**
5. **Rencana awal 'Bagian A' (filter obat nonaktif di dalam subquery) DIBATALKAN** karena tidak terukur.

## Alasan + bukti

### `INNER JOIN` membuang obat tanpa gerakan
Bentuk `FROM stock_movements sm JOIN medicines m ON m.id = sm.medicine_id AND m.is_active = 1 GROUP BY sm.medicine_id, sm.batch_no` menghilangkan obat aktif yang belum punya satu pun baris ledger.

- Data dev (`on_date=2026-10-03`): obat muncul **23 → 8**; 15 obat hilang (106, 109, 110, 111, 113, 114, 115, 116, 117, 119, 120, 121, 122, 123, 127).
- Skala sintetis (500 obat, 240.000 baris ledger, 50 obat aktif tanpa gerakan): **400 → 350**.
- Melanggar `StockReportTest::testMedicineWithoutBatchStillAppears` dan janji di collection: *semua obat aktif selalu muncul meski stok 0*.

**Karena itu `LEFT JOIN` dari `medicines` dipertahankan** — itulah yang menjamin janji tersebut.

### Filter `moved_at` mengubah arti `on_date`
`on_date` **hanya** garis klasifikasi kedaluwarsa, bukan filter gerakan (tertulis di deskripsi folder 2. Stocks collection). Kalau `moved_at` ikut difilter, artinya berubah jadi *stok per tanggal X* — fitur baru, bukan optimasi. Dampak di data dev: obat 102 125 → 16, obat 103 33 → 18. Angka 16/18 kebetulan sama dengan angka brief sehingga filter ini **tampak benar**; `testOnDateDefaultsToToday` hanya mengunci `on_date` sebagai teks, jadi perubahan arti ini lolos tanpa test gagal.

### Pembuangan `net <= 0` menyembunyikan oversell
`StockMovementLedgerTest::testOverUsageIsReportedAsNegativePhysical` mengunci `physical_quantity = -3` (stok 6, terpakai 9). Pembuangan net hanya boleh di tampilan, bukan perhitungan.

### Bagian A dibatalkan: -5,9% tidak sebanding
Benchmark `pharmacy_bench` (500 obat; 240.000 baris; 100.000 milik obat nonaktif), 7 iterasi, min:

| Varian | min | vs sekarang |
|---|---|---|
| V1 sekarang | 555,7 ms | — |
| V2b (IN subquery) | 522,8 ms | **-5,9%** |
| V2a (INNER JOIN) | 466,6 ms | -16,0% tapi kehilangan 50 obat |

Selisihnya nyata (seluruh 7 iterasi V2b di bawah seluruh 7 iterasi V1), tetapi tidak sebanding dengan menambah subquery pada laporan yang jarang dibuka. **Pelajaran: rencana optimasi harus diukur sebelum dikerjakan, bukan disusun dari penalaran deduktif.**

## Konsekuensi

- Konsumen yang disesuaikan: `schemas.ts` (Zod), `stock-report.tsx`, `stock-batch-sheet.tsx`, `public/assets/js/pages/stocks.js`, `frontend/scripts/verify-stocks.mjs`, 3 test feature + `ReceptionScenarioTest`.
- `StockRepository::reportRows()` **tidak berubah** — tetap 1 query, tetap `LEFT JOIN`, tetap dikunci `QueryCountTest`.
- `batchReferences()` tetap tanpa filter obat aktif (memori batch dropdown).
- Hasil verifikasi: suite **161 test / 561 assertion** hijau; Newman **45 request / 111 assertion** 0 gagal; payload live diperiksa (0 dari 22 obat tidak konsisten antara total dan rincian batch).

## Catatan untuk agen berikutnya

- Jangan usulkan lagi `INNER JOIN medicines`, filter `moved_at`, atau pembuangan `net <= 0` pada laporan stok.
- `StockRepository` adalah satu-satunya repository tanpa Model, dan itu disengaja: `reportRows()` adalah SELECT lintas tabel dengan derived table yang tidak bisa diungkapkan Model CI4 (Model terikat satu `$table`; `$allowedFields` hanya berlaku saat insert/update).
- Dua angka dokumentasi yang sebelumnya basi kini tersinkron: AGENTS.md, `docs/testing.md`, `docs/postman.md`.