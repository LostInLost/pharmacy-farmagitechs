---
title: "CSRF global untuk web + /api/*, filter kustom CsrfFilter, token segar di header response"
type: decision
summary: "CSRF kini global termasuk `/api/*`; `CsrfFilter` kustom mengembalikan 403 JSON + token segar untuk API dan redirect untuk web; semua response API membawa header `X-CSRF-TOKEN` terbaru; logout web jadi POST. Terverifikasi 68 test + 26 request Newman."
tags: ["csrf", "ci4", "security", "api", "filter", "decision"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T09:01:14Z"
updated_at: "2026-10-06T09:01:14Z"
---

## Keputusan
CSRF CodeIgniter 4 diaktifkan **global untuk semua path termasuk `/api/*`**, tidak lagi dikecualikan. Kegagalan token dijawab berbeda per jenis klien.

## Perubahan konkret
- `app/Config/Filters.php`: alias `'csrf' => App\Filters\CsrfFilter::class`; `$globals['before']` memakai `'csrf'` tanpa `except`.
- `app/Config/Security.php`: `$redirect = false` (unconditional) — penanganan diserahkan ke filter kustom.
- `app/Filters/CsrfFilter.php` (baru): menangkap `SecurityException`; untuk path `/api/*` mengembalikan `403` JSON `{"message":..., "error":"csrf"}` **beserta header `X-CSRF-TOKEN` berisi token segar**; untuk web `redirect()->back()->with('error', ...)`.
- `app/Controllers/Api/BaseApiController.php`: helper `withFreshCsrf(ResponseInterface)` menyetel header `X-CSRF-TOKEN` = `csrf_hash()`; dipakai di semua return controller API (Auth, Reception, Stock) termasuk `respondError()`.
- `app/Config/Routes.php`: `logout` web `GET` → `POST`; `app/Views/layout.php` memakai form + `csrf_field()` dan menambah `<meta name="csrf-token">`.
- `public/assets/reception-form.js`: kirim header `X-CSRF-TOKEN` dari meta, simpan token segar dari response, retry-once bila `403` + `error === 'csrf'`.

## Alasan memilih filter kustom (bukan exception handler)
Filter bawaan `CodeIgniter\Filters\CSRF` hanya melempar ulang `SecurityException` saat `redirect=false`, sehingga responsnya bergantung pada exception handler dan sulit membedakan `/api/*`. Filter kustom memberi kontrol penuh atas bentuk respons **dan** menyertakan token segar agar klien bisa langsung retry.

## Konsekuensi yang disengaja
- Filter global `csrf` berjalan **sebelum** filter route `auth` → `POST` tanpa token **dan** tanpa sesi dijawab `403`, bukan `401`. Fail-closed.
- Klien API wajib `GET /login` dulu untuk memperoleh cookie `csrf_cookie_name` (chicken-and-egg login).
- Token berotasi tiap mutasi berhasil (`regenerate=true`) → klien wajib memakai token terakhir dari header response.

## Verifikasi
- `tests/Feature/ApiCsrfTest.php` (8 test): 403 tanpa token, rotasi, tolak replay, token segar diterima, 401 dengan token valid tanpa sesi, logout POST-only, mutasi nyata 201.
- Suite penuh: 68 test / 159 assertion hijau.
- Newman: 26 request / 66 assertion hijau (folder 0 baru = bootstrap CSRF; folder 4 diperluas dengan skenario 403).

## Dokumentasi
`docs/security.md` (baru) memuat tabel konfigurasi, alur token, bentuk kegagalan, urutan filter, konsekuensi klien, dan batas yang belum ditangani (cookie belum `Secure` karena dev HTTP).