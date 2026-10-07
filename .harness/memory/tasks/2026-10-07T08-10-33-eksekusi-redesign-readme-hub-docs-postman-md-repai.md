---
title: "Eksekusi redesign README hub + docs/postman.md + repair mojibake collection (selesai, belum di-commit)"
type: decision
summary: "Selesai dieksekusi 2026-10-07: README jadi hub (Peta Dokumentasi 9 baris + Kesesuaian dengan Soal + GET /api/csrf + Struktur Proyek diperluas), berkas baru docs/postman.md (10 bagian), back-link di 5 docs + frontend/README.md + tests/README.md, mojibake collection 71x diperbaiki (52 em-dash, 1 en-dash, 17 arrow, 1 not-equal) + pointer docs/postman.md di info.description + nomor folder salah diperbaiki. Verifikasi: 77 tautan relatif + 47 anchor 0 error, 0 tautan ke berkas gitignored, Newman pasca-edit 15 request hijau folder 0/1/2/5, angka README 147 test/477 assertion (dari build/logs/logfile.xml) & 43 request/104 assertion. Commit belum dijalankan karena spawn proses diblokir sandbox (EPERM git/php/node)."
tags: ["readme", "dokumentasi", "postman", "mojibake", "farmagitechs", "selesai", "belum-commit"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T08:10:33Z"
updated_at: "2026-10-07T08:10:33Z"
---

# Eksekusi redesign dokumentasi Farmagitechs — 2026-10-07

## Keputusan user (turn 2)
- D1 = a: perbaiki mojibake collection JSON.
- D2 = ya: tambah penunjuk `docs/postman.md` di `info.description`.
- D3 = setuju: docs/postman.md dikerjakan sebelum README hub.

## Yang dikerjakan
1. **docs/postman.md (BARU, 10 bagian)**: berkas, menjalankan (GUI+Newman CLI), environment & variabel otomatis (6 variabel + pemicunya), autentikasi & CSRF (tautan security.md), peta 6 folder (request/assertion/yang dibuktikan), bentuk respons & error, aturan validasi payload + katalog pesan 422, pemetaan 5 skenario soal, jebakan umum (7), pemeliharaan.
2. **postman collection**: repair targeted 4 sekuens mojibake (52 em-dash, 1 en-dash, 17 arrow, 1 not-equal = 71 total) via string-replace; append 1 paragraf pointer docs/postman.md; fix `folder 4. Unauthenticated` -> `5.` di info.description. Verifikasi: JSON parse OK, struktur folder/request identik, request+scripts byte-identik, CRLF preserved, 0 mojibake sisa.
3. **README.md** (18.826 -> 24.036 char): +Peta Dokumentasi (9 baris, deep-link anchor), +§Kesesuaian dengan Soal (2 tabel traceability: 7 butir wajib + 7 ketentuan non-README), +baris GET /api/csrf di tabel endpoint, +tautan docs/postman.md/tests/README.md/scripts/run-tests.php/.github workflow, Struktur Proyek diperluas (docs/frontend/postman/.github/build), angka diperbarui: 139/449 -> **147 test, 477 assertion**; 42/99 -> **43 request, 104 assertion**.
4. **Back-link 2 arah**: docs/database.md, docs/security.md (+tautan backend-cors), docs/conventions.md, docs/backend-cors.md (+tautan frontend verifikasi), docs/frontend-theme.md, frontend/README.md (+Dokumentasi terkait), tests/README.md.

## Verifikasi
- Link checker buatan sendiri: 11 berkas md, **77 tautan relatif + 47 anchor, 0 error**; 0 tautan ke berkas gitignored (Technical Test doc tidak pernah ditautkan); semua 11 target punya >=1 tautan masuk (README: 9).
- Newman in-process (tanpa spawn) pasca-edit collection: folder 0/1/2/5 = 15 request, 15 test, 41 assertion, 0 failure. Aturan hitung Newman = jumlah blok pm.test (terkalibrasi per item).
- Angka test dari `build/logs/logfile.xml` (mtime 2026-10-07T07:06Z, lebih baru dari semua berkas test): 147 tests, 477 assertions, 0 error/failure/skipped.

## Belum selesai
- **Commit belum dijalankan**: spawn proses diblokir sandbox (EPERM untuk git, php, node, cmd, powershell). Perintah commit disiapkan ber-pathspec untuk user.
- `composer run test` belum dijalankan ulang sendiri (spawn PHP diblokir); angka 147/477 diambil dari artefak run terakhir.
- Postman full run tidak dijalankan (mengubah data dev); hanya folder read-only yang diverifikasi.

## Catatan pemeliharaan
docs/postman.md adalah cermin markdown dari deskripsi collection; saat collection berubah, perbarui keduanya.