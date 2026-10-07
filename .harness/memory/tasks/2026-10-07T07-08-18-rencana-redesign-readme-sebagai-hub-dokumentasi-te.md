---
title: "Rencana redesign README sebagai hub dokumentasi + temuan audit dokumen"
type: decision
summary: "Audit 2026-10-07: README 18.826 char/10 bagian, hanya menautkan docs/database.md, docs/security.md, docs/conventions.md, frontend/README.md, postman/. Dokumen yatim: docs/backend-cors.md, docs/frontend-theme.md, tests/README.md. Soal \"Pharmacy Farmagitechs Technical Test.md\" gitignored -> tidak boleh ditautkan, konteks dijaga via tabel traceability 7 butir wajib. Angka berpotensi basi (139 test/449 assertion; 42 req/99 assertion) vs hitungan statis 147 metode test & Postman 43 req/104 pm.test, artefak terakhir 119 test/381 assertion. README belum memuat GET /api/csrf dan frontend/ di Struktur Proyek. Rencana: README hub + deep-link + back-link docs, lalu verifikasi tautan & angka."
tags: ["readme", "dokumentasi", "audit", "farmagitechs", "plan", "link-map"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T07:08:18Z"
updated_at: "2026-10-07T07:08:18Z"
---

# Rencana redesign README.md (hub dokumentasi) — audit 2026-10-07

## Kondisi awal
- README.md 18.826 char, 10 bagian: Versi/Prasyarat, Diagram DB & setup, Cara menjalankan, Akun demo, Endpoint Utama, Pengujian, Postman, Struktur Proyek, Catatan AI.
- Tautan yang ada hanya: docs/database.md, docs/security.md, docs/conventions.md, frontend/README.md, postman/.
- Dokumen yatim (tanpa tautan masuk): docs/backend-cors.md, docs/frontend-theme.md, tests/README.md.
- Tidak ada back-link dari docs/* ke README; docs/* tidak saling tertaut.

## Temuan penting
- "Pharmacy Farmagitechs Technical Test.md" ada di .gitignore (baris terakhir), TIDAK ada di .git/index -> jangan tautkan dari README (404 di GitHub). Konteks soal dijaga via tabel traceability 7 butir "README wajib memuat".
- Angka berpotensi basi: README klaim "139 test, 449 assertion" dan "42 request (99 assertion)". Hitungan statis: 147 metode test; Postman aktual 43 request / 104 pm.test; artefak build/test-run-labels.txt: 119 test / 381 assertion. Verifikasi ulang via composer run test + Newman sebelum publikasi.
- Tabel endpoint belum memuat GET /api/csrf (app/Config/Routes.php; dipakai frontend lintas origin).
- Struktur Proyek belum memuat frontend/ dan .github/workflows.

## Rencana
- README jadi hub: tabel Peta Dokumentasi + deep-link anchor (#diagram-erd, #model-stok, #proteksi-csrf) + alur baca penilai.
- Traceability 7 butir wajib soal -> bagian README.
- Back-link "Kembali ke README" di docs/database.md, docs/security.md, docs/conventions.md, docs/backend-cors.md, docs/frontend-theme.md; frontend/README.md & tests/README.md dapat "Dokumentasi terkait".
- Perbaikan: tambah GET /api/csrf, perbarui Struktur Proyek, tautkan tests/README.md + backend-cors + frontend-theme.
- Verifikasi akhir: tautan relatif resolve, jangkar heading valid, tanpa tautan ke berkas gitignored.
- Commit terpisah per berkas dengan pathspec eksplisit.