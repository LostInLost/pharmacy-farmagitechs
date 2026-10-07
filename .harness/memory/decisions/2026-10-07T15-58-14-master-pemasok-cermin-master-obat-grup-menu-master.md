---
title: "Master pemasok: cermin master obat + grup menu \"Master Data\" di sidebar Astro"
type: decision
summary: "Master pemasok selesai sebagai cermin master obat: field name+is_active (unique index baru), permission supplier.view/write, grup sidebar \"Master Data\" (Pemasok+Obat) di Astro, tanpa DELETE (is_active=0). 179 test/640 assertion, Newman 57 request/135 assertion hijau; folder Postman 4b baru."
tags: ["suppliers", "master-data", "sidebar", "astro", "permissions", "audit", "postman", "farmagitechs", "migration"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T15:58:14Z"
updated_at: "2026-10-07T15:58:14Z"
---

Master pemasok (`/api/suppliers` + halaman Astro `/suppliers`) dibangun sebagai cermin penuh master obat, dengan grup menu sidebar baru.

## Keputusan yang dikunci bersama user (2026-10-07)
1. **Field pemasok cukup `name` + `is_active`** — tanpa kode/kontak, jadi tidak ada kolom baru; hanya unique index pada `name` (migrasi `2026-10-07-000014_AddUniqueNameToSuppliers`, `forge->addUniqueKey('name') + processIndexes` — portabel MySQL & SQLite3, diverifikasi dari vendor Forge).
2. **Permission baru `supplier.view` / `supplier.write`** di `Config\Permissions` (petugas: view; supervisor: view+write) — tidak menumpang `medicine.*` supaya hak dua master bisa dipisah nanti. `AuthMeTest` diperluas.

## Backend
- Lapisan baru: `SupplierRepository` (findAll/find/nameTaken/insert/update + escapeLike + normalisasi bool), `SupplierValidator` (name wajib ≤150, unik case-insensitive; is_active opsional bool), `SupplierService` (list/detail+logs/create/update, satu transaksi, policy di dalam transaksi, `AuditService`), `SupplierController` (index/show/create/update, `can_write` pada respons baca), `SupplierPolicy`.
- `AuditService::ENTITY_SUPPLIER = 'supplier'` + `KEY_GROUPS['supplier'] = 'suppliers'` → aksi tersimpan `Audit.suppliers.action.create|update`; label ditambah di `app/Language/{id,en}/Audit.php` dan `Supplier.php`.
- Tanpa DELETE: `receptions.supplier_id` FK RESTRICT → pensiun via `is_active = 0`; referensi dropdown otomatis menyaring baris nonaktif.
- Route: `GET|POST /api/suppliers`, `GET|PUT /api/suppliers/(:num)`.

## Frontend Astro
- Fitur baru `frontend/src/features/suppliers/` (schemas Zod, api, `suppliers-table.tsx` orkestrator sheet, `supplier-form-sheet.tsx`, `supplier-view-sheet.tsx`) — pola identik master obat: URL `?new=1`/`?view=`/`?edit=` via `history.replaceState`, `sheetOpen`/`sheetSeq`, toast sonner sukses, gating `can_write` + `PERMISSIONS.supplierWrite`, derived state `forbidden`.
- `pages/suppliers.astro` baru; `PERMISSIONS` diperluas; **grup sidebar baru "Master Data"** (Pemasok `Building2Icon` + Obat) — "Master Obat" dikeluarkan dari grup Operasional. `site-header.tsx`: `MASTER_DATA_PATHS` menampilkan remah induk "Master Data" **tanpa tautan** (grup menu tidak punya halaman sendiri) lewat tipe `Parent { label, href: string|null }`.
- Skrip verifikasi CDP baru `frontend/scripts/verify-suppliers.mjs` (port 9340) — cermin `verify-medicines.mjs`.

## Verifikasi (semua hijau)
- `composer run test`: **179 test / 640 assertion** (dari 161/561); test baru `tests/Feature/SupplierApiTest.php` (18 test) + `AuditActionLabelTest::testSupplierLabels` + assert permission di `AuthMeTest`.
- `pnpm typecheck` + `lint` + `build` bersih (build butuh unconfined karena EPERM esbuild).
- Newman full run: **57 request / 135 assertion, 0 gagal** setelah `composer db:refresh` (koleksi tidak idempoten — run kedua di DB bekas Newman memunculkan 14 kegagalan palsu; refresh dulu selalu).
- Item Postman folder baru **"4b. Suppliers (master pemasok)"** (12 item / 24 assertion) disisipkan di antara folder 4 dan 5; variabel baru `supplier_id`, `supplier_name`.
- Assertion item **2.4 diperkuat**: dari `data.length === 2` menjadi "semua baris aktif dan id 3 tidak ikut", karena jumlah pemasok bisa bertambah oleh data pengguna (mis. "PT Lastation" buatan manual) — angka tetap rapuh, maksud assertion yang penting.

## Catatan operasional
- Migrasi dev diterapkan dengan `composer db:migrate` setelah pra-cek duplikat nama (`GROUP BY LOWER(name) HAVING COUNT(*)>1` kosong).
- DB dev dikembalikan ke baseline + baris manual "PT Lastation" (id 4) beserta audit log-nya dipulihkan manual setelah `db:refresh` menyapunya; `actor_id` log dipulihkan ke 1 karena pengguna id 5 sudah tidak ada setelah seeder ulang.
