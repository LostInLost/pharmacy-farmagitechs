---
title: "Sandbox DSH: is_writable() false → CI4 session 500; jalankan server unconfined"
type: learning
summary: "Sandbox DSH membuat `is_writable()` false untuk semua path (padahal tulis berhasil), sehingga CI4 menolak start session dan semua halaman 500. Solusinya jalankan server PHP unconfined (danger-full-access). Ini bukan masalah izin nyata maupun konfigurasi aplikasi."
tags: ["sandbox", "ci4", "session", "php-dev-server", "troubleshooting", "windows"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T09:01:14Z"
updated_at: "2026-10-06T09:01:14Z"
---

## Gejala
Di dalam sandbox DSH (mode workspace-write), server PHP dev (`php -S`) mengembalikan **500** untuk semua halaman dengan:

```
CodeIgniter\Session\Exceptions\SessionException: Session: Configured save path
"...\writable\session" is not writable by the PHP process.
```

## Akar masalah
`FileHandler::open()` (vendor/.../Session/Handlers/FileHandler.php:101) memanggil `is_writable($path)`. Di dalam sandbox, `is_writable()` mengembalikan **false untuk semua path** — termasuk `C:\Windows\Temp` dan `writable/session` — padahal `file_put_contents()` ke path yang sama **berhasil**. Jadi ini false negative dari `is_writable()`, bukan masalah izin nyata. Diagnosa pembanding:

```
php scripts/_probe-writable.php
# workspace paths: exists=true writable=false put=true   <-- tidak konsisten
```

## Solusi
Jalankan server **unconfined** (`sandbox_permissions: danger-full-access` saat start). Setelah itu `GET /login` → 200 dan Newman hijau. Ini konsisten dengan praktik proyek: server HTTP harus unconfined untuk uji live.

## Catatan terkait
- `mysqli` sebenarnya sudah aktif di `C:\Users\php8.4\php.ini` (`extension=mysqli`, `extension_dir="ext"`). Kegagalan "required PHP extension mysqli is not loaded" sebelumnya berasal dari proses server lama/zombie yang masih memegang port 8080, bukan dari php.ini. Cek dulu: `Get-NetTCPConnection -LocalPort 8080 -State Listen`.
- Env var OS (`$env:database.default.database`) **tidak** menimpa `.env` CI4; `.env` menang. Untuk mengarahkan ke DB lain, edit `.env` (backup + restore, verifikasi via `Get-FileHash`) — bukan lewat env var.
