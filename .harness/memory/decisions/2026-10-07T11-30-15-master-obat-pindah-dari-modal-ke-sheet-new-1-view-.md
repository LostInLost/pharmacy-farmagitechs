---
title: "Master obat pindah dari modal ke sheet (?new=1/?view=/?edit=), MedicineFormDialog dihapus"
type: decision
summary: "Master obat kini memakai pola sheet seperti penerimaan: MedicinesTable jadi orkestrator dengan URL ?new=1/?view=<id>/?edit=<id> via history.replaceState, MedicineFormSheet menggantikan MedicineFormDialog (dihapus), MedicineViewSheet baru untuk detail tanpa audit log, gating tombol tambah via PERMISSIONS.medicineWrite + can_write; api/schemas/backend tidak berubah."
tags: ["medicines", "master-obat", "sheet", "astro", "permissions", "can-write", "frontend", "drug-dialog-removed"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T11:30:15Z"
updated_at: "2026-10-07T11:30:15Z"
---

Commit `e6fba21` (+689/−283, 5 berkas). Semua di `frontend/`; backend, API, Postman, dan migrasi tidak disentuh.

## Berkas
- `medicine-form-sheet.tsx` (baru): turunan `reception-form-sheet.tsx`; props `{medicineId: number|null, open, onOpenChange, onSaved, canWrite?}`. `medicineId === null` = tambah. Status `forbidden` bila `canWrite === false` atau `detail.canWrite === false`. Lebar sheet bawaan (`sm:max-w-sm`), bukan 3xl penerimaan — formnya hanya 4 field.
- `medicine-view-sheet.tsx` (baru): turunan `reception-view-sheet.tsx`; `dl` Kode/Nama/Satuan/Status(Badge), footer Tutup + Ubah (bila `canWrite`).
- `medicines-table.tsx` (ditulis ulang): ekspor `MedicineSheetState`; props baru `initialSheet?` + `permissions?`; state `sheet`/`sheetOpen`/`sheetSeq`/`notice`/`reloadToken`/`filter`.
- `medicines.astro`: `numericParam()` + prioritas edit → view → new; `permissions` dari `Astro.locals.user`.
- `medicine-form-dialog.tsx`: **dihapus** (terserap ke form sheet).

## Keputusan yang mudah salah diulang
- Daftar obat **tidak** punya `can_update` per baris (beda dari penerimaan, yang per baris butuh data pemilik). Di sini `can_write` berlaku sama untuk semua baris, jadi sheet detail cukup menerima prop `canWrite`.
- Audit log (`data.logs`, sudah ada di respons detail sejak `f634c0b`) **tidak dirender** di sheet mana pun — konsisten dengan keputusan `2026-10-07T11-01-22` bahwa audit jadi menu tersendiri.
- Efek pemuatan daftar digabung: dependensi `[filter, reloadToken]`, dan `filter` hanya berubah saat submit. Konsekuensi disadari: submit filter dengan nilai sama tidak memicu request ulang; tambahkan `filterSeq` ke dependensi bila itu perlu.
- Kolom Kode jadi anchor `href="/medicines?view=<id>"` (bisa dibuka di tab baru, bekerja tanpa JS) yang kliknya dicegat jadi sheet; tombol Ubah tetap langsung ke mode edit.
- Gating tombol header = `canWrite && hasPermission(permissions, PERMISSIONS.medicineWrite)` (fail-closed bila `permissions` kosong). Keduanya affordance; penegakan tetap di policy server.

## Verifikasi
Sesuai permintaan user: **tanpa test apa pun** — `composer run test`, Newman, `pnpm typecheck`/`lint`/`build` tidak dijalankan (disebut eksplisit di badan commit). Hanya pengecekan statis: tidak ada impor tersisa ke `medicine-form-dialog`. Verifikasi otomatis diserahkan ke user.