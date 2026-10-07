---
title: "AuditService: satu pintu penulisan audit, kontrak transaksi mengikuti pemanggil"
type: decision
summary: "AuditService (logCreated/logUpdated/logDeleted + forEntity) jadi satu-satunya penulis audit; tanpa transaksi sendiri; ReceptionRepository kehilangan log()/logsOf(); commit fef8e0c, 125 test hijau + Newman 75 assertion."
tags: ["audit-log", "audit-service", "arsitektur", "service-layer", "farmagitechs", "refactor"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T02:33:23Z"
updated_at: "2026-10-07T02:33:23Z"
---

## Keputusan
Penulisan jejak audit dipusatkan di `App\Services\AuditService` (commit fef8e0c). Bentuk yang dipilih **bukan** `log(Model $model, array $data)` seperti usulan awal, melainkan API semantik per aksi.

## Bentuk final
```php
AuditService::forEntity(string $entityType, int $entityId): array
AuditService::logCreated(string $entityType, int $entityId, int $actorId, array $after): void
AuditService::logUpdated(string $entityType, int $entityId, int $actorId, array $before, array $after): void
AuditService::logDeleted(string $entityType, int $entityId, int $actorId, array $before): void
```
Konstanta `AuditService::ENTITY_RECEPTION = 'reception'` (pindah dari AuditLogRepository).

## Kenapa bukan `log(Model, data)`
1. **Lapisan.** Konvensi repo (`docs/conventions.md`): Service tidak boleh tahu Model/tabel — itu tugas Repository. Menerima Model di service akan membocorkan detail persistensi ke lapisan atas. Service menerima `entity_type`/`entity_id` (nilai domain), bukan objek Model.
2. **Aksi hilang.** `log(Model, data)` menyembunyikan aksi yang dicatat, padahal nilai `action` (`CREATE`/`UPDATE`/`DELETE`) itu justru inti kontrak audit. `logCreated`/`logUpdated`/`logDeleted` mengeja aksi di nama method dan sekaligus menetapkan bentuk snapshot yang benar (`before=null` untuk CREATE, `after=null` untuk DELETE) sehingga pemanggil tidak bisa salah pasang.
3. **Tanpa type-check Model.** CI4 Model tidak punya tipe generik per tabel; `log(Model, data)` menuntut percabangan runtime untuk memutuskan bentuk snapshot. Bentuk per aksi meniadakan percabangan itu.

## Kontrak transaksi (penting)
AuditService **tidak** membuka transaksi sendiri. Service domain (`ReceptionService`) sudah memegang transaksi per operasi, dan memanggil audit di dalamnya — jadi baris audit ikut `transRollback()` saat operasi gagal. Ini diverifikasi `AuditServiceTest::testLogIsDiscardedWhenCallerRollsBack`. Kalau AuditService membuka transaksinya sendiri, log akan ter-commit walau operasi induk batal.

## Perubahan ikutan
- `ReceptionRepository`: `log()` dan `logsOf()` **dihapus** (perantara kehilangan pemanggil); tidak lagi menyuntik AuditLogRepository. Repository kembali murni akses tabel.
- `AuditLogRepository`: konstanta ENTITY_RECEPTION dihapus; tetap `forEntity()`/`record()`.
- `ReceptionService`: `detail()` pakai `audit->forEntity(...)`; create/update pakai `logCreated`/`logUpdated`.
- `docs/conventions.md`: aturan baru — service boleh memanggil service lain, hanya service terluar yang membuka transaksi; AuditService satu-satunya penulis audit.
- Test baru `tests/Feature/AuditServiceTest.php` (6 test): pemetaan tiap aksi, scoping entity, urutan kronologis + actor_name, dan pembatalan saat rollback.

## Verifikasi
Full suite MySQLi **125 test / 397 assertion** hijau; jalur SQLite3 juga hijau. Newman end-to-end **30 request / 75 assertion, 0 gagal** (termasuk assertion `logs[0].action = 'CREATE'`), dijalankan setelah `migrate:refresh` + StockSeeder + DemoUsersSeeder karena run sebelumnya meninggalkan 3 penerimaan di dev DB sehingga assertion kuantitas stok bergeser. Dev DB dikembalikan ke baseline (receptions=0) setelah verifikasi. Kontrak API `logs[]` tidak berubah.

## Jebakan
- Assertion awal saya salah: `$a + $b` pada dua array hasil `forEntity` melakukan union per-key (keduanya indeks 0), bukan menggabung — pakai `assertCount` terpisah.
- Newman assertion stok bergantung pada DB dev yang bersih; reset dulu sebelum menjalankan.
- Pesan commit: hindari `Out-File -Encoding utf8` (menambah BOM); pakai `git commit -m`.
