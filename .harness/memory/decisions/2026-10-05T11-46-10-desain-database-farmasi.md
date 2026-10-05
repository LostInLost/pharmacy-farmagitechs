---
title: "Desain database farmasi"
type: decision
summary: "Skema DB farmasi: seed dipertahankan + 4 tabel users/receptions/items/logs, stok agregasi ledger"
tags: ["farmasi", "database", "schema", "stock"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-05T11:46:10Z"
updated_at: "2026-10-05T11:46:10Z"
---

Skema: pertahankan suppliers, medicines, seed_batch_stock, stock_usage apa adanya; tambah users(id, username UK, password_hash, role ENUM penerimaan/supervisor, is_active), receptions(id, reference_no UK, supplier_id FK, received_at, created_by FK tetap, updated_by FK NULL sampai diubah), reception_items(id, reception_id FK CASCADE, medicine_id, batch_no, expires_on, quantity, UNIQUE reception_id+medicine_id+batch_no), reception_logs(id, reception_id FK, actor_id FK, action ENUM CREATE/UPDATE, created_at). Model stok agregasi ledger: fisik = seed + SUM(receipt) - SUM(usage), status dari expires_on < on_date. updated_by NULL = belum diubah. Batch nol tetap tampil terurut expires_on. Alasan: hindari hitung ganda, PUT full-replace idempoten, rollback transaksional sederhana, trace supplier/obat via join (medicine_id,batch_no).