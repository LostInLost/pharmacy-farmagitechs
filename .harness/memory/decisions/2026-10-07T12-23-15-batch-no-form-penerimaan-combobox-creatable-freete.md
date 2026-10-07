---
title: "Batch No form penerimaan: combobox creatable (freeText) + GET /api/references/batches; popover WAJIB portal+modal di dalam tabel"
type: decision
summary: "Kolom Batch No jadi combobox ketik-bebas (`freeText` + `createLabel`): baris 'Pakai batch baru \"...\"' menyimpan ketikan baru, tanpa perubahan validasi. Saran dari endpoint baru GET /api/references/batches (sesi; 401 tanpa login) → StockRepository::batchReferences(): GROUP BY medicine_id, batch_no + MAX(expires_on) dari ledger stock_movements. Popover JANGAN non-portal: Table memakai `relative w-full overflow-x-auto` sehingga wrapper Radix (fixed) terpotong dan teks panjang meluber."
tags: ["receptions", "combobox", "batch", "radix-ui", "popover", "table", "overflow", "api", "stocks", "ledger", "frontend", "backend"]
source: "commit 7818f3e, 2026-10-07"
confidence: high
scope: project
created_at: "2026-10-07T12:23:15Z"
updated_at: "2026-10-07T12:23:15Z"
---

Keputusan (commit 7818f3e, 2026-10-07):

1. Kolom Batch No di sheet tambah/ubah penerimaan memakai `Combobox` dengan prop baru `freeText` + `createLabel` (frontend/src/components/ui/combobox.tsx). Daftar saran tetap ada, tetapi ketikan yang tidak cocok opsi mana pun bisa disimpan: baris pembuat 'Pakai batch baru "<teks>"' muncul di puncak daftar dan dipilih dengan Enter/klik. Batch baru memang sah menurut ReceptionValidator, jadi TIDAK ada perubahan aturan validasi.

2. Kotak ketik berada DI DALAM popover (bukan di trigger). Ini konsekuensi langsung dari poin 3: mode modal mem-fokuskan konten, sehingga input di trigger akan kehilangan fokus.

3. Popover WAJIB portal ke body + `modal` (seperti combobox pemasok/obat). Alasan: `frontend/src/components/ui/table.tsx` membungkus tabel dengan `relative w-full overflow-x-auto`. Bila konten popover dirender non-portal di dalam `<td>`, wrapper Radix yang `position: fixed` terpotong oleh overflow-x itu dan teks panjang (teks kosong, hint tanggal) meluber keluar kotak. Gejala ini sempat dikira masalah lebar dan TIDAK bisa diperbaiki dengan min-width/max-width — sumbu masalahnya kliping leluhur. `modal` juga yang membuat roda mouse bisa menggulir daftar di dalam Sheet (nested scroll-lock; lihat learnings 2026-10-07T11-21-48).

4. Endpoint saran: GET /api/references/batches (grup api+auth; 401 tanpa sesi) → Controller `ReferenceController::batches()` → `StockRepository::batchReferences()`: `GROUP BY medicine_id, batch_no` dengan `MAX(expires_on)` dari ledger `stock_movements` (sumber tunggal kebenaran stok). MAX(expires_on) dipakai karena gerakan masuk/keluar berulang untuk batch yang sama akan menggandakan saran bila tidak diagregasi.

5. Endpoint /api/stocks DITOLAK sebagai sumber saran: muatannya berat dan `expires_on`-nya bergantung `on_date`. /api/receipts juga ditolak: batch dari stok awal (StockSeeder) tidak akan muncul.

6. Komponen registry `@shadcn/combobox` tetap tidak dipakai (butuh @base-ui/react, proyek berbasis radix-ui) — dicek ulang via MCP shadcn: Type registry:ui, Dependencies: cn, @base-ui/react.

Berkas: app/Repositories/StockRepository.php, app/Controllers/Api/ReferenceController.php, app/Config/Routes.php, tests/Feature/ReferenceApiTest.php, README.md (tabel endpoint), postman item 2.6, frontend/src/features/receptions/{schemas,api,index}.ts, frontend/src/features/receptions/components/reception-form-sheet.tsx.

Verifikasi: 153 test / 510 assertion hijau; Newman item 2.6 lulus 3 assertion; pnpm typecheck + lint + build bersih.