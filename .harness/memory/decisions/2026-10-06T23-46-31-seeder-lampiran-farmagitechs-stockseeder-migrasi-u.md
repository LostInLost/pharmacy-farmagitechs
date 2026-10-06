---
title: "Seeder lampiran farmagitechs: StockSeeder + migrasi usage details"
type: decision
summary: "Lampiran seed_farmasi.sql dipindahkan ke StockSeeder (menggantikan ProvisionalStockSeeder), plus migrasi kolom used_at/unit_name dan penyelarasan fixture/test/Postman"
tags: ["seeder", "lampiran", "stock", "migrasi", "fixture", "postman"]
source: "commit bed2677 + verifikasi test suite"
confidence: high
scope: project
created_at: "2026-10-06T23:46:31Z"
updated_at: "2026-10-06T23:46:31Z"
---

## Konteks

Lampiran `app/Database/seed_farmasi.sql` (99 baris, MySQL) tersedia dan berisi data resmi: 3 suppliers, 25 medicines (id 101-125), 10 seed_batch_stock, 3 stock_usage. Isinya sudah dipindahkan ke `StockSeeder` (commit bed2677), menggantikan `ProvisionalStockSeeder` yang datanya karangan.

## Keputusan

1. **Nama seeder `StockSeeder`**, file provisional dihapus. `DemoUsersSeeder` tidak disentuh (akun supervisor/petugas).
2. **Strategi idempotensi beda per tabel**: `suppliers` + `medicines` di-`sync()` per id (UPDATE bila id sudah ada, INSERT bila belum) karena dirujuk FK; `seed_batch_stock` + `stock_usage` di-`reload()` (emptyTable + insertBatch) supaya stok penerimaan lama tidak menumpuk. Seeder aman dijalankan berulang, dibungkus `transBegin`/`transCommit`.
3. **Migrasi `2026-10-06-000010_AddUsageDetailsToStockUsage`** menambah `used_at DATETIME NULL` + `unit_name VARCHAR(100) NULL` ke `stock_usage` (nullable agar insert tanpa kolom itu tetap sah). Lampiran punya kolom ini, skema lama tidak.
4. **`StockFixture` diselaraskan ke data lampiran** (obat 101-107 + 3 supplier + 3 usage row) sehingga angka soal tetap konsisten: 102 = 20-4 = 16. `ReferenceApiTest` ikut disesuaikan (supplier jadi 'Farma Nusantara'/'Medika Sentosa', urutan obat aktif `[102, 106, 104, 107, 101, 103]`).
5. **Postman `AMX-2601` expiry diubah 2027-06-30 → 2027-05-31** (2 tempat: item 3.13 dan 3.16). Validator `ReceptionValidator::knownExpiry()` membandingkan dengan `seed_batch_stock`, jadi expiry berbeda = 422 `batch_expiry_conflict`.

## Angka soal terverifikasi (on_date 2026-10-03)

101 = 142 fisik / 134 tersedia / 8 kedaluwarsa; 102 = 16; 103 = 15; 104 = 3; 106 = 0; 107 = 6 fisik / 0 tersedia / 6 kedaluwarsa; 108 = 12; 112 = 9; 118 = 7. Obat aktif = 22 dari 25 (105, 124, 125 nonaktif).

## Verifikasi

`StockSeederTest` (8 test, 50 assertion) menguji service DAN endpoint HTTP `/api/stocks` + `/api/references/*` terhadap seeder asli. Suite penuh: 101 test / 292 assertion lulus di MySQL.

## Catatan lingkungan

MySQL Laragon ternyata JALUR AKTIF meski `Get-Process mysqld` dan probe port 3306 kosong — probe itu menyesatkan; `spark db:probe` menunjukkan driver MySQLi/`pharmacy_farmagitechs` hidup dan terisi. Env override `database.default.*` TIDAK berlaku untuk spark (`.env` menang), tapi `database.tests.*` bisa di-override untuk PHPUnit.
