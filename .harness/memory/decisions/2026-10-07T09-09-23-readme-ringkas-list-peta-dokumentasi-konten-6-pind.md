---
title: "README ringkas + list Peta Dokumentasi; konten §6 pindah ke docs/testing.md; frasa berbau soal disapu"
type: decision
summary: "Keputusan user 2026-10-07: README dipangkas drastis (18.826 -> 7.220 char) menjadi pengantar + Peta Dokumentasi (list bullet, deep-link) + bagian 1-5 (versi, setup DB, cara jalan, akun demo, endpoint). Semua konten SETELAH endpoint (Pengujian §6, Postman §7, Struktur Proyek, Catatan AI) dihapus dari README; Pengujian dipindah ke berkas baru docs/testing.md (3457 B), Struktur Proyek dipindah ke docs/conventions.md#struktur-proyek. Frasa berbau soal/ujian (soal, penilai, lampiran, \"Tes Fullstack\") disapu dari seluruh dokumen teknis (0 sisa). Link check: 82 tautan, 38 anchor, 0 error; 9 dokumen punya tautan masuk. README kini 5 bagian + peta 9 baris."
tags: ["readme", "dokumentasi", "refactor", "testing-doc", "farmagitechs", "selesai"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T09:09:23Z"
updated_at: "2026-10-07T09:09:23Z"
---

# README ringkas + Peta Dokumentasi ter-link — 2026-10-07

## Keputusan user (turn 4)
1. README terlalu panjang -> buat ringkas berupa list.
2. Bagian setelah endpoint tidak usah (hapus §6 Pengujian, §7 Postman, Struktur Proyek, Catatan AI dari README).
3. List dictionary (Peta Dokumentasi) harus linkable ke berkas relevan.
4. Kalimat terkait soal jangan diikutkan — dokumen teknis saja.

## Hasil
- **README.md**: 18.826 -> **7.220 char**. Struktur akhir: intro 1 baris + Peta Dokumentasi (9 bullet, deep-link anchor) + §1 Versi, §2 Diagram DB & setup skema, §3 Cara menjalankan, §4 Akun demo, §5 Endpoint Utama (+ contoh request) + pointer "Panduan lain" + 1 baris disclosure AI.
- **docs/testing.md (BARU, 3.457 B)**: penampung §6 lama — menjalankan test, cakupan, database test, verifikasi manual 7 langkah, asumsi & batasan.
- **docs/conventions.md**: + bagian "Struktur Proyek" (tree app/..build/) dari README.
- **Frasa berbau soal disapu**: "soal", "penilai", "ujian", "lampiran", "Tes Fullstack", "Kesesuaian dengan Soal", "syarat minimal soal", "di luar cakupan tes" -> 0 sisa di semua md teknis. Diganti netral: "angka baseline", "berkas seed", "skenario uji end-to-end", "wajib memuat" dihapus.
- **docs/postman.md**: §8 judul jadi "Skenario uji end-to-end"; ref "README §7" dihapus (README §7 tak ada lagi); ref "README §2" diberi tautan anchor.

## Verifikasi
- Link check: 82 tautan relatif, 38 anchor, **0 error**; 9 dokumen punya >=1 tautan masuk (database 16, postman 10, security 9, conventions 8, frontend 8, testing 3, backend-cors 4, frontend-theme 3, tests 3).
- Konten kunci lama: 0 hilang (dicek 27 penanda: endpoint, kredensial, perintah, angka, dsb).
- README: 15 baris endpoint, contoh request ada, akun demo ada, langkah setup ada.
- Arsip lengkap bagian yang dihapus: .harness/tmp/README-bagian-dihapus-2026-10-07.md (tidak di-commit, gitignored).

## Belum dijalankan
- Commit (spawn proses diblokir sandbox: git/php/node EPERM).
- composer run test (angka 147/477 dari artefak run terakhir build/logs/logfile.xml).

## Catatan
- docs/postman.md tetap cermin markdown deskripsi collection.
- Frontmatter README kini menyertakan docs/testing.md di Peta Dokumentasi.