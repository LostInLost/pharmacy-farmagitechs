---
title: "Combobox batch penerimaan: daftar muncul saat fokus, lintas obat sebelum obat dipilih"
type: decision
summary: "Combobox Batch No form penerimaan memuat saran dari GET /api/references/batches sekali saat form dibuka, jadi daftar muncul seketika saat kotak difokus (terukur 3 item dalam 120 ms). Bila obat baris belum dipilih, ditampilkan SEMUA batch (satu baris per batch_no, hint = nama obat) sehingga batch bisa dipilih lebih dulu; memilih nomor batch lantas mengisi obat dan tanggal kedaluwarsa. Bila nomor batch dipakai beberapa obat dan obat belum dipilih, obat dibiarkan kosong (tidak ditebak)."
tags: ["frontend", "receptions", "combobox", "batch", "astro", "references"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T12:45:52Z"
updated_at: "2026-10-07T12:45:52Z"
---

## Keputusan

Kolom **Batch No** pada sheet penerimaan (Astro) memakai Combobox creatable (prop freeText), dan saran batch:

1. **Dimuat sekali saat form dibuka** — bersama listSuppliers() + listMedicines() lewat Promise.all di reception-form-sheet.tsx. Tidak ada fetch per fokus, sehingga daftar langsung terisi saat kotak difokus (uji CDP: 3 item siap dalam 120 ms).
2. **Disaring per obat** (batchOptions) bila row.medicine_id > 0 — hint = tanggal kedaluwarsa.
3. **Semua batch** (allBatchOptions) bila obat baris belum dipilih — satu baris per batch_no (nilai combobox wajib unik untuk React key), hint = nama obat. Ini yang membuat batch bisa dipilih lebih dulu tanpa memilih obat.
4. **Memilih batch mengisi baris**: updateBatch() menetapkan medicine_id dan expires_on dari referensi — tanggal hanya diisi bila kolomnya masih kosong (pengguna tetap bisa menimpa), dan obat hanya ditetapkan bila nomor batch itu milik SATU obat (candidates.length === 1). Nomor yang dipakai beberapa obat, sementara obat baris belum dipilih, dibiarkan kosong — tidak ditebak.

## Kenapa sumbernya tetap ledger

Endpoint GET /api/references/batches (StockRepository::batchReferences()) membaca stock_movements, dan pada data seed ledger sudah memuat **semua 10 batch** yang ada di seed_batch_stock:

101/PCT-2501 101/PCT-2601 101/PCT-2602 102/AMX-2601 103/SAL-2601 104/IBU-2601 107/LOR-2501 108/MET-2601 112/FOL-2601 118/ANT-2601

Diverifikasi lewat HTTP live: /api/references/batches → 10 baris, identik dengan seed_batch_stock. Jadi permintaan 'ambil batch yang sudah ada di seed_batch_stock' terpenuhi tanpa mengubah query — dan tanpa menyimpang dari aturan 'stok dibaca dari ledger'.

## Verifikasi

- CDP headless (Chrome + cookie ci_session): fokus kolom batch tanpa memilih obat → 10 item (semua unik); memilih MET-2601 → obat 'Metformin 500 mg tablet', tanggal 2028-02-28.
- pnpm typecheck 0 error, eslint file ini exit 0, pnpm build sukses.
- composer run test **OK (153 tests, 510 assertions)** — endpoint tidak berubah, tidak ada test yang perlu diubah.

## Catatan

Sebelum perubahan ini, saat obat belum dipilih daftar batch kosong dan hanya tampil teks 'Belum ada batch untuk obat ini.' Itu sumber kebingungan pengguna; teks kosongnya kini dibedakan ('Belum ada batch tercatat.') untuk state lintas obat.