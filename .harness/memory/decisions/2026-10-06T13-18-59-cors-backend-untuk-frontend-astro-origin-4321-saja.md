---
title: "CORS backend untuk frontend Astro (origin :4321 saja) + bootstrap CSRF /api/csrf"
type: decision
summary: "CORS CI4: allowedOrigins hanya http://localhost:4321 (tanpa wildcard), credentials true; filter cors global sebelum csrf agar 403 CSRF tetap bawa header CORS; rute OPTIONS api/(:any) + GET api/csrf untuk bootstrap token (cookie CSRF HttpOnly); 6 test baru, suite 90 hijau, verifikasi curl live OK"
tags: ["cors", "ci4", "csrf", "frontend", "astro", "cookie", "httponly", "decision"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T13:18:59Z"
updated_at: "2026-10-06T13:18:59Z"
---

# CORS frontend Astro farmagitechs (commit 1ce28af)

## Keputusan
- `app/Config/Cors.php`: `allowedOrigins = ['http://localhost:4321']` **saja**
  (origin dev Astro; permintaan user: origin frontend saja). Tanpa wildcard —
  wajib begitu `supportsCredentials = true`. Karena originCount === 1, CI4
  mengirim origin konstan (tidak pernah echo dari request), jadi origin asing
  tidak mungkin lolos. `allowedHeaders = ['Content-Type','X-CSRF-TOKEN']`,
  `exposedHeaders = ['X-CSRF-TOKEN']`, `allowedMethods = ['GET','POST','PUT','OPTIONS']`.
- `app/Config/Filters.php`: `cors` di `$globals['before']` **sebelum** `csrf`
  dan di `$globals['after']`. Alasan: `CsrfFilter` menolak tanpa token dengan
  403 JSON; tanpa CORS lebih dulu, respons itu tanpa header CORS → browser
  blokir → frontend tidak bisa baca token segar untuk retry.
- `app/Config/Routes.php`: `OPTIONS api/(:any)` → 204 + `Allow` (filter hanya
  jalan bila rute terdaftar; tanpa ini preflight 404) dan `GET api/csrf` →
  `{token}` + header `X-CSRF-TOKEN` + `no-store`.
- Frontend: `bootstrapCsrfToken()` sekarang fetch `GET {API}/api/csrf`
  (header/body), TIDAK lagi baca cookie.

## Temuan penting
- Cookie `csrf_cookie_name` **HttpOnly** (warisan `Config\Cookie::$httponly = true`
  yang dipakai `Security::saveHashInCookie()`), jadi `document.cookie` tidak bisa
  membacanya → endpoint `/api/csrf` diperlukan untuk bootstrap lintas origin.
- `localhost:4321` dan `localhost:8080` same-site (port tidak menentukan site),
  jadi cookie `SameSite=Lax` tetap terkirim dengan `credentials:'include'` —
  tidak perlu `SameSite=None`. Untuk deploy domain berbeda: update
  `allowedOrigins` + set `Cookie::$samesite='None'` & `$secure=true`.

## Verifikasi
- `tests/Feature/CorsTest.php`: 6 test / 21 assertion (preflight 204 + header,
  origin asing tidak ter-echo, header CORS pada 200/422/403, bootstrap token,
  OPTIONS biasa). Suite penuh 90 test / 232 assertion hijau.
- Live curl (spark serve, unconfined): preflight `OPTIONS /api/login` → 204 +
  `Access-Control-Allow-Origin: http://localhost:4321` + credentials true;
  `/api/csrf` → 200 + token header + `Set-Cookie ... HttpOnly; SameSite=Lax`;
  403 CSRF tetap membawa header CORS + token segar.
- Login penuh E2E masih belum diuji: MySQL/Laragon mati (port 3306 & 8080 kosong).
- `pnpm typecheck` 0 error, `pnpm lint` exit 0, `pnpm build` sukses.
