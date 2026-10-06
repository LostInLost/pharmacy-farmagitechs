# Frontend (Astro + shadcn/ui)

Frontend Astro (SSR, adapter Node) untuk aplikasi farmasi Farmagitechs.
Login memakai session CodeIgniter 4 lewat `POST /api/login` (cookie
`ci_session` + header CSRF). Guard rute dijalankan server-side lewat
middleware berantai `sequence()`.

## Prasyarat

- Node.js >= 22.12
- pnpm 11.x (semua perintah di dokumen ini memakai pnpm)
- Backend CI4 berjalan, misalnya `php spark serve` di root repo (default
  `http://localhost:8080`)

## Menjalankan

Terminal 1 (root repo, backend):

```bash
php spark serve
```

Terminal 2 (folder ini, frontend):

```bash
pnpm install
pnpm dev
```

Buka `http://localhost:4321/login`.

## Perintah

| Perintah | Fungsi |
| --- | --- |
| `pnpm dev` | Dev server di port 4321 |
| `pnpm build` | Build produksi ke `dist/` (SSR) |
| `pnpm preview` | Preview hasil build |
| `node dist/server/entry.mjs` | Jalankan server SSR hasil build (env `HOST`/`PORT`) |
| `pnpm typecheck` | `astro check` |
| `pnpm lint` | ESLint |
| `pnpm format` | Prettier |

## Konfigurasi

Salin `.env.example` menjadi `.env` lalu sesuaikan bila perlu:

- `PUBLIC_API_BASE_URL` — base URL backend CI4 (default `http://localhost:8080`)
- `PUBLIC_POST_LOGIN_PATH` — tujuan setelah login (default `/dashboard`)
- `PUBLIC_APP_NAME` — nama yang tampil di UI (default `Farmagitechs`)

## Struktur

```
src/
  components/
    auth/         # island React: login-form, logout-button
    ui/           # komponen shadcn (button, input, label, card)
  layouts/
    base-layout.astro
  lib/
    auth.ts         # login/logout + klasifikasi error
    csrf.ts         # baca token CSRF dari cookie/header
    server-auth.ts  # cek sesi dari server (GET /api/me)
    utils.ts        # cn()
  middleware.ts   # guard berantai: session → guest → authenticated
  pages/
    index.astro   # redirect pintar: login ↔ dashboard sesuai sesi
    login.astro   # halaman tamu (island LoginForm)
    dashboard.astro
```

## Middleware (guard berantai)

`src/middleware.ts` memakai `sequence()` dari `astro:middleware`, jadi tiap
tahap punya satu tanggung jawab dan dipanggil berurutan:

1. **`session`** — memanggil `GET {API}/api/me` dengan header `Cookie`
   browser diteruskan apa adanya, lalu mengisi `locals.user` (tipe ada di
   `src/env.d.ts`). Aset statis (`/_astro/`, favicon) dilewati agar tidak
   membanjiri backend. Backend mati → `locals.user = null` (fail-closed).
2. **`guest`** — rute tamu (`/login`): pengguna yang sudah masuk dialihkan
   ke `PUBLIC_POST_LOGIN_PATH`.
3. **`authenticated`** — **deny-by-default**: semua rute di luar daftar
   publik (`/login`, favicon, aset) wajib login; tamu dialihkan ke `/login`.
   Halaman baru otomatis terlindungi tanpa daftar manual.

Konsekuensi: SSR wajib aktif (`output: "server"` + `adapter: node`), dan
middleware hanya berjalan lewat dev server atau `node dist/server/entry.mjs`
— bukan sebagai file statis. `GET /api/me` dipilih sebagai pengecekan
sesi karena metode aman lolos CSRF dan mengembalikan data user sekaligus.

## Alur login

1. Token CSRF diambil dari `GET {API}/api/csrf` (header `X-CSRF-TOKEN`,
   body `{ token }`). Cookie CSRF bersifat HttpOnly, jadi JavaScript tidak
   bisa membacanya.
2. `POST {API}/api/login` dengan `credentials: 'include'` + header
   `X-CSRF-TOKEN`.
3. Token berotasi setiap mutasi (`regenerate = true`). Bila respons 403
   dengan `error: 'csrf'`, token segar diambil dari header respons lalu
   request diulang sekali.
4. Respons dipetakan: 422 (wajib isi), 401 (kredensial salah), 403 tanpa
   `error: csrf` (sudah login — `GuestFilter`), 5xx/network (server).

## CORS

Backend sudah dikonfigurasi untuk origin frontend saja (lihat
`app/Config/Cors.php` dan `app/Config/Filters.php` di root repo):

- `allowedOrigins` = `http://localhost:4321` (origin dev Astro), tanpa
  wildcard; origin asing tidak pernah di-echo.
- `supportsCredentials = true` — cookie session ikut terkirim.
- `allowedHeaders` = `Content-Type`, `X-CSRF-TOKEN`;
  `exposedHeaders` = `X-CSRF-TOKEN` agar token rotasi terbaca JavaScript.
- Filter `cors` global dijalankan sebelum `csrf`, sehingga respons 403 CSRF
  tetap membawa header CORS (jalur retry token).
- Rute `OPTIONS api/(:any)` disediakan untuk preflight; rute `GET api/csrf`
  untuk bootstrap token.

Frontend (`localhost:4321`) dan backend (`localhost:8080`) berbagi host
`localhost`, jadi keduanya same-site dan cookie `SameSite=Lax` tetap
terkirim. Saat deploy, tambahkan origin frontend produksi ke
`allowedOrigins` (jangan pakai wildcard).

## Catatan

- Cookie session `ci_session` maupun cookie CSRF `csrf_cookie_name`
  keduanya HttpOnly; token CSRF selalu diambil lewat header respons.
- Jangan membuat `package-lock.json`/`yarn.lock` di folder ini; paket
  dikelola pnpm (`pnpm-lock.yaml`).
