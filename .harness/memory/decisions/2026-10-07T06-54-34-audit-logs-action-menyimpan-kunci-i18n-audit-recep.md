---
title: "audit_logs.action menyimpan kunci i18n (Audit.receptions.action.create), label di app/Language/{id,en}/Audit.php; ENUM->VARCHAR(100); commit 45a784f..0fe6a02, 142 test hijau MySQLi+SQLite3"
type: decision
summary: "audit_logs.action menyimpan kunci i18n (Audit.receptions.action.create), label di app/Language/{id,en}/Audit.php; ENUM->VARCHAR(100); commit 45a784f..0fe6a02, 142 test hijau MySQLi+SQLite3"
tags: ["audit-log", "i18n", "action-label", "migration", "reception", "medicine", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T06:54:34Z"
updated_at: "2026-10-07T06:54:34Z"
---

# Action audit = kunci i18n, berkas label sendiri (Audit.php)

## Keputusan
Nilai `audit_logs.action` = **kunci i18n utuh** (`Audit.receptions.action.create`), bukan token kanonik lagi. Label dipindah ke berkas sendiri `app/Language/{id,en}/Audit.php` (grup `receptions.action.{create,update,delete}`); `Reception.log.action_*` **dihapus**. Menggantikan keputusan lama (commit 2e3b486) yang menyimpan token `CREATE/UPDATE/DELETE`.

## Bentuk kunci
- Segmen berkas **kapital**: `lang()` mencari `Language/{locale}/{file}.php` apa adanya — kunci huruf kecil (`audit.…`) jalan di Windows tapi **gagal di Linux** (case-sensitive). Ini jebakan utama.
- Bonus: `lang($row['action'])` langsung jalan, dan `lang()` mengembalikan kunci mentah bila terjemahan tidak ada → tidak perlu kode fallback khusus.

## Implementasi
- `AuditService` merangkai kunci: peta `KEY_GROUPS` (`reception`→`receptions`, `medicine`→`medicines`, fallback nama entity) + verb `create|update|delete`. Signature publik tidak berubah → `ReceptionService`/`MedicineService` tidak tersentuh.
- Migrasi `2026-10-07-000013_AuditActionToI18nKey`: **lebarkan kolom dulu** (ENUM→`VARCHAR(100) NOT NULL`), baru backfill 3 UPDATE ber-`where`; kalau urutannya terbalik, MySQL membuang/menolak nilai non-ENUM. `down()` pakai pola `Audit.<grup>.action.<verb>` (`like` depan-belakang), **bukan** daftar `entity_type`: kode baru menulis kunci untuk entitas apa pun, dan ENUM hanya menerima 3 token — satu baris tertinggal membuat penyempitan kolom gagal.
- Render CI4: view mengirim peta `'<kunci>': '<label>'` di boot i18n; `reception-form.js` → `Farmasi.text(action, action)`.
- Render Astro: `features/audit/labels.ts` (`auditActionLabel`) = padanan TypeScript `Audit.php`; dipakai `audit-log-table.tsx`. Astro belum punya i18n, jadi nilai dari API dipakai sebagai kunci lookup peta statis.
- Ringkasan "dibuat" kini dikenali dari `data_before === null`, **bukan** dari nilai `action` — supaya tidak ikut pecah saat kunci bertambah.

## Lingkup & sisa
Hanya `entity_type='reception'` yang di-backfill, tetapi baris `medicine` **baru** ikut tersimpan sebagai kunci (`Audit.medicines.action.create`) karena AuditService satu-satunya penulis — hanya berkas labelnya belum dibuat, jadi tampil mentah lewat fallback. Membuat label obat = tambah grup `medicines` di `Audit.php` + entri di `labels.ts`; tanpa migrasi kolom.

## Verifikasi
142 test / 459 assertion hijau di MySQLi **dan** SQLite3 (`php -d extension=sqlite3` + config sementara ber-`<env>`; sqlite3 tidak aktif di php.ini host). `pnpm typecheck` 0 error, `pnpm lint` 0 masalah. `php spark migrate` di DB dev: kolom `varchar(100) NOT NULL`, 3 baris lama ter-backfill. Boot halaman CI4 dicek lewat `curl` ber-sesi: peta kunci→label benar. API mengembalikan kunci. Render browser (Chrome CDP) **tidak bisa dijalankan di sesi ini** (sandbox memblokir spawn Chrome; eskalasi ditolak user) → disiapkan `frontend/scripts/verify-audit-label.mjs` untuk dijalankan manual.

## Commit
45a784f (backend+migrasi+test) → 189344e (render CI4) → 7cf9e92 (Astro) → 0fe6a02 (docs+Postman).
