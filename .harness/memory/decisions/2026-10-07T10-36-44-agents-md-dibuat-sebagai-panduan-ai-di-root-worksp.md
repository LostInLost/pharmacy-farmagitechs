---
title: "AGENTS.md dibuat sebagai panduan AI di root workspace"
type: decision
summary: "AGENTS.md (root, ~10KB, 9 bagian) dibuat 2026-10-07: panduan arsitektur berlapis CI4, jebakan sandbox DSH (is_writable false, push via askpass, jangan MCP push_files), resep env PHP system + MySQL Laragon, aturan memory_search/write, dan Definition of Done. DSH memuatnya otomatis sebagai workspace instructions."
tags: ["agents-md", "dokumentasi", "ai-tools", "arsitektur", "sandbox", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T10:36:44Z"
updated_at: "2026-10-07T10:36:44Z"
---

# AGENTS.md — panduan AI di root workspace

Dibuat 2026-10-07 atas permintaan user ("biar AI tau context nya"). DSH memuatnya otomatis sebagai workspace instructions setiap sesi.

## Isi (9 bagian)
1. Ringkasan stack (CI4 4.7.4, MySQL 8.4.3 Laragon, Astro 7.3.5 SSR pnpm-only, status test 147/477 + 43 request Postman).
2. Struktur proyek + larangan edit writable/, vendor/, build/.
3. Aturan arsitektur wajib (Controller→Service→Repository→Policy, AuditService satu pintu, ledger stock_movements, CSRF global, tanpa DELETE master obat).
4. Frontend Astro (pnpm only, middleware chaining, pola sheet, foundations tidak boleh impor features).
5. Lingkungan mesin Windows: PHP system C:\Users\php8.4\php.exe (BUKAN PHP Laragon), MySQL hanya via Laragon (mysqld tak bisa start manual), npm/npx rusak → node langsung, cek port 8080/3306 via Get-NetTCPConnection.
6. Jebakan sandbox DSH: is_writable() false → server PHP harus unconfined; resep git push via GIT_ASKPASS (.harness/tmp/askpass.cmd, hapus segera); JANGAN MCP GitHub push_files (commit terpisah, menelantarkan lokal); commit ber-pathspec (multi-tab).
7. Memory AI & MCP: memory_search sebelum kerja, memory_write setelah keputusan, MCP github/gitlab/shadcn/astro tersedia (shadcn dari frontend/; astro = update 2026-10-07 sore, bundel @local/astro-mcp, tool mcp__astro__search_astro_docs untuk dokumentasi resmi Astro).
8. Konvensi dokumentasi: README tetap hub, endpoint baru wajib masuk README + Postman + test, migrasi baru jangan edit lama, dokumen berbahasa Indonesia.
9. Definition of Done (test hijau, typecheck/lint/build, Newman, memory_write, commit segera).

## Sumber
Disusun dari README.md, docs/conventions.md, docs/ai-memory.md, frontend/package.json, frontend/README.md, dan 5 entri memory operasional (setup PHP/MySQL, sandbox is_writable, cara push, pelajaran testing, environment workaround).

## Konsekuensi
- README sengaja TIDAK diubah (user pemilik penuh konten README; AGENTS.md terdeteksi otomatis oleh tool AI). Bila user mau, bisa ditambahkan satu baris di Peta Dokumentasi.