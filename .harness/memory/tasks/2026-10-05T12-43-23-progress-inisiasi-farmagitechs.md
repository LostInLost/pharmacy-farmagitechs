---
title: "Progress inisiasi farmagitechs"
type: task
summary: "Progress: 5 commit init selesai (scaffold, struktur, migrasi, ERD, README); berikutnya implementasi backend"
tags: ["farmasi", "progress", "github", "backend"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-05T12:43:23Z"
updated_at: "2026-10-05T12:43:23Z"
---

5 commit sudah di main github.com/LostInLost/pharmacy-farmagitechs: d69ad28 bootstrap CI4 v4.7.4; ae6aeb2 struktur berlapis + routes + AuthFilter + Permissions; dea6a10 migrasi 8 tabel + DemoUsersSeeder (supervisor/supervisor123, petugas/petugas123, bcrypt); c2ea377 docs/database.md ERD Mermaid; bacd157 README lengkap 7 poin. Endpoint terdaftar + filter auth aktif (401 JSON untuk /api/*, 302 redirect untuk web). Handler API masih 501. Belum ada: implementasi auth/receipts/stocks, UI berfungsi, Postman collection, tests otomatis. Seed lampiran belum ada; 4 tabel seed dibuat provisional di migrasi. Rencana berikutnya: implementasi backend penuh (AuthService login/logout, ReceptionService CRUD + validasi batch, StockService laporan on_date).
