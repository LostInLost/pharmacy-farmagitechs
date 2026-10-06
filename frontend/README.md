# Frontend (Astro + shadcn/ui)

Frontend Astro untuk aplikasi farmasi Farmagitechs. Login memakai session
CodeIgniter 4 lewat `POST /api/login` (cookie `ci_session` + header CSRF).

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
| `pnpm build` | Build produksi ke `dist/` |
| `pnpm preview` | Preview hasil build |
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
    auth.ts       # login/logout + klasifikasi error
    csrf.ts       # baca token CSRF dari cookie/header
    utils.ts      # cn()
  pages/
    index.astro   # redirect ke /login
    login.astro   # halaman login (island LoginForm)
    dashboard.astro
```

## Alur login

1. Token CSRF dibaca dari cookie `csrf_cookie_name`; bila belum ada,
   fallback ke `GET {API}/login` lalu ambil header `X-CSRF-TOKEN`.
2. `POST {API}/api/login` dengan `credentials: 'include'` + header
   `X-CSRF-TOKEN`.
3. Token berotasi setiap mutasi (`regenerate = true`). Bila respons 403
   dengan `error: 'csrf'`, token segar diambil dari header respons lalu
   request diulang sekali.
4. Respons dipetakan: 422 (wajib isi), 401 (kredensial salah), 403 tanpa
   `error: csrf` (sudah login — `GuestFilter`), 5xx/network (server).

## Catatan

- Cookie session `ci_session` bersifat HttpOnly; hanya `csrf_cookie_name`
  yang bisa dibaca JavaScript.
- Bila backend memblokir cookie lintas origin, jalankan frontend dan backend
  pada origin yang sama (proxy) atau aktifkan CORS + `supportsCredentials`
  di `app/Config/Cors.php`.
- Jangan membuat `package-lock.json`/`yarn.lock` di folder ini; paket
  dikelola pnpm (`pnpm-lock.yaml`).
