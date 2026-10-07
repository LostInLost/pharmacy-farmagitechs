---
title: "Penerimaan lewat sheet + permissions via /api/me (jalur ke JWT cookie)"
type: decision
summary: "Penerimaan kini dikelola lewat sheet (tambah/detail/ubah) di /receptions dengan URL ?new=1|?view=|?edit=; permissions role dikirim lewat /api/me & /api/login sebagai array datar (jalur ke JWT cookie nanti), gating UI pakai permissions untuk aksi non-baris dan can_update per baris; audit log tidak dirender di mana pun."
tags: ["receptions", "sheet", "permissions", "api-me", "astro", "shadcn", "can-update", "postman", "frontend", "jwt-cookie"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T07:39:41Z"
updated_at: "2026-10-07T07:39:41Z"
---

# Penerimaan: sheet tambah/detail/ubah + permissions lewat /api/me

Commit `38c9463` (auth/permissions) dan `2972667` (sheet receptions).

## Permissions di /api/me
- `Config\Permissions::forRole(string $role): array` mengembalikan daftar permission role; `roleHas()` ditulis ulang di atasnya. Role tak dikenal → `[]` (fail-closed).
- `AuthController::login()` dan `me()` menyertakan `user.permissions` (array datar string, mis. `receipt.create`). Dihitung **per request dari session role**, bukan disimpan di session → ubah config langsung berlaku tanpa login ulang.
- Bentuk array datar dipilih agar nanti mudah dipindah apa adanya ke klaim cookie JWT (rencana user: pindah ke JWT cookies).
- Frontend: `authUserSchema.permissions` = `z.array(z.string()).default([])` (entri sesi lama tetap valid + fail-closed), helper `hasPermission()` di `features/auth/permissions.ts` dengan konstanta `PERMISSIONS`.
- `ensureSession()` pindah dari probe `/api/receipts` + fallback sintetis `id: 0` ke `GET /api/me` (TODO lama selesai).

## Gating: permissions vs can_update
- Permission untuk aksi **tidak bergantung baris**: aksi cepat sidebar, tombol header daftar, tombol dashboard → `receipt.create`.
- `can_update`/`can_write` tetap dipakai untuk aksi **per baris** karena `receipt.update-own` butuh data pemilik baris. Keduanya affordance; penegakan di policy server.

## Sheet di /receptions
- Tiga mode dalam satu halaman: `{mode:"create"} | {mode:"view";id} | {mode:"edit";id}`, URL `?new=1` / `?view=<id>` / `?edit=<id>` (prioritas edit → view → new), disinkronkan dengan `history.replaceState`.
- Berkas: `receptions-view.tsx` (orkestrator + daftar), `reception-form-sheet.tsx` (create/edit), `reception-view-sheet.tsx` (read-only). `audit-log-table.tsx` **dihapus**; `features/audit/labels.ts` dipertahankan sebagai padanan label backend untuk menu Audit mendatang.
- Sheet lebar: `data-[side=right]:w-full data-[side=right]:sm:max-w-3xl` (terukur 768px di viewport 1280; bawaan `sm:max-w-sm` = 384px terlalu sempit untuk tabel item).
- Rute lama `/receptions/new` dan `/receptions/{id}/edit` jadi redirect tipis (302) — tautan lama & `SidebarNavigationTest` CI4 tetap aman.
- Komponen: `key` per mode/id untuk remount (pola Master Obat), `onSaved` → tutup sheet + reload daftar + Feedback sukses (bukan redirect).
- Konsekuensi `replaceState` yang disadari: tombol Back keluar halaman, bukan menutup sheet.

## Bug Postman yang ikut diperbaiki (pre-existing)
- `const data = pm.response.json().data` bentrok dengan global sandbox Postman → SyntaxError "Identifier 'data' has already been declared" (4.1 & 4.6 selalu gagal). Rename ke `rows`.
- `pm.collectionVariables.set('medicine_id', data.id)` tidak ikut ter-rename (kutip ter-escape `\u0027`) sehingga `PUT /api/medicines/null` → 404 di 4.7/4.9/4.10. Setelah perbaikan: Newman 43 request / 104 assertion semua lolos.

## Temuan sampingan (belum diperbaiki, di luar scope)
- `negotiateLocale = true` + `Accept-Language: en` dari Chrome membuat pesan server tampil **Inggris** di browser (mis. "reference_no ... is already used by another reception"), padahal default locale `id`. Newman tidak terpengaruh karena tidak mengirim Accept-Language.
