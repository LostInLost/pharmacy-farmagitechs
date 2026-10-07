---
title: "Konvensi Peta Dokumentasi README: nama berkas jadi link tebal, bukan code span (biar jelas bisa diklik)"
type: decision
summary: "User 2026-10-07 bertanya apakah Peta Dokumentasi bisa diklik. Ternyata sudah link sejak awal, tapi nama berkas dibungkus backtick sehingga di GitHub tampak abu-abu seperti kode, bukan biru seperti link. Diubah jadi link tebal: `docs/database.md` (link tebal). Uji klik: 25 tautan di Peta Dokumentasi, 0 bermasalah. README 7.622 char."
tags: ["readme", "dokumentasi", "markdown", "konvensi", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T09:21:22Z"
updated_at: "2026-10-07T09:21:22Z"
---

# Konvensi Peta Dokumentasi README — 2026-10-07

## Masalah
User bertanya "peta dokumentasi gak bisa by link kah? misal docs/database.md itu diklik maka akan redirect kesitu."
Tautannya SUDAH ada, tapi karena nama berkas dibungkus backtick (code span), di GitHub tampil abu-abu monospace — secara visual tidak terbaca sebagai hyperlink.

## Keputusan
Format nama berkas di Peta Dokumentasi: **link tebal tanpa code span**.
- Lama: nama berkas dibungkus backtick di dalam kurung siku, mis. `[nama](tujuan)`.
- Baru: nama berkas jadi tebal di dalam kurung siku, mis. `[**nama**](tujuan)`.
- Nama berkas di dalam kalimat (bukan item peta) tetap boleh pakai code span.

## Bukti
- Render HTML: `<a href="docs/database.md">docs/database.md</a>` — benar-benar anchor.
- Uji klik 25 tautan Peta Dokumentasi (9 berkas + 16 deep-link anchor): 25 OK, 0 bermasalah.
- Link check seluruh repo: 83 tautan, 38 anchor, 0 error.
- README: 7.614 -> 7.622 char, CRLF penuh.

## Catatan
Anchor GitHub = heading lowercase, buang tanda baca, spasi jadi `-`. Semua anchor diverifikasi ada di berkas tujuan (mis. `docs/database.md#kunci-dan-indeks`).