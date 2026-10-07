# Pengujian dan Verifikasi

> Bagian dari [README](../README.md). Lihat juga [Konvensi Kode](conventions.md#lapisan), [Desain Database](database.md#konsistensi-transaksi), [Postman Collection](postman.md) untuk uji API, dan [Memori AI](ai-memory.md) untuk riwayat proses.

## Menjalankan test otomatis

```
composer run test
```

- 161 test, 561 assertion (hasil run terakhir 2026-10-07).
- `composer run test` memakai `scripts/run-tests.php`: interpreter PHP pemanggil Composer, argumen diteruskan ke PHPUnit (mis. `composer run test -- --filter HashTest`), binary PHPUnit dicari mengikuti aturan Composer.
- Skrip menjalankan PHPUnit persis seperti `vendor/bin/phpunit` (konfigurasi dan fallback SQLite3 `:memory:` tetap berlaku); bila `mysqli` tidak aktif, test diulang dengan `-d extension=mysqli`. Alternatif langsung: `vendor/bin/phpunit`.
- Quick-start PHPUnit (konfigurasi, cara membuat test baru): [`tests/README.md`](../tests/README.md#running-the-tests).

## Cakupan

- Skenario inti penerimaan (buat, ubah, kirim ulang identik, atribusi pembuat/pengubah, hak akses) dan angka laporan stok baseline.
- Batas `expires_on` sama dengan `on_date`; ledger `stock_movements` (write-through, backfill seeder, flag `is_expired`); isolasi rollback; stamping timestamp.
- Autentikasi, proteksi CSRF (token wajib, rotasi, tolak pakai ulang), dan filter `guest`.
- Audit trail (snapshot before/after, kunci i18n); endpoint references; `can_update` di daftar penerimaan.
- Master obat: hak baca petugas vs tulis supervisor, `can_write` pada respons baca, kode unik case-insensitive, pencarian/filter status, wildcard `%` aman, efek nonaktif ke dropdown; riwayat aksi audit di detail (`data.logs`, urut kronologis, before/after) sementara daftar tetap tanpa log, dan tulis yang ditolak (`403`/`404`/`422`) tidak meninggalkan baris audit; serta helper hash.

## Database test

- Default: `pharmacy_farmagitechs_test` (lihat `database.tests.*` di `.env`), sehingga data development tidak tersentuh.
- Tanpa `.env` (mis. CI): fallback SQLite3 `:memory:`; migrasi dan query dijaga portabel agar kedua driver lulus.
- Laporan coverage tidak diaktifkan di `phpunit.dist.xml`; jalankan `vendor/bin/phpunit --coverage-text` bila driver Xdebug/PCOV tersedia.
- CI: [`.github/workflows/phpunit.yml`](../.github/workflows/phpunit.yml) (matrix PHP 8.2 dan 8.5 dengan Xdebug).

## Verifikasi manual

1. Dari database kosong: `php spark migrate`, `php spark db:seed StockSeeder`, `php spark db:seed DemoUsersSeeder`.
2. Jalankan `composer run test`; semua test harus lulus.
3. `php spark migrate:rollback` lalu `php spark migrate` untuk memastikan migrasi turun dan naik bersih.
4. Buka `/receptions` tanpa login → redirect ke `/login`. Akses `/api/stocks` tanpa login → `401` JSON.
5. `POST /api/receipts` tanpa header `X-CSRF-TOKEN` → `403` JSON `"error": "csrf"` walau sudah login; ulangi dengan token dari cookie `csrf_cookie_name` (dari `GET /login`) → lolos ke validasi (`422`).
6. Login lalu buka `/login` → `403` halaman "Sudah Masuk"; `POST /api/login` dengan sesi aktif → `403` JSON `{"message": "Sudah masuk."}`.
7. `php spark routes` untuk memastikan filter `auth`/`guest` terpasang pada seluruh path.

## Asumsi dan batasan

- Data awal berasal dari berkas seed `app/Database/seed_farmasi.sql`, dipindahkan ke `StockSeeder`. Jalankan seeder itu sebelum memakai angka baseline (obat 101, 102, 103, 104, 106, 107); aman dijalankan ulang.
- UI menyediakan 4 tampilan utama (login, daftar/detail penerimaan, form penerimaan, daftar stok). Filter tanggal `on_date` dan status batch (semua/tersedia/kedaluwarsa) ada di halaman stok; angka baseline paling akurat diverifikasi lewat API.
