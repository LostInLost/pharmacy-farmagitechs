---
title: "Repo dikerjakan multi-tab: commit segera, reset --hard bisa hapus kerja uncommitted"
type: learning
summary: "Repo ini dikerjakan beberapa tab bersamaan dan ada riwayat `git reset` ke `origin/main`, sehingga pekerjaan uncommitted berisiko hilang permanen. Aturan: commit segera setelah unit kerja terverifikasi; jangan simpulkan file \"belum di-commit\" hanya dari `git status` — cek `git log -- <file>` karena tab lain bisa sudah meng-commit-nya."
tags: ["git", "workflow", "multi-tab", "reset", "commit", "risk"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T10:44:39Z"
updated_at: "2026-10-06T10:44:39Z"
---

## Kondisi
Repo ini dikerjakan **beberapa tab/agen bersamaan**. Saat sesi CSRF berjalan, 11 commit muncul dari tab lain tanpa terlihat di sesi ini (`100bdad` audit snapshot → `d6499e0` catatan memory), padahal sesi hanya tahu sampai `d2cf754`.

## Risiko yang terbukti dari reflog
Tab lain menjalankan `git reset` **dua kali** ke `origin/main` (`8babe71`, `d6499e0` di reflog). Kalau itu `reset --hard` dan terjadi **sementara ada pekerjaan uncommitted**, pekerjaan itu **hilang permanen** — reflog hanya menyimpan commit, bukan perubahan yang belum di-commit.

Sisa lain: commit yatim `5a32e22` (isi hampir identik dengan `d6499e0`, beda hanya newline di file memory) sebagai jejak reset.

## Aturan kerja
1. **Commit segera** setelah satu unit kerja selesai dan terverifikasi — jangan menumpuk perubahan uncommitted lama-lama.
2. Sebelum mengedit file, sadari file itu mungkin sudah diubah tab lain; cek `git log --oneline -5 -- <file>` bila ragu.
3. Kalau menemukan perubahan yang "bukan dari sesi ini", **jangan langsung simpulkan belum di-commit** — cek dulu `git log`/`git show --stat` karena bisa jadi sudah masuk commit tab lain.
4. Setelah commit di sesi ini, `git log origin/main..HEAD` untuk melihat posisi relatif terhadap remote.

## Kasus nyata
Dua file (`app/Repositories/StockRepository.php`, migrasi `000009`) sempat dikira "perubahan lama yang belum di-commit" dari sesi ini, padahal tab lain sudah meng-commit-nya sebagai `8babe71` (Forge API + `unionAll()` menggantikan SQL mentah). Kesimpulan awal itu keliru karena `git status` saja tidak menunjukkan siapa yang mengubah.