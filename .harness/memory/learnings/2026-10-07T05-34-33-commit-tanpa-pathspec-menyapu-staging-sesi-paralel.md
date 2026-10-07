---
title: "Commit tanpa pathspec menyapu staging sesi paralel; pecah dengan commit ber-pathspec"
type: learning
summary: "Commit git tanpa pathspec menyapu file yang di-stage sesi paralel; perbaikan: pecah via reset --soft + commit ber-pathspec eksplisit; commit ber-pathspec memakai isi working tree (bukan index) sehingga edit WIP sesi lain bisa ikut ter-commit."
tags: ["git", "workflow", "parallel-sessions", "commit", "reset-soft", "pathspec", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T05:34:33Z"
updated_at: "2026-10-07T05:34:33Z"
---

Di repo ini beberapa sesi agen berjalan paralel pada working tree yang sama. Kejadian nyata (2026-10-07):

1. `git add` 6 file task badge, lalu `git commit -F msg` tanpa pathspec → commit berisi **9 file**: 2 file sidebar (app-sidebar.tsx, nav-main.tsx) + 1 memory note milik sesi paralel yang sudah di-stage di index. Penyebab: index dibagi bersama; sesi paralel `git add` beberapa detik sebelumnya (note ditulis 12:22:38, commit saya 12:22:44).

2. Perbaikan tanpa kehilangan apa pun:
   - `git reset --soft HEAD~1` (index tetap berisi campuran).
   - Tiga `git commit -q -F msg -- <path...>` berurutan dengan guard `git rev-parse HEAD` di antaranya.
   - Verifikasi: `git diff <tree-lama> <tree-baru>` → hanya 1 file berbeda, membuktikan tidak ada konten yang hilang/berubah selain yang diharapkan.

3. Jebakan penting: `git commit -- <path>` memakai **isi working tree** dari path itu, bukan versi yang ada di index. Akibatnya edit WIP sesi paralel yang belum di-stage (entri "Master Obat" di app-sidebar, dibuat setelah file di-stage) ikut masuk ke commit split — sehingga commit sidebar memuat perubahan yang bukan bagian pesannya.

Aturan praktis di repo sesi-paralel:
- Selalu commit dengan pathspec eksplisit (`git commit -m ... -- file1 file2`), jangan andalkan index.
- Cek `git status` dan mtime file persis sebelum commit; file berubah beberapa detik lalu = kemungkinan sesi lain sedang aktif.
- Hindari `git add -A` / `git add .`.
- Untuk rewrite history, pakai guard HEAD sebelum tiap langkah dan bandingkan tree lama vs baru setelah selesai.