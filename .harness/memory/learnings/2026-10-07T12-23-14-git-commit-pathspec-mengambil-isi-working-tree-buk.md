---
title: "git commit <pathspec> mengambil isi working tree, bukan index — staging parsial per-hunk sia-sia"
type: learning
summary: "`git commit -F msg -- <paths>` mengganti isi index dengan isi working tree untuk path itu; staging parsial (update-index --cacheinfo dari blob rakitan / git apply --cached) jadi tidak berpengaruh. Akibat: commit memuat migrasi `Feedback` milik tab lain padahal sudah dibangun 'hunks saya saja' → commit tidak konsisten dan gagal typecheck."
tags: ["git", "commit", "pathspec", "multi-tab", "staging", "index", "powershell", "workflow"]
source: "insiden commit b2246d8 → diperbaiki jadi 7818f3e, 2026-10-07"
confidence: high
scope: project
created_at: "2026-10-07T12:23:14Z"
updated_at: "2026-10-07T12:23:14Z"
---

Di repo ini, `git commit -F <msgfile> -- <paths>` MENGGANTIKAN isi index dengan isi WORKING TREE untuk path itu. Karena itu staging parsial tidak berpengaruh pada hasil commit.

Yang dicoba dan gagal:
- `git apply --cached` dari patch yang hanya berisi hunk milik sendiri → gagal apply (CRLF/LF; PowerShell Out-File menulis UTF-16LE, jadi jangan lewatkan data teks git melalui pipeline PowerShell — pakai `cmd /c` redirect atau Node).
- Menulis blob sendiri lalu `git update-index --cacheinfo 100644,<sha>,<path>` → blob masuk index dengan benar, tapi `git commit -- <path>` tetap mengambil versi working tree, sehingga perubahan tab lain ikut ter-commit.

Akibat nyata (commit b2246d8): reception-form-sheet.tsx ter-commit memakai `<Feedback messages={...} />` (migrasi tab lain) sementara HEAD:frontend/src/components/feedback.tsx masih mewajibkan prop `variant` → TypeScript menolak, commit patah.

Cara yang benar:
1. Samakan dulu isi WORKING TREE dengan yang ingin di-commit (tulis/timpa berkas hasil rakitan), baru `git commit -- <paths>`.
2. Setelah itu kembalikan isi working tree milik tab lain (jangan tinggalkan tab lain kehilangan kerjanya).
3. Alternatif staging parsial yang sah: seluruh rangkaian dengan `GIT_INDEX_FILE=<tmp>`, atau worktree terpisah — jangan mencampur `update-index` dengan `commit` ber-pathspec.

Verifikasi cepat sebelum commit: `git diff HEAD^ HEAD -- <file> | Select-String '^[+-].*Feedback'` harus 0 bila berkas itu mencampur kerja tab lain.