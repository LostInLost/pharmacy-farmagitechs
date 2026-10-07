---
title: "Halaman Receptions + Laporan Stok (mirror CI4) dan pembersihan demo dashboard"
type: decision
summary: "Halaman Receptions + Laporan Stok di frontend Astro, demo dashboard-01 dibersihkan. Kunci: can_update hanya di index/show (bukan respons tulis), filter stok 'Semua status' menampilkan semua obat, verifikasi via Chrome CDP menemukan 2 bug nyata."
tags: ["frontend", "astro", "receptions", "stocks", "can-update", "cdp-verification", "farmagitechs", "csrf"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T03:25:55Z"
updated_at: "2026-10-07T03:25:55Z"
---

## Keputusan
Frontend Astro kini punya halaman Receptions (list/baru/edit) + Laporan Stok yang memakai API CI4 yang sudah ada, dan seluruh boilerplate demo `dashboard-01` dibersihkan. Dashboard jadi ringkasan nyata.

## Struktur
- `features/receptions/`: `schemas.ts`, `api.ts`, `components/{receptions-table,reception-form,audit-log-table}.tsx`, `index.ts`
- `features/stocks/`: `schemas.ts`, `api.ts`, `components/stock-report.tsx`, `index.ts`
- `components/app-shell.tsx`: chrome bersama (TooltipProvider → SidebarProvider → AppSidebar → SiteHeader → Toaster) + logout; `components/feedback.tsx`: alert inline `role="alert"` (padanan `lib/ui.js` CI4)
- `foundations/api/request.ts`: `requestJson(method, path, body?)` + `ApiResult<T>`/`ApiFailure`/`networkFailure`/`unrecognizedFailure`/`readFailure`
- `foundations/format.ts`: `formatDateTime`/`formatDate`/`toDatetimeLocal`/`todayIso` — **tanpa `new Date()`** agar nilai Asia/Jakarta tidak bergeser mengikuti zona browser
- Halaman: `/receptions`, `/receptions/new`, `/receptions/[id]/edit` (segmen non-numerik → redirect), `/stocks`

## Dihapus (demo dashboard-01)
`data/dashboard.json`, `features/dashboard/schemas.ts`, `dashboard-shell`, `section-cards`, `chart-area-interactive`, `data-table`, `nav-documents`, `nav-secondary`, dan primitif `ui/*` yang menganggur: `chart, tabs, drawer, checkbox, toggle, toggle-group, breadcrumb`. Dependency `recharts`, `@dnd-kit/*`, `@tanstack/react-table` dilepas dari `package.json` + lockfile.

## Kontrak API yang WAJIB diingat
- **`can_update` hanya ada pada `GET /api/receipts` (index) dan `GET /api/receipts/:id` (show)**. `POST`/`PUT` mengembalikan `ReceptionService::detail()` yang belum tersentuh `ReceptionPolicy`, jadi respons tulis **tanpa** `can_update`. Skema header dipisah dari `receptionRowSchema` supaya tidak menolak respons sah.
- `GET` lolos CsrfFilter (safe method) → tanpa token. Mutasi butuh token + retry sekali pada 403 `error:"csrf"`.
- 422 mengembalikan `errors: string[]` (aturan server). Validasi bentuk tetap milik Zod; aturan yang butuh data server (reference terpakai, konflik batch, urutan tanggal) **tidak** disalin ke klien.
- Pesan validasi backend mengikuti `Accept-Language` — Chrome mengirim `en` sehingga muncul bahasa Inggris. Ini perilaku CI4 sendiri, bukan bug frontend.

## Perilaku CI4 yang harus ditiru
Filter status stok: **"Semua status" menampilkan semua obat, termasuk yang belum punya batch sama sekali** (`matchesStatus` → `true`). Hanya "tersedia"/"kedaluwarsa" yang menyaring berdasarkan batch. Filter status murni sisi klien; angka selalu dari server.

## Verifikasi (bukan asumsi)
Skrip `frontend/scripts/verify-*.mjs` menjalankan Chrome headless lewat CDP: memasang cookie sesi CI4 (HttpOnly → hanya bisa dari CDP), lalu memeriksa DOM yang benar-benar tampil. Ini **menemukan dua bug nyata** yang tidak terlihat dari pembacaan kode: (1) skema tulis menolak respons tanpa `can_update` sehingga muncul "Respons server tidak dikenali" padahal data tersimpan; (2) filter "Semua status" menyembunyikan 14 dari 22 obat.

Hasil akhir: typecheck 65 file 0 error, lint 0 error, build SSR OK, semua skrip verifikasi PASS.

## Commit
`56563a3` requestJson → `347c10a` features → `ce90563` AppShell+halaman+bersih demo → `3413f39` dua perbaikan bug → `54e3539` skrip verifikasi.

## Catatan environment
- `pnpm install` penuh **gagal** di sandbox (`ERR_PNPM_ABORTED_REMOVE_MODULES_DIR_NO_TTY`, lalu pnpm mau purge `node_modules`). Aman: `pnpm install --lockfile-only --offline --store-dir F:/.pnpm-store` — lockfile ikut berubah tanpa menyentuh `node_modules`.
- `pnpm build` butuh akses penuh sekali (esbuild `spawn EPERM`).
- Chrome headless butuh akses penuh; endpoint `/json/version` (level browser) **tidak** punya domain `Network`/`Page`/`Runtime` — harus `/json/list` lalu pilih target `type: "page"`.
- `Runtime.evaluate` mati saat navigasi; pantau pengalihan dari sisi Node, bukan dalam satu evaluate panjang.