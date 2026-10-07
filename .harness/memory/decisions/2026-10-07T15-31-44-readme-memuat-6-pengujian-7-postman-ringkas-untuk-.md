---
title: "README memuat §6 Pengujian + §7 Postman (ringkas) untuk memenuhi daftar \"wajib memuat\""
type: decision
summary: "README kembali memuat §6 Pengujian dan §7 Postman dalam bentuk ringkas (7.220 -> 13.067 char) sesuai tuntutan literal \"README.md wajib memuat\" pada soal: §6 berisi perintah `composer run test` + angka 161 test/561 assertion (dari build/logs/logfile.xml), 7 langkah verifikasi manual, angka baseline on_date=2026-10-03, dan 5 asumsi/batasan; §7 berisi dua berkas Postman (45 request/111 assertion), perintah Newman, urutan folder 0→5, autentikasi cookie+CSRF, dan db:refresh pasca-run. Detail tetap di docs/testing.md dan docs/postman.md. Verifikasi: 51 tautan relatif + 49 anchor resolve."
tags: ["readme", "dokumentasi", "testing-doc", "postman", "farmagitechs", "selesai"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T15:31:44Z"
updated_at: "2026-10-07T15:31:44Z"
---

# README kembali memuat §6 Pengujian & §7 Postman (versi ringkas)

## Konteks
Soal menuntut "README.md wajib memuat" 7 butir, termasuk (6) cara menjalankan pengujian otomatis / verifikasi manual + asumsi & batasan, dan (7) lokasi & cara menjalankan Postman Collection. Setelah redesign hub 2026-10-07, dua butir itu hanya berupa tautan di Peta Dokumentasi ke `docs/testing.md` dan `docs/postman.md`.

## Keputusan user (2026-10-07)
Tambah §6 dan §7 **ringkas** di README (4-8 baris inti per bagian) plus tautan ke docs/ untuk detail — memenuhi syarat literal tanpa mengembalikan README ke 18.826 char.

## Hasil
- **README.md**: 7.220 → **13.067 char**. Ditambah:
  - **§6 Pengujian dan Verifikasi**: `composer run test` (DB test terpisah, fallback SQLite3), angka 161 test/561 assertion hijau (2026-10-07), 7 langkah verifikasi manual, angka baseline seed `on_date=2026-10-03` (101 tersedia 134 dst), dan 5 butir asumsi/batasan (batch nol tampil, tanpa DELETE, `created_by` tetap + `updated_by` NULL, stok bukan laporan historis, tanpa paginasi).
  - **§7 Postman Collection**: dua berkas collection/environment (45 request, 111 assertion), perintah Newman, urutan folder 0→5, autentikasi cookie sesi + CSRF rotasi, dan peringatan `composer db:refresh` setelah run penuh.
- Anchor baru yang dipakai: `docs/testing.md#cakupan`, `tests/README.md#running-the-tests`, `docs/database.md#sumber-tulis`, `docs/database.md#konvensi`, `README.md#4-akun-demo-dan-autentikasi` (semua diverifikasi ada).

## Verifikasi
- 51 tautan relatif README resolve; 49 anchor lintas 12 berkas resolve (skrip PowerShell slug GitHub-flavored).
- Angka 161/561 dikutip dari `build/logs/logfile.xml` (artefak run 2026-10-07 22:20), bukan hafalan.
- 45 request/111 assertion Postman dihitung ulang dari JSON collection.

## Catatan
- Frase "tidak diwajibkan" pada butir paginasi diganti netral ("belum dilakukan") agar README tidak berbau soal.
- Dua angka basi ikut diperbaiki: jumlah catatan memori di README (45 → jumlah riil) dan ukuran README di docs/ai-memory.md.
