---
title: "Pesan sukses jadi toast sonner (langsung, tanpa helper); Feedback inline kini error-only"
type: decision
summary: "Simpan sukses di frontend Astro kini menampilkan toast sonner (toast.success langsung dari komponen; Toaster sudah ter-host di app-shell.tsx) — bukan Feedback variant=success. Komponen Feedback dirapikan jadi error-only (variant dihapus, Alert destructive). Bonus: perbaikan lint pre-existing react-hooks/set-state-in-effect di medicine-form-sheet.tsx lewat derived state. Verifikasi: 153 test/510 assertion, pnpm typecheck+lint+build bersih, CDP live membuktikan toast 'Obat dibuat./Obat diperbarui./Penerimaan dibuat.' dan jalur forbidden petugas tetap benar."
tags: ["frontend", "astro", "sonner", "toast", "feedback", "ui", "react-hooks", "lint", "supersedes:2026-10-07T07-39-41"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T12:28:10Z"
updated_at: "2026-10-07T12:28:10Z"
---

## Keputusan

Pesan **sukses** di frontend Astro memakai toast sonner langsung:

- `import { toast } from "sonner"` di komponen pemanggil, lalu `toast.success("...")` — **tanpa helper/wrapper** (keputusan user: "langsung sonner aja").
- Host `<Toaster position="top-right" />` sudah ada di `components/app-shell.tsx` sejak awal — tidak diubah. Sonner menyimpan observer di level modul, jadi toast dari island halaman mana pun tetap tampil.
- Durasi/posisi: default (4 detik, kanan atas).

Pesan **error tetap inline** lewat `components/feedback.tsx` — daftar `errors[]` validasi server harus menempel pada form yang bermasalah. Komponen ini sekarang **error-only**: prop `variant` dihapus, selalu `Alert variant="destructive"` + ikon `CircleAlertIcon`; `role="alert"` bawaan Alert tetap dipertahankan agar skrip verifikasi `[role="alert"]` masih menemukan pesan.

## Berkas yang berubah

- `medicines-table.tsx`, `receptions-view.tsx`: state `notice` dihapus; `onSaved` → `reloadRows()` + `toast.success("Obat dibuat."/"Obat diperbarui."/"Penerimaan dibuat."/"Penerimaan diperbarui.")`.
- `feedback.tsx`: error-only (buang `Variant`, `CircleCheckIcon`, `cn`).
- 12 call site `<Feedback variant="error"` → `<Feedback` (dashboard, medicine-form/view-sheet, reception-form/view-sheet, stock-report, kedua orkestrator).
- `frontend/README.md`: deskripsi `feedback.tsx` + catatan "sukses = toast".
- `frontend/scripts/verify-*.mjs` (write/update/combobox): snapshot juga membaca `[data-sonner-toast]`; teks sukses via `document.body.innerText` tetap valid selama toast tampil.

## Perbaikan lint pre-existing (masih dalam changeset ini)

`medicine-form-sheet.tsx` merah di `react-hooks/set-state-in-effect` (L103 `setLoad` sinkron di badan efek) — terbukti **sudah merah di HEAD** (uji eslint via stdin atas versi HEAD). Diperbaiki dengan pola derived state:

- `loadState` = state internal; `const load = medicineId !== null && canWrite === false ? { status: "forbidden", ... } : loadState` — status forbidden benar sejak render pertama, efek cukup `return` lebih awal tanpa setState.
- Perilaku deep link `?edit=` (keputusan `2026-10-07T11-30-15` + susulan `c600f25`) tidak berubah; dibuktikan live sebagai petugas: pesan "Anda tidak berhak mengubah obat ini.", tanpa form/tombol Simpan, tombol "Tutup" muncul.

## Verifikasi

- `composer run test`: 153 test / 510 assertion OK.
- `pnpm typecheck` + `pnpm lint` + `pnpm build`: bersih (lint tadinya merah pre-existing).
- Live CDP (Chrome headless, skrip sementara di `.harness/tmp/`, tidak di-commit): toast "Obat dibuat." dan "Obat diperbarui." (master obat), "Penerimaan dibuat." (penerimaan via combobox) — `[data-sonner-toast]` terisi, `[role="alert"]` kosong, sheet tertutup.
- Data uji dihapus dari DB dev (receptions 4→3, medicines 29→28, movements 18→17, audit entitas uji dibersihkan).

## Catatan

- Skrip `verify-write.mjs`/lama masih mengasumsikan form select; form penerimaan kini memakai combobox — verifikasi tulis yang relevan adalah `verify-combobox.mjs` (selector `[data-slot="combobox-search"]`; versi HEAD masih memakai `combobox-input` dan sudah tidak cocok dengan komponen working copy tab lain).
- Entri ini menyupersede bagian "onSaved → ... Feedback sukses" pada keputusan `2026-10-07T07-39-41`.