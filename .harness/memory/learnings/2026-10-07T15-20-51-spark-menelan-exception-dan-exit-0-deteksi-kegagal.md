---
title: "spark menelan exception dan exit 0 — deteksi kegagalan dari keluaran, bukan kode keluar"
type: learning
summary: "`php spark <command>` selalu exit 0 walau gagal (Commands::run membuang return, command menangkap Throwable lalu showError, Boot mengubah void → EXIT_SUCCESS), sehingga rantai composer/CI tidak berhenti di kegagalan. Deteksi harus dari keluaran: `[KelasException]` dengan regex case-insensitive (kelas internal PHP huruf kecil, mis. `mysqli_sql_exception`) atau `Migration failed!`."
tags: ["spark", "cli", "exit-code", "ci4", "composer", "seeder", "migrasi", "jebakan"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T15:20:51Z"
updated_at: "2026-10-07T15:20:51Z"
---

## Gejala

`php spark db:seed SeederTidakAda` mencetak blok exception lengkap dengan backtrace, tetapi `$LASTEXITCODE` = **0**. Sama untuk `php spark migrate` saat MySQL mati. Akibatnya rantai `composer run` yang berisi beberapa `@php spark ...` **tidak berhenti** di langkah yang gagal — seeder kedua tetap dijalankan di atas database yang belum siap, dan CI/otomasi melihat "sukses".

## Rantai sebab di kerangka CI4

1. `CodeIgniter\CLI\Commands::run()` (baris ~72) memanggil `$class->run($params)` dan **membuang nilai kembaliannya**.
2. Command database menangkap `Throwable` lalu memanggil `$this->showError($e)` yang me-`require` view `errors/cli/error_exception.php` — mencetak trace, tidak melempar ulang.
3. `Boot::runCommand()` mengubah nilai `void` menjadi `EXIT_SUCCESS`.
4. `spark` sendiri hanya `exit(Boot::bootSpark($paths))`.

Jadi exception **tidak pernah** sampai ke pemanggil CLI.

## Cara mendeteksi kegagalan

Baca keluaran, bukan kode keluar:

- View `errors/cli/error_exception.php` selalu membuka dengan baris `[KelasException]`. Cocokkan regex `/^\[.*(Exception|Error|Throwable).*\]$/i` — **flag `i` wajib**, karena kelas internal PHP memakai huruf kecil: `mysqli_sql_exception` (koneksi DB mati). Versi case-sensitive meleset pada kasus ini.
- Kegagalan migrasi memakai `CLI::error(lang('Migrations.generalFault'))` → teks `Migration failed!`, bukan view exception.
- Jangan pakai penanda generik seperti baris kosong atau kata "Error" telanjang — header spark dan output normal (`Migrations complete.`, `Seeded: ...`) harus tetap lolos.

Pola yang sama berguna untuk perintah `spark` lain yang dibungkus skrip/otomasi (`db:table`, `routes`, `migrate:rollback`).

## Catatan tambahan

- Skrip PHP mandiri (dijalankan `@php scripts/xxx.php`) **tidak** punya konstanta `ENVIRONMENT` CI4; untuk mengecek environment, baca `.env` manual (`preg_match` baris `CI_ENVIRONMENT`), jangan memakai `ENVIRONMENT`.
- Membungkus `spark` lewat `exec()` di PHP aman di sandbox DSH: yang diblokir adalah pipa stdio antar-proses Node (`child_process` EPERM), bukan `exec()` PHP.
