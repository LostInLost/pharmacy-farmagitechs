---
title: "Temuan audit Postman collection + rencana docs/postman.md"
type: decision
summary: "Dokumentasi Postman hanya hidup di dalam JSON collection (info.description + 6 folder description + 43 request description) sehingga tak terbaca di GitHub. Temuan: (1) mojibake cp1252 71x — 52 em-dash, 1 en-dash, 17 arrow, 1 not-equal; repair targeted string-replace terverifikasi aman (JSON parse OK, struktur identik); (2) hitungan aktual 43 request / 104 pm.test / 81 pm.expect, README klaim 42/99 (basi); (3) newman 6.2.2 terpasang, tanpa root package.json sehingga CLI via node node_modules/newman/bin/newman.js; (4) collection-level script prerequest isi X-CSRF-TOKEN dari cookie, test simpan token segar; variabel otomatis: csrf_token (0.1), receipt_id (3.1), foreign_receipt_id+foreign_reference (3.13), medicine_code+medicine_id (4.6). Rencana: berkas baru docs/postman.md sebagai cermin markdown + masuk Peta Dokumentasi README."
tags: ["postman", "newman", "dokumentasi", "audit", "mojibake", "farmagitechs", "plan"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T07:26:14Z"
updated_at: "2026-10-07T07:26:14Z"
---

# Audit Postman collection — 2026-10-07

## Di mana dokumentasi Postman hidup sekarang
Semua di dalam `postman/Pharmacy-Farmagitechs.postman_collection.json`:
- `info.description`: base URL, autentikasi & CSRF, bentuk response sukses (tabel), bentuk error (tabel status 401/403), data contoh seeder, catatan operasional.
- Deskripsi 6 folder: aturan payload, status per folder, katalog pesan 422.
- Deskripsi 43 request: payload contoh, response contoh, kondisi status.
Tidak ada satu pun versi markdown -> tak terbaca dari GitHub, hanya dari Postman app.

## Temuan teknis
1. **Mojibake cp1252** (UTF-8 dibaca cp1252 lalu di-encode ulang): 71 kemunculan
   - 52x em-dash `â€"` -> `—`
   - 1x en-dash `â€"` -> `–`
   - 17x arrow `â†'` -> `→`
   - 1x not-equal `â‰ ` -> `≠`
   Repair: targeted string replace 4 sekuens. Terverifikasi: 0 sisa, JSON parse OK, struktur folder/request identik, byte lain tak tersentuh.
2. **Hitungan aktual**: 43 request, 104 `pm.test`, 81 `pm.expect`, 6 folder.
   Per folder: 0.Bootstrap 1/2, 1.Auth 5/12, 2.Stocks 5/19, 3.Receipts 16/39, 4.Medicines 12/24, 5.Unauthenticated 4/8.
   README klaim "42 request dalam 6 folder (99 assertion)" -> basi.
3. **Newman 6.2.2** terpasang di node_modules; tidak ada package.json root -> CLI lewat `node node_modules/newman/bin/newman.js run ...`.
4. **Collection-level script**: prerequest mengisi header `X-CSRF-TOKEN` dari cookie `csrf_cookie_name` (fallback variabel `csrf_token`); test menyimpan token segar dari setiap response header.
5. **Variabel otomatis** (README belum menyebut csrf_token): `csrf_token` (item 0.1), `receipt_id` (3.1), `foreign_receipt_id` + `foreign_reference` (3.13), `medicine_code` + `medicine_id` (4.6). Environment hanya `base_url`.
6. Collection **git-tracked** (bukan gitignored).

## Rencana
- Berkas baru `docs/postman.md`: cermin markdown dokumentasi collection (berkas, cara jalan GUI+CLI, environment & variabel, autentikasi & CSRF ringkas + tautan security.md, tabel folder & yang dibuktikan, bentuk response/error, aturan validasi payload, katalog pesan 422, pemetaan ke 5 skenario soal, jebakan umum, catatan pemeliharaan).
- README: Peta Dokumentasi 8 -> 9 baris; §7 tautkan docs/postman.md; perbarui angka 42/99 -> 43/104.
- Opsional (perlu konfirmasi user, menyentuh deliverable): perbaiki mojibake di collection + tambah baris penunjuk ke docs/postman.md di info.description.
- Catatan drift: deskripsi collection tetap sumber di app; docs/postman.md cermin -> perlu sinkron manual saat collection berubah.