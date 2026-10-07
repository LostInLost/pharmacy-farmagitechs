---
title: "Master obat: API + halaman Astro, tanpa DELETE dan audit lewat tabel polimorfik"
type: decision
summary: "Master obat (API + halaman Astro) selesai: hak tulis khusus supervisor lewat medicine.write, tanpa DELETE (is_active=0), audit pakai entity_type 'medicine' pada tabel polimorfik yang sudah ada, validasi kode unik case-insensitive, dan can_write pada respons baca."
tags: ["farmasi", "medicines", "master-obat", "api", "astro", "frontend", "policy", "audit", "like-escape", "validation", "postman", "cdp-verification"]
source: "implementasi master obat (API + halaman Astro), sesi 2026-10-07"
confidence: high
scope: project
created_at: "2026-10-07T06:14:13Z"
updated_at: "2026-10-07T06:14:13Z"
---

## Keputusan

Master obat dibuat end-to-end mengikuti lapisan yang sudah ada (Controller → Service → Repository → Policy + Validator), tanpa migrasi baru dan tanpa tabel log baru.

## Backend

- Endpoint: `GET /api/medicines?q=&status=`, `POST /api/medicines`, `GET /api/medicines/{id}`, `PUT /api/medicines/{id}`. Tidak ada DELETE.
- Permission baru di `Config\Permissions`: `medicine.view` (kedua peran), `medicine.write` (supervisor saja). `MedicinePolicy` sengaja tanpa parameter entitas — haknya per peran, bukan per baris.
- **Tanpa hapus**: `medicines.id` dirujuk `reception_items` dan `stock_movements` dengan FK RESTRICT. Obat yang tidak dipakai lagi ditandai `is_active = 0`. Efeknya: hilang dari `GET /api/references/medicines` (dropdown form penerimaan) tetapi tetap tampil di halaman master.
- Audit: `AuditService::ENTITY_MEDICINE = 'medicine'` memakai tabel `audit_logs` yang sudah polimorfik — **tanpa perubahan skema**. Ini pembuktian pertama perluasan tabel audit.
- `can_write` disertakan pada `index()` dan `show()` saja (padanan `can_update` penerimaan), dihitung dari policy. Respons tulis tidak menyertakannya, jadi skema Zod tulis dipisah.

## Jebakan yang terverifikasi

- **Validasi kode harus case-insensitive.** Unique index MySQL memakai collation `_ci`; `codeTaken()` memakai `like('code', $escaped, 'none', true)` agar `OBT-001` vs `obt-001` dijawab 422, bukan error 500 dari database.
- **Wildcard LIKE harus dikunci.** `db->escapeLikeString()` memakai karakter escape `!` (bukan backslash), dan `like()` harus dipanggil dengan `$escape = true` supaya klausa `ESCAPE '!'` ikut ter-render. Tanpa itu, input `%` menjadi wildcard dan seluruh katalog cocok. Diuji lewat `?q=%25` → 0 baris.
- `MedicineRepository` memegang satu instance `MedicineModel`; aman karena `find()/findAll()/countAllResults()` me-reset builder (terverifikasi di source CI4), tetapi urutan panggilan tetap dijaga.
- `AuditService::logUpdated` untuk UPDATE menulis snapshot `after` dari hasil `find()` ulang (keadaan tersimpan), bukan dari payload.

## Frontend

- `features/medicines/`: `schemas.ts`, `api.ts`, `components/{medicines-table,medicine-form-dialog}.tsx`, `index.ts`. Halaman `/medicines`, menu sidebar "Master Obat" (grup Operasional), breadcrumb di `site-header.tsx`.
- Tombol Tambah/Ubah hanya dirender bila `can_write`; penegakan tetap di server.
- **Dua aturan ESLint `react-hooks/set-state-in-effect` dihindari tanpa mematikan aturan**: pemuatan awal memakai pola callback (status awal sudah "loading"), dan form dialog di-reset lewat `key={medicine?.id ?? "new"}` sehingga berpindah baris me-remount komponen. Reset lewat efek ditolak lint.
- `frontend/scripts/verify-medicines.mjs`: 9 assertion, semua lolos di Chrome headless (petugas tanpa tombol tulis, supervisor bisa simpan, filter status cocok dengan API).

## Jebakan verifikasi browser

- Cookie `ci_session` **tidak tersimpan** di cookie jar curl di mesin ini; nilainya harus dibaca langsung dari header `Set-Cookie` respons login.
- Chrome headless dan `pnpm build` butuh `danger-full-access` (spawn proses + named pipe); di sandbox `workspace-write` gagal `EPERM`/Chrome DevTools tidak siap.
- Cacat skrip verifikasi sendiri sempat membuat 2 assertion palsu gagal: (1) jumlah baris dibandingkan dengan snapshot server yang sudah berubah setelah obat uji dibuat → ambil baseline SEBELUM navigasi; (2) memeriksa baris obat baru padahal filter masih "Hanya nonaktif" → kembalikan ke "Semua status" dulu.

## Hasil verifikasi

139 test PHPUnit (449 assertion) hijau; lint 0 error; typecheck 81 berkas 0 error; `pnpm build` sukses; alur live API (petugas 403 / supervisor 201 / duplikat 422 / nonaktif hilang dari dropdown) dan audit `CREATE`+`UPDATE` oleh supervisor terbukti; DB dev dikembalikan bersih (25 obat, 0 sisa audit).

Commit: `8497d26` backend, `8d26fad` frontend, `a182d86` docs. Postman folder `4. Medicines` (12 request) ikut ter-commit di `385e81b` milik sesi paralel.
