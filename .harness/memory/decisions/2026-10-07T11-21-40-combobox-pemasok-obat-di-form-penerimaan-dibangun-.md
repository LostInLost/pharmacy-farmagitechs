---
title: "Combobox pemasok & obat di form penerimaan: dibangun dari Radix Popover, bukan registry shadcn"
type: decision
summary: "Form penerimaan memakai Combobox (frontend/src/components/ui/combobox.tsx) untuk pemasok dan obat per baris, menggantikan Radix Select. Dibangun dari primitif Radix yang sudah terpasang (Popover via `shadcn add popover`, tanpa dependensi baru) + Input + daftar tersaring sendiri; BUKAN `shadcn add combobox` karena versi registry (radix-nova) butuh @base-ui/react sedangkan proyek berbasis radix-ui dan paket itu tidak bisa dipasang di sandbox (ERR_PNPM_EPERM, store pnpm di luar workspace). Pencarian disaring di klien karena GET /api/references/* mengirim daftar utuh; trigger tetap role=\"combobox\" + id supplier_id agar skrip verifikasi lama jalan; hint satuan disembunyikan bila label sudah memuatnya (21/25 nama obat seed sudah berakhir bentuk sediaan). Popover memakai `modal` (wajib: Sheet induk dialog modal). Verifikasi: typecheck+lint+build hijau, perilaku dibuktikan Chrome headless/CDP; skrip E2E baru frontend/scripts/verify-combobox.mjs."
tags: ["receptions", "combobox", "radix-ui", "popover", "shadcn-registry", "base-ui", "frontend", "astro", "cdp-verification", "sandbox"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T11:21:40Z"
updated_at: "2026-10-07T11:21:40Z"
---

## Keputusan
Form penerimaan (`frontend/src/features/receptions/components/reception-form-sheet.tsx`) kini memakai `Combobox` (`frontend/src/components/ui/combobox.tsx`) untuk pemasok (header) dan obat (tiap baris item), menggantikan Radix `Select`.

Komponen dibangun dari primitif Radix yang **sudah terpasang**: `Popover` (ditambahkan lewat `shadcn add popover`, tidak membawa dependensi baru) + `Input` + daftar tersaring sendiri, lengkap dengan `role="combobox"`/`listbox`/`option` dan navigasi keyboard.

### Kenapa bukan `shadcn add combobox`
Komponen `combobox` registry (radix-nova) bergantung pada `@base-ui/react` + `input-group`. Proyek ini berbasis `radix-ui`, dan `@base-ui/react` tidak bisa dipasang di sandbox ini (store pnpm di `F:/.pnpm-store`, di luar workspace → `ERR_PNPM_EPERM`; `pnpm add` juga gagal symlink). Ini juga sejalan dengan preferensi proyek: komponen `ui/` dijaga dekat ke upstream dan di-port manual, bukan `shadcn add` membabi buta.

## Keputusan pendukung
- **Pencarian disaring di klien**: `GET /api/references/suppliers|medicines` memang mengirim daftar utuh (hanya baris aktif), jadi tidak perlu endpoint pencarian baru.
- **Kontrak DOM dipertahankan**: trigger tetap `role="combobox"` dan `id="supplier_id"`, sehingga `frontend/scripts/verify-write.mjs` (yang mengkueri `#supplier_id` dan `button[role="combobox"]`) tetap bekerja.
- **Hint satuan dikondisikan**: `hint` disembunyikan bila label sudah memuatnya — 21 dari 25 nama obat di `StockSeeder` sudah berakhir dengan bentuk sediaan ("Paracetamol 500 mg tablet"), jadi tanpa ini daftar tampak menduplikasi satuan.
- **Popover `modal`**: bukan pilihan gaya, tapi syarat agar roda mouse bisa menggulir daftar di dalam Sheet — lihat entri memori terkait `react-remove-scroll`.

## Verifikasi
- `pnpm typecheck` + `pnpm lint` + `pnpm build` hijau.
- Perilaku dibuktikan empiris dengan Chrome headless via CDP: pencarian menyaring (40→1), memilih mengisi nilai, roda mouse menggulir daftar, panah bawah menggulir item tersorot ke tampilan, Escape/klik-luar menutup popover **saja** (Sheet tetap terbuka), tanpa error konsol.
- Skrip E2E baru: `frontend/scripts/verify-combobox.mjs` (belum dijalankan end-to-end karena MySQL/backend mati saat pengerjaan).