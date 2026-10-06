---
title: "Middleware chaining Astro: session → guest → authenticated (SSR)"
type: decision
summary: "Guard rute Astro kini middleware chaining sequence(session, guest, authenticated): session isi locals.user dari GET /api/me (cookie diteruskan, aset dilewati), guest tolak /login saat sudah masuk, authenticated deny-by-default untuk rute lain. SSR aktif (output server + @astrojs/node), backend dapat /api/me + username di sesi. Verifikasi: 99 test backend hijau, typecheck/lint/build bersih, 7 skenario live lolos. Catatan: node_modules hardlink ke F:\.pnpm-store (butuh .npmrc) dan store root di-gitignore."
tags: ["frontend", "astro", "middleware", "ssr", "auth", "guard", "sequence", "ci4", "farmagitechs", "decision"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T14:43:08Z"
updated_at: "2026-10-06T14:43:08Z"
---

# Middleware chaining Astro: session → guest → authenticated (commit 67b8e46)

## Keputusan
Guard rute frontend berjalan **server-side** lewat `sequence()` dari
`astro:middleware` (docs https://docs.astro.build/en/guides/middleware),
tiga tahap dengan satu tanggung jawab masing-masing:

1. `session` — panggil `GET {API}/api/me` dengan header `Cookie` browser
   diteruskan apa adanya, isi `context.locals.user`. Aset statis
   (`/_astro/`, `/_image`, `/@`, `/node_modules/`, favicon) dilewati agar
   HMR dev tidak membanjiri backend. Backend mati → `null` (fail-closed).
2. `guest` — `/login` menolak pengguna yang sudah masuk → redirect
   `PUBLIC_POST_LOGIN_PATH`.
3. `authenticated` — **deny-by-default**: apa pun di luar daftar publik
   (`/`, `/login`, favicon, aset) wajib login → redirect `/login`.
   Halaman baru otomatis terlindungi tanpa daftar manual.

`/` sengaja publik; `index.astro` memilih arah redirect dari
`Astro.locals.user` (tanpa fetch ganda).

## Perubahan pendukung
- **SSR wajib**: `output: "server"` + `@astrojs/node@^11.1.6`
  (`mode: "standalone"`). Middleware tidak jalan pada output statis.
  Jalankan hasil build: `node dist/server/entry.mjs` (env `HOST`/`PORT`).
- **Backend**: `GET /api/me` di grup filter `auth` (method aman → lolos
  CSRF tanpa token) mengembalikan `{ user: {id,name,username,role} }`;
  `AuthService::login()` kini menyimpan `username` di sesi (fallback `''`
  untuk sesi lama). Test: `tests/Feature/AuthMeTest.php` (3 test).
- **Frontend**: `src/lib/server-auth.ts` (`getSessionUser`, timeout 3 s),
  tipe `App.Locals` di `src/env.d.ts`.

## Verifikasi
- `pnpm typecheck` 0 error (48 file), `pnpm lint` exit 0, `pnpm build` SSR
  sukses, suite backend **99 test / 274 assertion hijau**.
- 7 skenario diuji live lewat server hasil build dengan sesi asli
  (login `petugas`): anon `/dashboard` `/` `/rute-baru` → 302 `/login`;
  anon `/login` & `/favicon.svg` → 200; login `/dashboard` → 200;
  login `/login` `/` → 302 `/dashboard`.
- Dev server user (:4321, IPv6 `[::1]`) juga sudah memverifikasi perilaku
  yang sama (termasuk `/@vite/client` → 200).

## Catatan lingkungan (penting untuk tab lain)
- `frontend/node_modules` ter-hardlink ke store pnpm **di luar repo**
  (`F:\.pnpm-store`). Akibatnya `pnpm add` gagal `UNEXPECTED_STORE` bila
  resolusi default menunjuk store lain → dipasang `frontend/.npmrc`
  (`store-dir=F:/.pnpm-store`, di-gitignore) agar perintah pnpm konsisten.
- `.pnpm-store/` di root repo (≈380 MB) adalah artefak cache → masuk
  `.gitignore` root.
- Menghapus/menimpa file hardlink dari store itu **ditolak sandbox
  workspace-write** (`pnpm add @astrojs/node` perlu sekali
  danger-full-access). `pnpm build` juga perlu itu (esbuild `spawn EPERM`).
- `pnpm dev` yang di-spawn dari shell harness gagal
  `Failed to create the dev server app: module is not defined`; dev server
  yang dijalankan user sendiri normal — gejala lingkungan spawn, bukan kode.
