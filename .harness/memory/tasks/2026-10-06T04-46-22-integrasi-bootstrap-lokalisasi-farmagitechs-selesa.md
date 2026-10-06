---
title: "Integrasi Bootstrap + lokalisasi farmagitechs selesai"
type: task
summary: "UI Bootstrap 5.3.8 + lokalisasi id/en selesai (belum commit); 44 test & 23 request Newman lolos, bug redirect JS diperbaiki"
tags: ["farmasi", "ui", "bootstrap", "lokalisasi", "codeigniter", "selesai"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T04:46:22Z"
updated_at: "2026-10-06T04:46:22Z"
---

# Integrasi Bootstrap + Lokalisasi UI farmagitechs

Selesai di working tree (belum di-commit). Semua verifikasi lolos: PHPUnit 44 test / 116 assertion, Newman 23 request / 51 assertion, lint 24 file PHP + JS, smoke test HTTP per halaman.

## Keputusan implementasi
- Bootstrap **5.3.8** via CDN jsDelivr dengan SRI (hash diverifikasi dari dua sumber: docs resmi getbootstrap.com + hasil hash file asli yang diunduh). CSS di `<head>`, `bootstrap.bundle.min.js` sebelum `</body>`. Tanpa jQuery, tanpa tooling build.
- `public/assets/app.css` dipangkas jadi tema tipis: hanya `details/summary`, `details pre` (pre-wrap), dan fallback tabel dasar bila CDN gagal. Semua class lama (`.alert error/ok`, `.muted`, `.grid`, `.row-actions`, `.badge`, `.num`, `.card`) dihapus; view memakai class Bootstrap.
- Layout menyediakan section `scripts` agar halaman form bisa menyuntik `window.RECEPTION_DATA` sebelum `reception-form.js`.
- `layout.php` memakai `service('request')->getLocale()` untuk atribut `<html lang>`.

## Lokalisasi
- `app/Config/App.php`: `defaultLocale='id'`, `negotiateLocale=true`, `supportedLocales=['id','en']`.
- File bahasa baru: `app/Language/{id,en}/{App,Auth,Reception,Stock}.php` (8 file). Key identik antar locale.
- Placeholder pakai sintaks ICU `{0}` (ekstensi `intl` tersedia di PHP Laragon, jadi substitusi jalan). Nilai dinamis yang disubstitusi TIDAK di-reparse sebagai format, jadi karakter `{`/`}` pada pesan exception aman.
- Fallback CI4: locale → `en` → key mentah (terverifikasi di `Language::getLine`).
- Pesan user-facing di controllers/filter/service/validator semuanya lewat `lang()`. `BaseApiController::notImplemented()` pakai `App.api.not_implemented`.

## Perbaikan bug yang ikut terbawa
- `public/assets/reception-form.js` lama menyematkan `<?= site_url('receptions') ?>` di file JS statis → tag PHP tidak pernah dirender sehingga redirect setelah simpan rusak. Sekarang URL dikirim via `RECEPTION_DATA.redirectUrl`.
- JS lama menyuntik `batch_no` dan `medicine.name` ke `innerHTML` tanpa escaping → berpotensi merusak markup/XSS. Sekarang baris dibuat via `createElement` + `escapeHtml()` untuk nama obat dan pesan error.
- `app/Language/id/Stock.php` sempat punya key duplikat `available`/`expired` (kolom vs badge) → dipecah jadi `status_available`/`status_expired`.

## Catatan lingkungan pengujian
- `app.baseURL = http://localhost:8080/`; server dev proyek ini sudah berjalan di port 8080. Jangan uji di port lain: redirect CI4 akan memakai host baseURL sehingga cookie CSRF lintas origin dan login POST gagal 403.
- Newman meninggalkan data di DB dev (receptions PB-001, PB-SUP-*) yang membuat assertion stok baseline gagal pada run berikutnya. Setelah menjalankan Newman, hapus baris `receptions`/`reception_items`/`reception_logs` agar baseline pulih.
- Untuk smoke test HTTP di sandbox ini, pakai `curl` PHP (bukan `Invoke-WebRequest`/`curl.exe`): schannel gagal `SEC_E_NO_CREDENTIALS`.