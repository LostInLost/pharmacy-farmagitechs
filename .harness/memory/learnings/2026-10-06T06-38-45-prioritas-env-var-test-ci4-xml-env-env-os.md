---
title: "Prioritas env var test CI4: xml > .env > env OS"
type: learning
summary: "Prioritas env var test CI4: blok <env> phpunit.xml menang atas .env, dan .env menang atas env var OS — kebalikan intuisi. Terverifikasi lewat probe; runner tidak boleh menebak konfigurasi."
tags: ["testing", "codeigniter4", "phpunit", "env", "precedence", "config"]
source: "Probe langsung: phpunit.probe.xml vs cmd set env vs .env, dibaca dari Application.php/PhpHandler.php/DotEnv.php/BaseConfig.php"
confidence: high
scope: project
created_at: "2026-10-06T06:38:45Z"
updated_at: "2026-10-06T06:38:45Z"
---

## Urutan prioritas env var di test CI4 (terverifikasi empiris)

Saat menjalankan PHPUnit untuk proyek CI4, urutan yang menang adalah:

1. `<env name="..." value="..."/>` di phpunit.xml/phpunit.dist.xml  ← paling kuat
2. `.env` proyek
3. environment variable proses OS  ← paling lemah

Ini kebalikan dari intuisi umum ("env var shell selalu menang"). Bukti probe:

- `phpunit.probe.xml` dengan `<env name="database.tests.DBDriver" value="SQLite3"/>` + `.env` berisi MySQLi → driver terpakai **SQLite3**.
- `cmd /c "set database.tests.DBDriver=SQLite3 && php vendor/bin/phpunit"` + `.env` MySQLi → driver terpakai **MySQLi**.

### Mekanismenya (kenapa begini)

- `vendor/phpunit/phpunit/src/TextUI/Application.php:106` menjalankan `(new PhpHandler)->handle($configuration->php())` **sebelum** bootstrap di baris 108. Jadi blok `<php><env>` PHPUnit diterapkan lebih dulu.
- `PhpHandler.php:110-119`: `if ($force || getenv($name) === false) putenv(...)` dan `if ($force || !isset($_ENV[$name])) $_ENV[$name] = $value`.
- Bootstrap CI4 memuat `DotEnv` yang memakai guard `getenv($name, true) === false` (DotEnv.php:97, local-only) dan `empty($_ENV[$name])` (baris 101). Karena PHPUnit sudah mengisi keduanya, `.env` tidak menimpa.
- `.env` menang atas env var OS karena `getenv($name, true)` hanya melihat nilai yang di-set via putenv di dalam proses PHP, bukan env warisan OS. Jadi `.env` tetap menimpa env var OS.

### Implikasi praktis

- Jangan mengandalkan `set VAR=...` di shell untuk meng-override konfigurasi test CI4 — `.env` akan mengalahkannya. Pakai blok `<env>` di XML, atau ubah `.env`.
- Sebuah script pembungkus (runner) **tidak boleh menebak** konfigurasi efektif dengan membaca `.env`/env/XML sendiri, karena urutannya tidak intuitif dan mudah salah. Lebih aman: jalankan PHPUnit seperti biasa dulu, lalu bertindak hanya berdasarkan keluaran nyatanya.
- `BaseConfig::initEnvValue` (BaseConfig.php:217-245) membaca urutan: `$_ENV[shortPrefix.prop]` → `$_SERVER[...]` → `getenv(...)`.

### Konteks lain yang relevan

- Windows CLI: `cmd /c "set nama.bertitik=nilai"` **bisa** menyetel env var bernama titik, tapi PowerShell tidak (`$env:database.tests.DBDriver` gagal). `variables_order = GPCS` (tanpa E) di kedua interpreter.
- `php_ini_loaded_file()` mengembalikan `false` (bukan null) bila tidak ada php.ini dimuat (mis. `php -n`) — jangan langsung dilempar ke parameter bertipe `?string`.
- Sandbox workspace-write menolak tulis di luar workspace (mis. `C:\Users\php8.4\ext-nomysqli`), jadi isolasi ekstensi harus dibuat di dalam workspace.
