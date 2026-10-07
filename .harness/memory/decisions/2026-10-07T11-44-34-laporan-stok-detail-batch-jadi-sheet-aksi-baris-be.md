---
title: "Laporan stok: detail batch jadi sheet + aksi baris berupa ikon"
type: decision
summary: "Detail batch laporan stok Astro kini sheet (bukan collapsible): aksi baris jadi ikon BoxesIcon + tooltip, sheet read-only tanpa fetch (data sudah di klien) menampilkan ringkasan angka + tabel batch tersedia/kedaluwarsa, deep link ?view=<medicine_id> disinkronkan replaceState dan diparse di stocks.astro; typecheck 0 error, build OK, commit 5a66e53."
tags: ["stocks", "laporan-stok", "batch", "sheet", "astro", "frontend", "icon-action", "deep-link", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T11:44:34Z"
updated_at: "2026-10-07T11:44:34Z"
---

## Keputusan
Kolom "Batch" pada laporan stok Astro (`frontend/src/features/stocks/components/stock-report.tsx`) berubah dari `Collapsible` di dalam baris menjadi **aksi ikon** (`BoxesIcon`, `Button size="icon-sm" variant="outline"` + Tooltip "Lihat N batch") yang membuka **sheet** detail batch. Obat tanpa batch tetap menampilkan "-" (tanpa tombol).

## Komponen baru
`frontend/src/features/stocks/components/stock-batch-sheet.tsx` — sheet mode baca murni (tanpa fetch: data sudah ada di klien dari `GET /api/stocks`). Isi: ringkasan 3 angka (fisik/tersedia/kedaluwarsa) + dua tabel terpisah "Batch tersedia" dan "Batch kedaluwarsa". Lebar `data-[side=right]:sm:max-w-lg` (lebih sempit dari sheet penerimaan yang `max-w-3xl`). Label tombol memakai **total** batch (tersedia + kedaluwarsa), bukan jumlah terfilter, karena sheet menampilkan keduanya — filter status hanya menyaring baris.

## Deep link & SSR
Sheet disinkronkan ke URL `?view=<medicine_id>` via `history.replaceState` (pola sama dengan medicines/receptions). `stocks.astro` mem-parse `view` numerik → prop `initialViewId`; sheet dibuka saat mount lewat pemuatan awal `GET /api/stocks` (obat dicari di **seluruh** daftar, bukan hanya baris yang lolos filter — mengganti filter saat sheet terbuka tidak boleh mengosongkan sheet).

## Verifikasi
typecheck 91 file 0 error; eslint file stocks 0 error; build SSR OK (butuh `danger-full-access` sekali — esbuild `spawn EPERM`). Belum diuji browser (user minta dicek sendiri). Commit `5a66e53`.

## Catatan
`pnpm lint` global **gagal** karena `medicine-form-sheet.tsx:103` (react-hooks/set-state-in-effect) — file tab lain yang sudah ter-commit di `c600f25`, bukan regresi dari perubahan ini.
