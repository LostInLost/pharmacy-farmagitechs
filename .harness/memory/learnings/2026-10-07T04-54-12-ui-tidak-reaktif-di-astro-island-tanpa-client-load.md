---
title: "UI tidak reaktif di Astro: island tanpa client:load dan cache Vite basi"
type: learning
summary: "Island mati tanpa error: AppShell tanpa client:load + cache Vite basi (504 sonner.js); cara diagnosis via CDP"
tags: ["astro", "react", "island", "hidrasi", "vite", "debugging", "frontend"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T04:54:12Z"
updated_at: "2026-10-07T04:54:12Z"
---

## Gejala
Sidebar tidak bisa di-collapse, menu profil tidak muncul, tombol lain tidak bereaksi — tanpa error konsol yang jelas. Halaman tetap ter-render (HTML SSR lengkap), jadi terlihat seperti "CSS/JS Astro mati".

## Dua penyebab berbeda yang ditemukan

### 1. Island tidak pernah diminta hidrasi (penyebab utama)
`AppShell` dipakai TANPA direktif `client:*` di 5 halaman terproteksi. Di Astro, komponen React tanpa direktif hanya dirender sekali di server (HTML statis), tanpa JS sama sekali.

Deteksi: di DevTools, hitung `document.querySelectorAll('astro-island')` dan periksa atribut `ssr`:
- island sehat: `ssr` hilang setelah hidrasi (atribut `client-render-time` muncul)
- island mati: `ssr` masih menempel, dan elemen tidak ada di daftar island sama sekali

Probe CDP yang membuktikan: hanya `DashboardOverview` yang terhidrasi; `AppShell` tidak ada. Setelah `client:load` ditambahkan, `AppShell` muncul di daftar island.

Catatan penting: **nested island tetap terhidrasi** — anak (`DashboardOverview client:load`) di dalam `<AppShell client:load>` tetap jalan. Jadi pola shell ber-client + slot anak ber-client itu sah.

Perbaikan: semua halaman memakai `<AppShell client:load ...>`. Riwayat: pola benar ada di scaffold (`<DashboardShell client:load />`), lalu hilang saat shell dipecah di commit `ce90563`.

### 2. Cache Vite basi (penyebab kedua, muncul belakangan)
Gejala khas: `[astro-island] Error hydrating ... TypeError: Failed to fetch dynamically imported module: <url>?astro-retry=...`

Deteksi cepat: cek `Network.responseReceived` via CDP — ditemukan `HTTP 504` pada `/node_modules/.vite/deps/sonner.js`. Direktori `node_modules/.vite/deps` kehilangan entri itu.

Catatan: `curl` ke URL modul yang sama bisa balas 200 karena server menyajikan dari memori, sementara browser tetap gagal. Jadi **jangan andalkan curl saja** — lacak via `Network.loadingFailed` / `responseReceived >= 400` di CDP.

Perbaikan: hapus folder `frontend/node_modules/.vite` lalu minta Vite membangun ulang. Restart dev server adalah cara paling andal; menghapus cache saat server berjalan tidak selalu memicu re-optimize penuh.

## Pelajaran umum
- Komponen React yang memakai state/event (dropdown, collapsible, toggle) WAJIB punya direktif `client:*`. Tanpa itu tidak ada error, hanya diam.
- Kalau UI "tidak bereaksi" tapi HTML benar dan konsol bersih: periksa direktif `client:*` lebih dulu, baru cache Vite.
