---
title: "Log operasi medicines: detail obat menyertakan riwayat audit (data.logs), label Audit.medicines, tanpa migrasi"
type: decision
summary: "Detail obat kini menyertakan logs via AuditService::forEntity; label Audit.medicines ditambah di id/en dan mirror frontend; tanpa migrasi; 151 test + Newman 108 assertion hijau."
tags: ["medicines", "audit-log", "medicine-service", "i18n", "detail-logs", "postman", "test", "farmagitechs"]
source: "session"
confidence: high
scope: project
created_at: "2026-10-07T11:01:22Z"
updated_at: "2026-10-07T11:01:22Z"
---

2026-10-07: business logic log medicines dilengkapi.

TEMUAN: create/update SUDAH menulis audit via AuditService sejak commit 8497d26/45a784f (di dalam transaksi MedicineService). Yang kurang hanya jalur baca + label.

PERUBAHAN:
1. app/Services/MedicineService.php — detail() kini mengembalikan baris obat + logs via audit->forEntity(ENTITY_MEDICINE, id), pola sama dengan ReceptionService::detail(). list() sengaja tetap tanpa log (N query per baris tidak sepadan). Tidak ada delete(): master obat tanpa DELETE (FK RESTRICT).
2. app/Language/{id,en}/Audit.php — grup 'medicines' (create/update/delete). Tanpa migrasi: audit_logs.action sudah VARCHAR(100) sejak migrasi 000013 dan entity_type 'medicine' sudah didukung.
3. frontend/src/features/audit/labels.ts — mirror label ditambah 3 kunci medicines (kunci di DB tetap sumber kebenaran).
4. postman/ collection — item 4.13 'Detail obat memuat log audit (200)': membuktikan data.logs memuat create+update, snapshot data_before/after is_active, actor_name, dan can_write=false untuk petugas. Folder 4 kini 13 request; collection 44 request / 108 assertion.

TEST:
- tests/Feature/MedicineApiTest.php +3: detail memuat logs kronologis (create before=null, update before/after nama+is_active, actor_name), daftar tetap tanpa logs, dan tulis ditolak (403 petugas, 422 duplikat/payload kosong, 404 id tak ada) tidak menambah baris audit_logs.
- tests/unit/AuditActionLabelTest.php +1: label medicines id+en; sekaligus memperbaiki sisa mojibake '§BT§' (seharusnya backtick) di docblock berkas itu.

VERIFIKASI: composer run test 151 test / 504 assertion hijau (naik dari 147/477); Newman 44 request / 108 assertion hijau (naik dari 43/104); pnpm typecheck + lint hijau; pnpm build hijau (dijalankan unconfined karena esbuild spawn EPERM di sandbox).

CATATAN: MySQL Laragon (E:\laragon) tidak listen saat sesi mulai dan tidak bisa distart dari sandbox (ibdata1 must be writable); server ternyata hidup via PID 10760 setelah upaya start. Server CI4 untuk Newman dijalankan `php spark serve --port 8080`; DB dev di-refresh (migrate:refresh + StockSeeder + DemoUsersSeeder) sebelum run.

KOMIT: pathspec eksplisit (repo multi-tab) — README.md, AGENTS.md (angka status), app/Language/{id,en}/Audit.php, app/Services/MedicineService.php, docs/{postman,testing}.md, frontend/src/features/audit/labels.ts, postman/*.json, tests/Feature/MedicineApiTest.php, tests/unit/AuditActionLabelTest.php.