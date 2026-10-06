---
title: "Runner composer test + perbaikan mysqli farmagitechs"
type: learning
summary: "composer run test via scripts/run-tests.php; menjalankan PHPUnit seperti vendor/bin/phpunit dulu, retry -d extension=mysqli hanya bila benar-benar gagal karena mysqli"
tags: ["farmasi", "testing", "composer", "mysqli", "environment", "phpunit"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T05:58:03Z"
updated_at: "2026-10-06T06:45:00Z"
---

## Masalah

`composer run test` gagal dengan "The required PHP extension mysqli is not loaded" karena Composer di PATH memakai PHP `C:\Users\php8.4` (php.ini-nya mematikan mysqli dan tidak bisa diedit). DLL-nya ADA di `C:\Users\php8.4\ext\php_mysqli.dll` dan `php -d extension=mysqli` BERHASIL memuatnya (`dl()` gagal, `php -n` tidak bisa karena extension_dir jadi `C:\php\ext`).

## Solusi

1. `phpunit.dist.xml` (commit `90bfa34`) — blok `<coverage><report>` DIHAPUS karena tanpa driver coverage PHPUnit memunculkan warning "No code coverage driver available" yang menjadi exit 1 akibat `failOnWarning="true"`. Coverage tetap bisa via `vendor/bin/phpunit --coverage-text` (CI `.github/workflows/phpunit.yml` pakai xdebug, aman).
2. `scripts/run-tests.php` (commit `90bfa34`, ditulis ulang di `00b55b6`) — titik masuk `composer run test` (`"test": "@php scripts/run-tests.php"`).
3. `ext-mysqli` TIDAK ditambahkan ke composer.json require karena CI menginstal mysqlnd tanpa mysqli — `composer install` CI akan gagal dengan check-platform-reqs.

## Desain runner final (commit 00b55b6)

Prinsipnya: **jangan menebak konfigurasi proyek**. Urutan:

1. `chdir()` ke root proyek — PHPUnit mencari `phpunit.dist.xml` di cwd, jadi tanpa ini pemanggilan dari direktori lain gagal (terbukti: PHPUnit hanya mencetak Usage, exit 1).
2. Cari binary PHPUnit mengikuti aturan Composer: `COMPOSER_BIN_DIR` → `config.bin-dir` / `config.vendor-dir` di composer.json (termasuk placeholder `{$vendor-dir}`) → PATH (disuntik Composer) → default `vendor/bin` → fallback binary asli di `vendor/phpunit/phpunit/phpunit`.
3. Kalau mysqli sudah aktif: langsung jalankan PHPUnit.
4. Kalau tidak: jalankan PHPUnit **seperti biasa dulu**. Bila exit 0 atau keluaran tidak memuat pesan `required PHP extension "mysqli" is not loaded`, teruskan hasilnya apa adanya. Ini yang membuat fallback bawaan CI4 (SQLite3 `:memory:` di `Config\Database::$tests`) tetap berlaku untuk developer yang tidak memakai MySQL.
5. Hanya bila benar-benar gagal karena mysqli: cek `php -d extension=mysqli -r "extension_loaded('mysqli')"`. Bila bisa → ulangi test dengan flag itu; bila tidak bisa → pesan diagnostik (PHP binary + php.ini) dan exit dengan status kegagalan.
6. `php.ini` yang sedang dipakai diteruskan ke probe dan retry (`-c`), supaya `PHPRC` / `-c` pemanggil tidak hilang. `php_ini_loaded_file()` mengembalikan `false` (bukan null) saat `php -n` — wajib di-normalisasi ke `null` untuk parameter `?string`.

## Verifikasi

`composer run test` exit 0, 60 test / 144 assertion, di PHP PATH (lewat retry) maupun PHP Laragon 8.3.33. Diuji juga: `--filter`, `composer run test -- --filter`, pemanggilan dari cwd luar proyek, `php -n`, tanpa `.env` (fallback SQLite3 jalan tanpa mysqli), `bin-dir`/`vendor-dir` kustom (`vendor-bin`, `{$vendor-dir}/bin-alt`), `COMPOSER_BIN_DIR`, phpunit tidak ditemukan, exit code diteruskan, dan jalur hard-fail mysqli (exit 2 dengan diagnostik).

## Catatan

Lokal di depan `origin/main` dan belum di-push (`git push` diblokir kredensial; workaround MCP `push_files`).
