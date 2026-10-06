---
title: "Backend farmagitechs selesai"
type: task
summary: "Backend + UI + test + Postman selesai di commit d2cf754; 37 test & 23 request Newman lolos"
tags: ["farmasi", "progress", "backend", "testing"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T02:08:54Z"
updated_at: "2026-10-06T02:08:54Z"
---

Commit d2cf754 (38 file) di main: implementasi backend penuh + UI + test + Postman. Yang sudah jalan: (1) Auth session (AuthService attempt/login/logout, AuthController API, AuthPages web, CSRF aktif untuk web kecuali /api/*, session simpan user_id/user_name/role). (2) API penerimaan POST/GET/PUT lengkap dengan ReceptionValidator (reference_no unik, supplier/obat aktif, qty integer positif, batch unik per payload, expires_on konsisten lintas seed+reception_items, expires_on > tanggal penerimaan Asia/Jakarta) + ReceptionPolicy (petugas hanya milik sendiri, supervisor semua) + transaksi transBegin/Commit/Rollback. (3) StockService laporan ledger dengan UNION tiga sumber (seed_batch_stock, reception_items, stock_usage) — PENTING: query harus UNION, bukan LEFT JOIN dari seed saja, karena batch bisa hanya ada di penerimaan. (4) UI 4 halaman + CSS/JS di public/assets. (5) 37 test lolos (93 assertion), Postman 23 request/48 assertion lolos via Newman. (6) Timestamp via model events (beforeInsert/beforeInsertBatch/beforeUpdate) dengan Time::now(); useTimestamps=false; DB tanpa default. Catatan: git push LANGSUNG sekarang berhasil (sandbox danger-full-access), tidak perlu MCP push_files lagi.