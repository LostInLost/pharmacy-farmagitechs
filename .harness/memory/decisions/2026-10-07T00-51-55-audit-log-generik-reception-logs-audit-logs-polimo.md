---
title: "Audit log generik: reception_logs → audit_logs polimorfik"
type: decision
summary: "reception_logs digeneralisasi jadi audit_logs polimorfik (entity_type/entity_id, tanpa FK entity_id); migrasi 000011 backfill + down aman; commit 5d1b06d, suite 102 test hijau + Newman 74 assertion"
tags: ["farmasi", "audit", "database", "migrasi", "polymorphic", "decision", "supersedes:2026-10-06T03-34-39"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T00:51:55Z"
updated_at: "2026-10-07T00:51:55Z"
---

Keputusan lama (2026-10-06T03-34-39) "audit trail tetap reception-scoped" DIBATALKAN atas permintaan user: log aksi dipakai general, jadi tabel digeneralisasi menjadi `audit_logs` polimorfik.

Bentuk baru: `audit_logs(id, entity_type VARCHAR(50), entity_id INT UNSIGNED, actor_id FK users RESTRICT, action ENUM(CREATE/UPDATE/DELETE), data_before JSON NULL, data_after JSON NULL, created_at)`; indeks `(entity_type, entity_id, created_at)`. `entity_id` sengaja TANPA FK (polimorfik, menunjuk banyak tabel) — trade-off diterima karena jejak audit justru harus hidup saat entitas dihapus; `actor_id` tetap FK. Baris penerimaan memakai `entity_type='reception'`.

Migrasi: `2026-10-07-000011_RenameReceptionLogsToAuditLogs` — bangun tabel baru via Forge (portabel MySQL/SQLite), backfill dari reception_logs dengan mempertahankan `id`, lalu drop reception_logs. `down()` membangun ulang reception_logs dengan FK reception_id CASCADE/RESTRICT dan hanya menyalin log yang reception-nya masih ada (log yatim tidak punya tempat di skema lama; kalau dipaksa insertBatch akan ditolak FK dan meninggalkan tabel setengah jadi — bug ini sempat muncul saat test suite regress). Migrasi 000008/000009 lama tetap dipertahankan sebagai sejarah (fresh DB: create → alter → rename).

Kode: `AuditLogModel` (rename dari ReceptionLogModel, casts json-array + stamp created_at) dan `AuditLogRepository` (ENTITY_RECEPTION='reception', forEntity()/record()) baru; ReceptionRepository::logsOf()/log() mendelegasikan ke sana; kontrak API `logs[]` (action/actor_name/data_before/data_after/created_at) tidak berubah sehingga JS & Postman aman. Test: ReceptionAuditTest dapat kasus `testAuditLogSupportsOtherEntityTypes` + assert kolom varchar; fixture clear() pakai audit_logs.

Verifikasi: full test suite 102 test / 296 assertion hijau (saat WIP tab lain di-stash; 2 kegagalan StockReportTest/StockSeederTest berasal dari WIP uncommitted StockRepository.php tab lain yang menghapus cast int, bukan dari refactor ini); round-trip rollback→migrate di DB dev dengan baris nyata mempertahankan data; migrate:refresh dari nol bersih; Newman 30 request / 74 assertion lolos. Commit 5d1b06d.

Dampak ke jawaban soal: soal hanya mewajibkan reception_id/actor_id/action/waktu; kolom generik tetap memenuhi karena entity_type='reception' + entity_id. README & docs/database.md sudah diperbarui menjelaskan alasan generik + trade-off FK.