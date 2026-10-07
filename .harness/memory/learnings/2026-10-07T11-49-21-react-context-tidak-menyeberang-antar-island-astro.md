---
title: "React Context tidak menyeberang antar island Astro: TooltipProvider AppShell tidak sampai ke island halaman"
type: learning
summary: "Astro membuat React root terpisah per island, jadi React Context (mis. TooltipProvider) tidak menyeberang antar island: TooltipProvider di AppShell tidak menjangkau island StockReport sehingga muncul 'Tooltip must be used within TooltipProvider'. Perbaikan: pasang provider lokal di dalam island pemakai; commit d9d7834."
tags: ["astro", "island", "react-context", "tooltip", "radix-ui", "frontend", "debugging", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T11:49:21Z"
updated_at: "2026-10-07T11:49:21Z"
---

## Gejala
Halaman `/stocks` melempar `Uncaught Error: Tooltip must be used within TooltipProvider` setelah aksi baris laporan stok diubah menjadi tombol ikon ber-tooltip.

## Sebab
`AppShell` (island `client:load` sendiri) memasang `TooltipProvider`, dan `StockReport` adalah **island terpisah** di halaman yang sama. Astro membuat React root tersendiri per island, jadi React Context **tidak menyeberang** antar island — komponen di island stok tidak menemukan provider milik island shell.

## Perbaikan
Pasang `TooltipProvider` **di dalam island yang memakainya** (`stock-report.tsx` membungkus `<div>` utamanya). Provider bersarang tidak masalah: `sidebar.tsx` tetap aman karena hidup di island `AppShell` yang punya provider sendiri.

## Aturan umum untuk repo ini
Jangan pernah mengandalkan provider React (Tooltip, theme, dsb.) dari `AppShell` untuk komponen yang dirender di island lain. Setiap island yang memakai konteks harus membawa provider-nya sendiri — atau dipindahkan ke island yang sama. Saat ini hanya `AppShell` dan `StockReport` yang memakai `Tooltip`, jadi tidak ada island lain yang perlu diperiksa.

## Verifikasi
`pnpm typecheck` 91 file 0 error; `eslint` file stocks 0 error; `pnpm build` Complete. Commit `d9d7834` (perbaikan) menyusul `5a66e53` (fitur).
