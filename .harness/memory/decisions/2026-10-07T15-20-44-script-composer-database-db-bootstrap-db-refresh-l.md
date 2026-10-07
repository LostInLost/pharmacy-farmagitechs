---
title: "Script composer database (db:bootstrap/db:refresh) lewat scripts/db-bootstrap.php; spark selalu exit 0 sehingga exit code tidak bisa jadi penjaga"
type: decision
summary: "Script composer db:migrate/db:seed/db:bootstrap/db:refresh (analog pnpm run) merangkai spark; WAJIB lewat scripts/db-bootstrap.php karena `spark` selalu exit 0 walau gagal, sehingga rantai `composer run` biasa melanjutkan seeder di atas DB yang belum siap dan melaporkan sukses palsu. Kegagalan dideteksi dari keluaran (`[KelasException]` case-insensitive + \"Migration failed!\"), bukan kode keluar."
tags: ["composer", "spark", "cli", "seeder", "migrasi", "bootstrap", "exit-code", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T15:20:44Z"
updated_at: "2026-10-07T15:20:44Z"
---

## Keputusan

Setup database dirangkai jadi script `composer` (permintaan user 2026-10-07, "kayak pnpm") di `composer.json`:

| Script | Isi |
| --- | --- |
| `composer db:migrate` | `php spark migrate` |
| `composer db:seed` | `db:seed StockSeeder` + `db:seed DemoUsersSeeder` |
| `composer db:bootstrap` | `db:migrate` + `db:seed` (cukup ini dari DB kosong) |
| `composer db:refresh` | `migrate:refresh` + `db:seed` (kembalikan baseline setelah Newman) |

Semuanya memanggil satu titik masuk `scripts/db-bootstrap.php <mode>` yang menjalankan langkah-langkah via `exec()` dan **berhenti di kegagalan pertama** dengan exit code non-nol.

## Kenapa bukan `"db:seed": ["@php spark ...", "@php spark ..."]` polos

Rancangan pertama memakai rantai script composer biasa. Saat diuji dengan probe sengaja gagal (`db:seed SeederTidakAda`), seeder kedua **tetap dijalankan** dan `composer` melaporkan exit 0. Penyebabnya ada di kerangka CI4:

- `Commands::run()` memanggil `$class->run($params)` tanpa memakai nilai kembaliannya.
- Command database (`Seed`, `Migrate`) menangkap `Throwable` lalu `showError()` → view `errors/cli/error_exception.php` mencetak trace, dan method selesai tanpa melempar apa pun.
- `Boot::runCommand()` mengubah `void` jadi `EXIT_SUCCESS`.

Hasilnya: `php spark db:seed SeederTidakAda` → exit **0** walau seeder tidak ada, dan `php spark migrate` → exit 0 walau DB mati. Exit code tidak bisa dipakai sebagai penjaga.

## Deteksi kegagalan

Karena exit code tidak berguna, `scripts/db-bootstrap.php::sparkFailed()` membaca keluaran:

- baris berkurung `[...]` yang memuat `Exception`/`Error`/`Throwable` → regex case-**insensitive**, karena kelas internal PHP memakai huruf kecil (`[mysqli_sql_exception]` saat koneksi DB mati); versi case-sensitive sempat meleset.
- `Migration failed!` (label `Migrations.generalFault`), karena migrasi yang gagal dipanggil lewat `CLI::error`, bukan view exception.

Diuji 10 kasus (exception framework, `mysqli_sql_exception`, `[Error]`, `[TypeError]`, `Migration failed!`, plus baris sukses seperti `Migrations complete.`/`Seeded: ...`/`| [table] |`) — semua cocok.

## Verifikasi

- `composer db:bootstrap` di DB terisi: idempoten, exit 0, `receptions` tetap kosong.
- `composer db:refresh`: memulihkan baseline (medicines 25, suppliers 3, seed_batch_stock 10, stock_usage 3, users 2, receptions 0).
- Jalur gagal: `.env` sementara diarahkan ke port 3307 → chain berhenti di `migrate`, `Gagal pada langkah: migrate`, exit 1, seeder tidak dijalankan; `.env` dipulihkan dan diverifikasi identik via `Get-FileHash`.
- `db:refresh` menolak jalan saat `CI_ENVIRONMENT = production` (dibaca dari `.env` tanpa bootstrap CI4, karena `ENVIRONMENT` belum terdefinisi di skrip mandiri).
- `composer run test` tetap hijau: **161 test / 561 assertion**.

## Dokumentasi tersentuh

`README.md` (bagian script database + langkah instalasi awal `composer install`), `AGENTS.md` §5 & §2, `docs/conventions.md` (baris `scripts/`), `docs/testing.md` (verifikasi manual), `docs/postman.md` (pemulihan baseline → `composer db:refresh`).
