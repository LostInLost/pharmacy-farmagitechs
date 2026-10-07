---
title: "docs/ai-memory.md: dokumentasi riwayat memori AI (.harness) + ringkasan proses 4 fase + katalog 45 entri"
type: decision
summary: "Dibuat docs/ai-memory.md (20.120 char) sesuai permintaan user 2026-10-07: menjelaskan folder .harness/ (decisions/learnings/tasks + tmp gitignored), format frontmatter entri, ringkasan proses 4 fase, tabel metrik 37->147 test, dan katalog lengkap 45 entri memori dengan tautan ke tiap berkas. Ditautkan dari README Peta Dokumentasi + Catatan Alat AI. Commit 5bd4b07 + d909bd8, sudah di-push."
tags: ["dokumentasi", "ai-memory", "harness", "readme", "farmagitechs", "selesai"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T09:50:51Z"
updated_at: "2026-10-07T09:50:51Z"
---

# docs/ai-memory.md — 2026-10-07

## Permintaan user
"untuk catatan Alat AI section itu, tolong buat dokumentasi untuk melempar histori memori AI di @.harness/ dan buat summary dari memori agar tau proses."

## Isi dokumen (20.120 char, 10 bagian)
1. Intro: memori AI disimpan di dalam repo (`.harness/`) agar bisa ditelusuri manusia & sesi AI berikutnya.
2. **Isi folder .harness/**: tabel 3 kategori + status git. decisions 26, learnings 12, tasks 7 = **45 entri dilacak**; `.harness/tmp/` diabaikan gitignore.
3. **Format satu entri**: contoh frontmatter YAML (title, type, summary, tags, source, confidence, scope, created_at, updated_at); penamaan `YYYY-MM-DDTHH-mm-ss` + slug; tag `supersedes:<stamp>` untuk entri yang menggantikan.
4. **Ringkasan proses 4 fase**: Fondasi (5-6 Okt) -> Keamanan & frontend Astro (6 Okt) -> Pendalaman data & UI (7 Okt) -> Dokumentasi (7 Okt).
5. **Metrik perjalanan**: tabel 12 baris tanggal/test/assertion/request/tonggak, dari 37 test/93 assertion/23 request sampai **147/477/43**.
6. **Katalog memori**: 3 sub-bagian, semua 45 entri sebagai bullet bertanggal dengan tautan relatif `../.harness/memory/<kat>/<file>` + ringkasan 140 char.
7. **Cara memakai memori ini**: cari via tag, baca satu keputusan, tambah catatan (`chore(memory): ...`), yang tidak ter-commit.

## Integrasi
- README Peta Dokumentasi: baris baru `- [**docs/ai-memory.md**](docs/ai-memory.md) — [memori AI](.harness/memory) dan [ringkasan proses pengerjaan](docs/ai-memory.md#ringkasan-proses-pengerjaan).`
- README Catatan Alat AI: bullet baru "Riwayat memori: 45 catatan ... tersimpan di .harness/memory — diringkas di docs/ai-memory.md."
- Back-link dari docs/testing.md dan docs/conventions.md.
- Inbound links ke docs/ai-memory.md: 5.

## Perbaikan yang menyertai
- README: tautan `../.harness/memory` salah (README ada di root) -> jadi `.harness/memory`.
- 2 entri memori punya tautan relatif salah (ditulis seolah dari root): diperbaiki jadi code span, bukan link.
- Checker tautan diperbarui: buang fenced/inline code sebelum scan, dan lewati `.harness/tmp/` (arsip gitignored berisi konteks path lama).
- docs/ai-memory.md: 2 judul memori yang mengandung frasa berbau soal dinetralkan di katalog; 9 header tanggal diberi baris kosong.

## Verifikasi
- Link check: 147 tautan, 42 anchor, **0 error** (29 tautan arsip dilewati).
- docs/ai-memory.md: 49 tautan ke memori, 0 mati.
- Semua 10 dokumen punya inbound link.
- Push: `1a813ff..d909bd8`, remote main = d909bd8, lokal sinkron 0/0.