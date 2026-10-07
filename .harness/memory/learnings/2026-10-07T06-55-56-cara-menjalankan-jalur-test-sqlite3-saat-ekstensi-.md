---
title: "Cara menjalankan jalur test SQLite3 saat ekstensi sqlite3 mati (php -d extension=sqlite3 + config sementara ber-<env>), lang() hanya memuat berkas bila diminta per grup, API test dari PowerShell pakai file payload + cookie jar, dan Chrome headless harus diserahkan ke user"
type: learning
summary: "Cara menjalankan jalur test SQLite3 saat ekstensi sqlite3 mati (php -d extension=sqlite3 + config sementara ber-<env>), lang() hanya memuat berkas bila diminta per grup, API test dari PowerShell pakai file payload + cookie jar, dan Chrome headless harus diserahkan ke user"
tags: ["testing", "sqlite3", "phpunit", "i18n", "lang", "powershell", "csrf", "chrome-headless", "sandbox"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T06:55:56Z"
updated_at: "2026-10-07T06:55:56Z"
---

# Verifikasi sesi ini: jalur SQLite3, lang() per grup, API test dari PowerShell

## 1. Menjalankan jalur test SQLite3 saat ekstensi sqlite3 TIDAK aktif di php.ini
Ekstensi `sqlite3` mati di php.ini host, tetapi DLL-nya bisa dimuat per-proses. Jalur SQLite3 tetap bisa diuji tanpa mengubah php.ini:

1. Salin `phpunit.dist.xml` ke config sementara, sisipkan blok `<env>` **di dalam `<php>`** (blok `<env>` menang atas `.env`):
   `database.tests.DBDriver=SQLite3`, `database.tests.database=:memory:`, `database.tests.DBPrefix=db_` (+ hostname/username/password/port).
2. Jalankan `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit -c phpunit.sqlite.xml`.
3. Pindahkan juga path `<logging>` di config sementara agar laporan tidak menimpa `build/logs/*` milik jalur MySQLi. Hapus config sementara setelah selesai (jangan di-commit).

Bukti: 142 test / 459 assertion — jumlahnya identik dengan jalur MySQLi.

## 2. `lang()` memuat berkas HANYA bila diminta dengan grup
`lang('Audit')` → mengembalikan string `'Audit'` apa adanya (tanpa titik, CI4 melewati pemuatan berkas), bukan array isi berkas. Untuk membaca isi berkas label, minta grupnya: `lang('Audit.receptions')` → array. Sama untuk `lang('Reception.log')`.

Akibatnya: test yang ingin membuktikan berkas label benar-benar dimuat harus memakai bentuk bergrup, dan assertion `assertIsArray()` di situ sekaligus mengunci bahwa berkasnya ada.

## 3. Memanggil API ber-sesi + CSRF dari PowerShell
- **Quoting JSON merusak payload**: `curl -d "{\"a\":1}"` lewat pwsh bikin body tidak valid → CI4 menjawab `HTTPException: Failed to parse JSON` (500). Solusi: tulis payload ke berkas lalu `curl.exe --data-binary "@file.json"`.
- Alur sesi: `GET /login` (ambil cookie `csrf_cookie_name` + `ci_session` ke cookie jar) → `POST /api/login` dengan header `X-CSRF-TOKEN` → `GET /api/receipts/{id}` dengan `-b jar.txt`.
- Baca token dari jar dengan regex `/csrf_cookie_name\s+(\w+)/`; jangan mengandalkan `$env:` (PowerShell tidak bisa menyetel env var bertitik).

## 4. Chrome headless tidak bisa dijalankan dari sesi ini
`chrome --headless` diblokir sandbox (crashpad `OpenProcess: Access is denied`), `node spawn` → `spawn EPERM`, dan eskalasi `danger-full-access` ditolak user. Pola yang dipakai: tulis skrip verifikasi CDP siap-pakai di `frontend/scripts/` (mis. `verify-audit-label.mjs`), lalu **serahkan ke user** untuk dijalankan di terminal biasa. Skrip: terima `<chromePath> <sessionCookie> <id>`, pasang cookie `ci_session` via `Network.setCookie` (HttpOnly), evaluasi DOM, cetak JSON, `exit 1` bila ada baris yang belum diterjemahkan.

## Catatan alat
- `tools.write`/`tools.edit` menuntut berkas **sudah dibaca** lebih dulu di sesi itu; kalau belum, gagal dengan "file has not been read".
- Edit berkas yang baru dibuat bisa gagal `ReplaceFileW EIO (Win32 1175)` → ulangi setelah jeda ~1,5 detik.
