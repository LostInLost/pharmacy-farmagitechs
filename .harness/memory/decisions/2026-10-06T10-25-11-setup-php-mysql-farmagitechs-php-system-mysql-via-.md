---
title: "Setup PHP & MySQL farmagitechs: PHP system, MySQL via Laragon"
type: decision
summary: "PHP wajib dari system (C:\Users\php8.4\php.exe), MySQL hanya ada di Laragon sehingga Laragon harus jalan saat test; mysqld tidak bisa distart manual karena paket MySQL Laragon kehilangan folder lib"
tags: ["php", "mysql", "laragon", "testing", "setup", "windows"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T10:25:11Z"
updated_at: "2026-10-06T10:25:11Z"
---

## Keputusan
- **PHP**: satu saja, dari system — `C:\Users\php8.4\php.exe` (8.4.15). PHP Laragon (`E:\laragon\bin\php\php-8.3.33-...`) **tidak dipakai**.
- **MySQL**: hanya tersedia di dalam paket Laragon — `E:\laragon\bin\mysql\mysql-8.4.3-winx64`, datadir `E:\laragon\data\mysql-8.4`, port 3306.
- **Test** (`composer run test`) memakai MySQL, bukan SQLite, sesuai permintaan user.

## Konsekuensi penting
1. MySQL **harus** dinyalakan lewat Laragon. `mysqld.exe` tidak bisa distart langsung dari folder Laragon: paket MySQL-nya kehilangan folder `lib\` (tidak ada sama sekali), sehingga gagal dengan `Can't open shared library ...\lib\plugin\component_reference_cache.dll (errno: 126)`. Sandbox DSH juga memblokir tulis ke `E:\laragon\data` (di luar workspace).
2. Laragon di sini hanya berperan sebagai **server database**; itu tidak melanggar keinginan user agar PHP tetap miliknya sendiri. Bukti: run hijau menampilkan `Runtime: PHP 8.4.15` (PHP system) sambil konek ke MySQL Laragon.
3. Kalau port 3306 kosong, test gagal dengan `mysqli: (HY000/2002) connection refused` → `Tests: 68, Assertions: 37, Errors: 44`. Cek cepat: `Get-NetTCPConnection -LocalPort 3306 -State Listen`.
4. Tidak ada MySQL mandiri di `C:\Program Files` — sudah dipastikan kosong.

## Data
Database di datadir Laragon: `pharmacy_farmagitechs`, `pharmacy_farmagitechs_test`, `pharmacy_farmagitechs_baseline`.

## Catatan lain
- Log PHP dev server (`php -S`) yang berisi `Accepted` / `[200]: GET ...` / `Closing` adalah siklus koneksi TCP normal, bukan error. `Accepted`+`Closing` tanpa request = preconnect browser.
- `mysqli` aktif di php.ini user (baris 928, tidak dikomentari); `sqlite3` (baris 946) masih dikomentari.
