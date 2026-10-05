---
title: "Keputusan stack farmasi"
type: decision
summary: "Stack CI4 + MySQL, Hono ditolak, Astro ditunda setelah backend tuntas"
tags: ["farmasi", "stack", "codeigniter", "frontend"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-05T11:46:10Z"
updated_at: "2026-10-05T11:46:10Z"
---

Stack: backend wajib PHP CodeIgniter 4 + MySQL 5.7+, Hono ditolak karena backend wajib PHP. Frontend Astro ditunda paling terakhir; utamakan view CI4 sederhana agar auth/CORS tidak menambah risiko demo. Urutan: DB+seed, auth, API penerimaan, API stok, UI ringan, lalu opsional lain. seed_farmasi.sql belum ada di workspace, skema menunggu file seed untuk dikunci.