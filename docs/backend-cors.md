# CORS backend untuk frontend Astro

> Bagian dari [README](../README.md). Lihat juga [Keamanan](security.md#proteksi-csrf) dan [frontend/README.md](../frontend/README.md) (sisi klien, halaman, verifikasi browser).

Frontend Astro berjalan di origin berbeda saat pengembangan
(`http://localhost:4321`) dari backend CI4 (`http://localhost:8080`), jadi
API perlu mengizinkan request lintas origin sekaligus tetap mengirim cookie
session (`credentials: 'include'`).

## Konfigurasi

### `app/Config/Cors.php`

| Item | Nilai | Alasan |
| --- | --- | --- |
| `allowedOrigins` | `['http://localhost:4321']` | Origin frontend saja; tanpa wildcard (wajib begitu `supportsCredentials = true`). Nilai `Allow-Origin` tidak pernah diambil dari request, jadi origin asing tidak ter-echo. |
| `supportsCredentials` | `true` | Cookie `ci_session` + `csrf_cookie_name` ikut terkirim. |
| `allowedHeaders` | `Content-Type`, `X-CSRF-TOKEN` | Header yang dikirim frontend; keduanya tidak safelisted sehingga butuh preflight. |
| `exposedHeaders` | `X-CSRF-TOKEN` | Token CSRF berotasi; frontend harus membaca nilai terbaru dari header respons. |
| `allowedMethods` | `GET`, `POST`, `PUT`, `OPTIONS` | Metode yang dipakai API saat ini. |
| `maxAge` | `7200` | Cache preflight 2 jam. |

### `app/Config/Filters.php`

Filter `cors` dipasang di `$globals['before']` **sebelum** `csrf`, dan di
`$globals['after']`:

- Urutan penting: `CsrfFilter` menolak request tanpa token dengan 403 JSON.
  Bila CORS belum berjalan, respons itu tidak punya header CORS, browser
  memblokirnya, dan frontend tidak bisa membaca header `X-CSRF-TOKEN` segar
  untuk mengulang request.
- Pasangan `after` menjaga header CORS tetap ada bila controller
  mengembalikan objek response baru (mis. redirect), bukan response bersama.

### `app/Config/Routes.php`

- `OPTIONS api/(:any)` → 204 + header `Allow`. Filter hanya berjalan bila
  rutenya terdaftar; tanpa rute ini preflight dijawab 404 dan filter `cors`
  tidak pernah jalan. Filter `cors` menangani preflight (204) sebelum
  closure dipanggil.
- `GET api/csrf` → 200, body `{ token }`, header `X-CSRF-TOKEN` +
  `Cache-Control: no-store`. Dipakai frontend untuk bootstrap token awal.

## Kenapa perlu endpoint `/api/csrf`

Cookie `csrf_cookie_name` di-set lewat `Security::saveHashInCookie()` dengan
default `Config\Cookie` (`$httponly = true`), sehingga `document.cookie` di
browser tidak bisa membacanya. Frontend lintas origin juga tidak punya
halaman HTML CI4 untuk membaca meta tag, jadi token awal diambil lewat
endpoint JSON kecil ini.

## Cookie lintas port

`localhost:4321` (frontend) dan `localhost:8080` (backend) berbagi host
`localhost`; port **tidak** menentukan "site". Keduanya same-site, jadi
cookie `SameSite=Lax` tetap dikirim pada fetch lintas origin dengan
`credentials: 'include'`. Karena itu tidak perlu mengubah `SameSite` menjadi
`None`/`secure`.

Saat deploy ke domain berbeda, ubah `allowedOrigins` ke origin frontend
produksi **dan** set `Config\Cookie::$samesite = 'None'` + `$secure = true`
(keduanya wajib berpasangan).

## Verifikasi

Langkah verifikasi end-to-end lewat browser (skrip CDP) ada di [`frontend/README.md`](../frontend/README.md#verifikasi-browser-opsional).

- Otomatis: `tests/Feature/CorsTest.php` (preflight 204 + header, origin
  asing tidak ter-echo, header CORS pada 200/422/403, endpoint bootstrap,
  OPTIONS biasa).
- Live (server `php spark serve`):

```bash
curl -i -X OPTIONS http://localhost:8080/api/login \
  -H 'Origin: http://localhost:4321' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type, x-csrf-token'

curl -i http://localhost:8080/api/csrf -H 'Origin: http://localhost:4321'
```

Hasil yang diharapkan: `204` dengan `Access-Control-Allow-Origin:
http://localhost:4321`, `Access-Control-Allow-Credentials: true`, dan
`X-CSRF-TOKEN` tersedia pada respons `/api/csrf`.
