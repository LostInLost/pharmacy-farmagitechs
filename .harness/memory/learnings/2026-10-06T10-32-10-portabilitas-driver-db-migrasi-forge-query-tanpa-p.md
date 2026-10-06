---
title: "Portabilitas driver DB: migrasi Forge & query tanpa prefix"
type: learning
summary: "Fallback SQLite3 menyingkap dua bug MySQL-only: migrasi 000009 (information_schema + ALTER TABLE mentah) dan raw SQL StockRepository tanpa prefix tabel. Perbaikan: Forge API + Query Builder unionAll/fromSubquery (commit 8babe71)."
tags: ["database", "sqlite3", "mysqli", "migration", "forge", "query-builder", "portabilitas"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T10:32:10Z"
updated_at: "2026-10-06T10:32:10Z"
---

## Konteks
- `Config\Database::$tests` CI4 4.7.4 memakai SQLite3 `:memory:` sebagai fallback saat `.env` tidak ada. Di repo ini `.env` ada → MySQLi.
- `$namespace = null` menjalankan migrasi semua namespace; `$refresh = true` me-rollback ke versi 0, jadi `down()` **ikut dieksekusi** tiap test. Portabilitas `up()` dan `down()` sama-sama wajib.
- `.github/workflows/phpunit.yml` tidak membuat `.env` dan tidak memasang mysqli → memang mengandalkan SQLite3.

## Bug 1 — migrasi 2026-10-05-000009
- Gejala: `SQLite3Exception: Unable to prepare statement: no such table: information_schema.TABLE_CONSTRAINTS` → 34 error, exit 2.
- Sebab: query `information_schema.TABLE_CONSTRAINTS` + `ALTER TABLE ... DROP FOREIGN KEY` / `ADD CONSTRAINT` = MySQL-only.
- Perbaikan (Forge API, bukan SQL mentah):
  ```php
  foreach ($this->db->getForeignKeyData('reception_logs') as $name => $fk) {
      if (in_array('reception_id', (array) $fk->column_name, true)) {
          $this->forge->dropForeignKey('reception_logs', $name);
      }
  }
  $this->forge->addForeignKey('reception_id', 'receptions', 'id', 'CASCADE', $onDelete);
  $this->forge->processIndexes('reception_logs');
  ```
- Catatan: `SQLite3\Forge::addForeignKey()` **melempar** `DatabaseException` bila diberi nama FK eksplisit. `processIndexes()` adalah cara portabel menambah FK setelah tabel dibuat. Penamaan FK bawaan Forge: `{table}_{field}_foreign`.

## Bug 2 — StockRepository::batches()
- Gejala: `no such table: stock_usage` → 14 error.
- Sebab: raw SQL menyebut `seed_batch_stock`, `reception_items`, `stock_usage` tanpa prefix tabel. `DBPrefix` kosong di MySQL membuat bug tak terlihat; fallback SQLite memakai prefix `db_`.
- Perbaikan: Query Builder `unionAll()` + `fromSubquery()` (`BaseBuilder.php:614, 1231`) → prefix dan escaping ditangani driver. Terverifikasi hasilnya identik dengan raw SQL di kedua driver (probe berfixture, bukan sekadar kompilasi SQL).

## Fakta pendukung (terverifikasi)
- Peta driver→ekstensi di `Database.php`: `MySQLi => mysqli`, `SQLite3 => sqlite3`. Pesan gagalnya: `The required PHP extension "X" is not loaded.`
- `db_connect()` mengembalikan **instance bersama**, bukan koneksi baru (`Config::connect()` menyimpan cache `static::$instances`, `getShared = true`). Terbukti: `db_connect() === db_connect() === $model->db`.
- Selisih 1 assertion antar driver berasal dari `HealthTest::testBaseUrlHasBeenSet` (2 assertion saat `.env` ada, 1 saat tidak ada) — bukan efek perubahan ini.

## Hasil
- MySQLi: `OK (68 tests, 159 assertions)`. Fallback SQLite3: `OK (68 tests, 158 assertions)`.
- Commit `8babe71`.
