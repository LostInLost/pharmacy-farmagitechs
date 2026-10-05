---
title: "Environment & workaround farmagitechs"
type: learning
summary: "Environment farmagitechs: push via MCP, server harus unconfined, MySQL Laragon E:\, PHP pakai binary Laragon"
tags: ["farmasi", "environment", "sandbox", "laragon", "github"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-05T12:43:23Z"
updated_at: "2026-10-05T12:43:23Z"
---

Workspace F:\Proyek\Lastation\PT Farma Global Teknologi: (1) git push via helper kredensial DIBLOKIR sandbox (sh.exe signal pipe error, wincred helper kosong) — solusi: push file via MCP github push_files; git fetch/reset ke FETCH_HEAD tetap bekerja asal set git config http.sslBackend openssl (schannel gagal SEC_E_NO_CREDENTIALS). (2) CI4 FileHandler session memanggil is_writable() yang GAGAL di dalam sandbox DSH (token confinement) meski file_put_contents sukses — server php -S harus dijalankan unconfined (danger-full-access) untuk uji HTTP. (3) MySQL 8.4.3 Laragon di E:\laragon (bukan di PATH), client di E:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe, root tanpa password. (4) PHP CLI default C:\Users\php8.4 tanpa mysqli/pdo_mysql dan php.ini-nya tidak bisa diedit (deny); pakai PHP Laragon E:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe yang sudah lengkap + composer via E:\laragon\bin\composer\composer.phar, tambah -d extension=zip saat composer butuh ekstraksi zip.
