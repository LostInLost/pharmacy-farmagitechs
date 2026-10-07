---
title: "Sidebar gaya sidebar-07: port manual, grup Utama/Operasional"
type: decision
summary: "Sidebar di-port ke gaya sidebar-07 (collapsible=icon, SidebarRail, grup Utama/Operasional) via port manual, bukan `shadcn add` — karena 3 file blok sudah dikustomisasi (auth/tema). Jarak antar item di-override `gap-1` di sisi pemakai, bukan di ui/. Verifikasi wajib `pnpm build`; build di sandbox sempit gagal EPERM dari esbuild."
tags: ["frontend", "astro", "shadcn", "sidebar", "sidebar-07", "app-sidebar", "nav-main", "cn", "tailwind-merge", "farmagitechs", "build"]
source: "refactor sidebar gaya sidebar-07, sesi 2026-10-07"
confidence: high
scope: project
created_at: "2026-10-07T05:14:59Z"
updated_at: "2026-10-07T05:14:59Z"
---

## Keputusan

Sidebar frontend di-port ke gaya blok shadcn `sidebar-07` (collapse-to-icon + rail + menu bergrup), **tanpa** menjalankan `npx shadcn@latest add sidebar-07`.

Alasan tidak pakai CLI: 3 dari 6 file blok itu (`app-sidebar.tsx`, `nav-main.tsx`, `nav-user.tsx`) sudah ada di proyek dengan kustomisasi nyata (AuthUser dari `GET /api/me`, `onLogout`, submenu Tema via `@/foundations/theme`, dialog "Profil Saya"). `shadcn add` akan menimpanya dan menambah 2 file sampah (`nav-projects.tsx`, `team-switcher.tsx`). Blok sidebar-07 sendiri tidak punya SidebarGroupLabel (itu ada di sidebar-08); grup berlabel diambil dari `dashboard-01`/`sidebar-08`.

## Yang berubah

- `frontend/src/components/app-sidebar.tsx`: `collapsible="offcanvas"` → `"icon"`, tambah `<SidebarRail />`, data menu jadi `navGroups` (Utama: Dashboard / Operasional: Penerimaan, Laporan Stok), ikon disimpan sebagai **referensi komponen** (`LayoutDashboardIcon`, bukan `<LayoutDashboardIcon />`) karena `collapsible="icon"` merender ulang ikon saat menyusut. Brand dapat `tooltip="Farmagitechs"` supaya tetap terbaca saat collapsed.
- `frontend/src/components/nav-main.tsx`: prop `items` → `groups`; satu `SidebarGroup` tanpa label untuk aksi cepat "Tambah Penerimaan" (tetap, karena `receptions/new` tidak ada di menu CI4), lalu satu `SidebarGroup` + `SidebarGroupLabel` per grup.
- Jarak antar item: `SidebarMenu className="gap-1"` (4px) di dua tempat. Bawaan `SidebarMenu` adalah `gap-0` (tombol menempel), jadi tanpa override tidak ada jeda sama sekali.
- Tidak ada file baru. `nav-user.tsx` tidak disentuh.
- Grup hanya pemisah tampilan, bukan hak akses — tidak ada menu yang disembunyikan per peran.

## Kenapa `gap-1` di sisi pemakai, bukan di `ui/sidebar.tsx`

`ui/` sengaja dibiarkan sedekat mungkin dengan upstream supaya `shadcn add` berikutnya tidak berkonflik; selain itu `SidebarMenu` bawaan dipakai `ui/` lain juga. Override lewat prop `className` aman karena `cn` proyek ini (`cn@0.4.0`, dipakai via `@/foundations/ui/cn`) adalah `twMerge(clsx(...))` — argumen kedua menang atas yang pertama, jadi `gap-1` memang membuang `gap-0`.

Dibuktikan empiris: `cn("flex w-full min-w-0 flex-col gap-0", "gap-1")` → `"... flex-col gap-1"`, dan di CSS hasil build `.gap-1{gap:var(--spacing)}` = 4px.

## Jarak antar elemen (semua dari `ui/sidebar.tsx`, kecuali yang di-override)

| Jarak | Nilai | Sumber |
| --- | --- | --- |
| Antar item dalam satu grup | 4px (override) | `SidebarMenu gap-1` |
| Antar grup | 16px | `SidebarGroup p-2` + `p-2` |
| Tinggi label grup | 32px | `SidebarGroupLabel h-8` (sama dengan tinggi tombol `h-8`) |
| Header brand → menu pertama | 16px | `SidebarHeader p-2` + grup `p-2` |
| Item menu → blok pengguna | 16px | grup `p-2` + `SidebarFooter p-2` |

Saat collapsed (icon mode), `SidebarGroupLabel` menyusut lewat `-mt-8` + `opacity-0` sehingga tingginya net 0 — antar ikon tetap 4px, labelnya hilang dari tampilan tapi tetap ada di DOM untuk pembaca layar.

## Jebakan

- **Verifikasi harus `pnpm build`, bukan cuma lint/typecheck.** Build di sandbox `workspace-write` gagal `spawn EPERM` dari esbuild (batas named pipe), bukan karena kode. Dengan `danger-full-access` build lolos 2,2s.
- Submenu collapsible (chevron `Collapsible` gaya sidebar-07) sengaja tidak dipakai: menu aplikasi datar, jadi tidak ada yang bisa dijadikan anak menu tanpa mengarang.
- `--header-height` tetap 12 karena `site-header.tsx` proyek ini tidak memakai pola `group-has-data-[collapsible=icon]` ala dashboard-01.

Verifikasi: `pnpm lint` 0 error, `pnpm typecheck` (astro check) 74 file 0 error/0 warning, `pnpm build` sukses (2,2s). Bundle hasil build dicek: `.gap-1{gap:var(--spacing)}` ada di CSS dan `Tambah Penerimaan` ada di chunk server.