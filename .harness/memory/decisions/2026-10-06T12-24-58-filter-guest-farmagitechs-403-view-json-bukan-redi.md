---
title: "Filter guest farmagitechs: 403 view/JSON, bukan redirect"
type: decision
summary: "GuestFilter menolak login saat sesi aktif: web 403 + view \"Sudah Masuk\" (bukan redirect), API 403 JSON + X-CSRF-TOKEN segar; Postman logout dulu sebelum ganti akun; temuan: Security::removeTokenInRequest merusak body JSON array di test"
tags: ["farmasi", "auth", "filter", "guest", "ci4", "decision", "csrf", "postman"]
source: "commit 5bbea49; verifikasi PHPUnit + Newman + curl"
confidence: high
scope: project
created_at: "2026-10-06T12:24:58Z"
updated_at: "2026-10-06T12:24:58Z"
---

# Filter `guest` farmagitechs (commit 5bbea49)

## Keputusan
- `App\Filters\GuestFilter` = kebalikan `AuthFilter`: menolak request yang sudah login
  (`session()->get('user_id') !== null`) pada rute tamu.
- Web `GET /login` → **403 + view** `auth/already_authenticated.php` (tautan ke `/receptions`
  + tombol keluar), **bukan redirect**. Redirect 302 dari halaman login menyulitkan pengguna
  memahami kenapa form tidak muncul; ini permintaan eksplisit user.
- API `POST /api/login` → **403 JSON** `{"message": "Sudah masuk."}` + header `X-CSRF-TOKEN`
  berisi token terbaru. Header segar wajib: token sudah berotasi di filter global `csrf`,
  tanpa header itu klien ditolak akan memakai token basi dan tertahan `403 csrf` (dibuktikan
  lewat Newman: 3.12/3.14 gagal "csrf" sebelum perbaikan).
- Cek inline `session()->get('user_id')` di `Web\AuthPages::login()` dihapus — filter jadi
  satu-satunya sumber kebenaran.
- Bahasa: `Auth.already_authenticated` (API) + blok `Auth.already.{title,message,back,logout}`
  (view), id/en.

## Konsekuensi
- Login saat sesi aktif kini 403. Skenario Postman 3.11/3.14 baru: `POST /api/logout`
  sebelum ganti akun (petugas → supervisor → petugas).
- Alias didaftarkan di `app/Config/Filters.php`; tidak masuk `$globals` (per-rute saja).

## Temuan teknis (jebakan)
`CodeIgniter\Security\Security::removeTokenInRequest()` menulis ulang body JSON array
(`[]`) menjadi form-encoded saat verifikasi CSRF → `getJSON()` di controller melempar
"Failed to parse JSON string". Di test, kirim payload objek (mis. `['username' => '', ...]`),
jangan `[]`.

## Verifikasi
74 test PHPUnit (5 baru di `tests/Feature/GuestFilterTest.php`) + Newman 30 request /
74 assertion hijau. Curl manual: anon 200, login-ulang 403 view/JSON, header token segar
konsisten dengan cookie `csrf_cookie_name`.