---
title: "Audit trail before/after farmagitechs"
type: decision
summary: "Audit trail tetap reception-scoped + kolom data_before/data_after JSON; FK reception_id jadi RESTRICT; receipt_id statis di Postman environment dihapus"
tags: ["farmasi", "audit", "database", "postman", "decision"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T03:34:39Z"
updated_at: "2026-10-06T03:34:39Z"
---

Audit trail farmagitechs diputuskan TETAP reception-scoped (reception_logs), bukan tabel audit generik audit_logs(entity_type, entity_id). Alasan: soal (baris 99) hanya mewajibkan reception_id, actor_id, action, waktu; isi before/after eksplisit "tidak diwajibkan" (baris 101); receptions satu-satunya entitas yang bisa ditulis aplikasi (medicines/suppliers/seed_batch_stock/stock_usage read-only dari lampiran, master data & pemakaian baru di luar cakupan baris 286/333-335); tabel generik menghilangkan FK ke entitas yang diaudit padahal penjelasan FK diminta soal.

Yang ditambahkan sebagai nilai tambah: kolom JSON nullable data_before/data_after pada reception_logs (migrasi 2026-10-05-000009), cast CI4 `?json-array` di ReceptionLogModel, snapshot ternormalisasi dari ReceptionService::snapshot() (item diurutkan medicine_id+batch_no agar diff stabil), data_before null saat CREATE, PUT identik menghasilkan before == after.

Perubahan FK: reception_logs.reception_id CASCADE -> RESTRICT (jejak audit tidak boleh ikut terhapus; reception_items tetap CASCADE karena item yatim = data rusak). Konsekuensi: menghapus penerimaan via SQL manual harus hapus log dulu; endpoint DELETE tidak ada di aplikasi.

Jalur generalisasi bila muncul domain tulis kedua: audit_logs(entity_type, entity_id, actor_id, action, data_before, data_after) + backfill dari reception_logs.

Temuan bug Postman yang diperbaiki: Local.postman_environment.json mendefinisikan receipt_id statis yang MENIMPA collectionVariables (environment menang atas collection), sehingga PUT {{receipt_id}} selalu menembak id lama dan gagal 404 saat AUTO_INCREMENT tidak mulai dari 1. Solusi: hapus receipt_id dari environment; biarkan collection yang set. Setelah reset DB, newman 23 request / 51 assertion lolos 0 gagal.