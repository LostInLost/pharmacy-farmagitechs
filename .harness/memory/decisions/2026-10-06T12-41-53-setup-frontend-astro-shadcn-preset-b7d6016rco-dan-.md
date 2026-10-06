---
title: "Setup frontend Astro + shadcn preset b7D6016rcO dan login CI4"
type: decision
summary: "Frontend Astro pnpm-only di frontend/ (preset b7D6016rcO, base radix); login CI4 session+CSRF dengan retry; dashboard-01 diadaptasi dari Next ke Astro; E2E belum diuji karena backend/Laragon mati"
tags: ["frontend", "astro", "shadcn", "pnpm", "auth", "csrf", "dashboard", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T12:41:53Z"
updated_at: "2026-10-06T12:41:53Z"
---

# Frontend Astro farmagitechs (commit 7bc21b5)

## Keputusan
- `frontend/` = project Astro standalone, **pnpm-only** (pnpm 11.7.0, Node 22).
  Root `.gitignore` + `frontend/.gitignore` meng-ignore artifact
  (node_modules, dist, .astro, .env*) tapi **bukan** seluruh folder frontend;
  `frontend/pnpm-lock.yaml` di-track, `.env.example` dikecualikan dari `.env*`.
- Instalasi resmi: `pnpm dlx shadcn@latest init --preset b7D6016rcO --base radix --template astro`
  (bukan `npx`, sesuai instruksi user). Warna ikut preset, bukan sampling landing page.
- Komponen: `pnpm dlx shadcn@latest add input label card` + block `dashboard-01`.
- Login = session CI4 asli: `POST {PUBLIC_API_BASE_URL}/api/login` dengan
  `credentials:'include'` + header `X-CSRF-TOKEN` dari cookie `csrf_cookie_name`;
  retry sekali saat 403 `error:'csrf'` memakai token dari header respons.
- Status backend dipetakan di UI: 422 wajib isi, 401 kredensial, 403 tanpa
  `error:csrf` = sudah login (GuestFilter), 5xx/network = server.
- Dashboard = block dashboard-01 (sidebar, section cards, chart, data table)
  sebagai satu island `DashboardShell` (`client:load`); logout via `POST /api/logout`
  + `sessionStorage` untuk info user non-otoritatif.

## Adaptasi Next → Astro (wajib diingat)
- `ui/sonner.tsx`: `next-themes` dibuang → baca kelas `dark` di `<html>`.
- `hooks/use-mobile.ts`: useState+useEffect diganti `useSyncExternalStore`
  (lint `react-hooks/set-state-in-effect` error di kode asli block).
- `chart-area-interactive.tsx`: setTimeRange dalam effect dihapus →
  `userRange ?? (isMobile ? '7d' : '90d')` (state diturunkan, bukan disinkronkan).
- `nav-user.tsx` dapat prop opsional `onLogout`; `app-sidebar.tsx` di-rebrand
  Farmagitechs (nav: Dashboard/Penerimaan/Stok).
- `src/app/dashboard/data.json` dipindah ke `src/data/dashboard.json`; folder `src/app` dihapus.
- Halaman: `/` redirect ke `/login` (Astro.redirect), `/login`, `/dashboard`.

## Verifikasi
- `pnpm typecheck` 0 error; `pnpm lint` exit 0; `pnpm build` sukses 3 halaman;
  preview `/login` HTTP 200 + form ter-render.
- **E2E login ke CI4 belum dijalankan**: port 8080 (spark serve) dan 3306 (MySQL
  Laragon) mati saat pengerjaan. Perlu test manual saat Laragon jalan.
- Sandbox DSH memblokir: `pnpm dlx` cache di luar workspace (atasi dengan
  `npm_config_cache_dir` ke workspace), `git clone` template shadcn & `pnpm add`
  oleh CLI, serta esbuild service saat `pnpm build` → butuh danger-full-access.
