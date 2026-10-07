---
title: "Referensi README + Astro (frontend stack: Astro 7.3.5 SSR, React 19, Tailwind 4, shadcn)"
type: decision
summary: "Bullet \"Referensi\" di README ditambah Astro sesuai permintaan user 2026-10-07: \"dokumentasi resmi CodeIgniter 4 (backend) dan Astro (frontend, lihat frontend/README.md)\". Verifikasi stack frontend aktual: Astro 7.3.5 (output server, adapter @astrojs/node standalone), React 19.3.0, Tailwind 4.3.3, zod 4.6.5, shadcn 4.21.1, radix-ui. README 7.614 char; link check 83 link/38 anchor/0 error."
tags: ["readme", "referensi", "astro", "frontend", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T09:12:09Z"
updated_at: "2026-10-07T09:12:09Z"
---

# Referensi README + Astro — 2026-10-07

## Perubahan
Bullet Referensi di `## Catatan Alat AI dan Referensi`:
- Sebelum: "dokumentasi resmi CodeIgniter 4. Keputusan desain diverifikasi manual..."
- Sesudah: "dokumentasi resmi CodeIgniter 4 (backend) dan Astro (frontend, lihat [`frontend/README.md`](frontend/README.md)). Keputusan desain diverifikasi manual..."

README: 7.533 -> 7.614 char.

## Stack frontend aktual (diverifikasi dari frontend/package.json + node_modules)
- Astro 7.3.5 — `output: "server"` (SSR) + adapter `@astrojs/node` mode standalone; alasan SSR: middleware auth (guest + authenticated) harus jalan sebelum halaman dikirim.
- Integrasi: `@astrojs/react` (React 19.3.0), `@tailwindcss/vite` (Tailwind 4.3.3).
- UI: shadcn 4.21.1, radix-ui 1.6.7, lucide-react, sonner, vaul, next-themes, class-variance-authority.
- Validasi: zod 4.6.5. Font: @fontsource-variable/inter + ibm-plex-sans.
- Tooling: eslint 10, prettier 3 (+ plugin astro/tailwindcss), typescript ~6.0.2, @astrojs/check.

## Verifikasi
- Link check: 83 tautan relatif, 38 anchor, 0 error.
- Line ending README: CRLF penuh (127), 0 LF nyasar.
- Astro disebut di: README, docs/{backend-cors,conventions,database,frontend-theme,security}.md, frontend/README.md.