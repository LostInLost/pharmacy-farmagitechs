# Pharmacy Farmagitechs

Aplikasi pencatatan penerimaan obat dan laporan stok untuk fasilitas kesehatan: PHP + CodeIgniter 4 dengan MySQL, UI jQuery, dan frontend alternatif Astro.

> Setup dan endpoint ada di README ini; pendalaman ada di dokumen berikut.

## Peta Dokumentasi

- [**docs/database.md**](docs/database.md) — [ERD](docs/database.md#diagram-erd), tabel, [kunci & indeks](docs/database.md#kunci-dan-indeks), [model stok](docs/database.md#model-stok), [konsistensi transaksi](docs/database.md#konsistensi-transaksi), [audit trail](docs/database.md#audit-trail).
- [**docs/security.md**](docs/security.md) — [proteksi CSRF](docs/security.md#proteksi-csrf), urutan filter, rute tamu, batas keamanan.
- [**docs/conventions.md**](docs/conventions.md) — [aturan lapisan](docs/conventions.md#lapisan), [struktur proyek](docs/conventions.md#struktur-proyek), gaya kode.
- [**docs/testing.md**](docs/testing.md) — menjalankan test, cakupan, database test, verifikasi manual, asumsi & batasan.
- [**docs/ai-memory.md**](docs/ai-memory.md) — [memori AI](.harness/memory) dan [ringkasan proses pengerjaan](docs/ai-memory.md#ringkasan-proses-pengerjaan).
- [**docs/postman.md**](docs/postman.md) — menjalankan collection, [autentikasi & CSRF](docs/postman.md#4-autentikasi-dan-csrf), bentuk respons, aturan validasi payload.
- [**docs/backend-cors.md**](docs/backend-cors.md) — konfigurasi CORS dan endpoint [/api/csrf](docs/backend-cors.md#kenapa-perlu-endpoint-apicsrf).
- [**docs/frontend-theme.md**](docs/frontend-theme.md) — [token tema](docs/frontend-theme.md#token-utama-light) shadcn (warna, tipografi).
- [**frontend/README.md**](frontend/README.md) — [menjalankan Astro](frontend/README.md#menjalankan), halaman, middleware, [verifikasi browser](frontend/README.md#verifikasi-browser-opsional).
- [**tests/README.md**](tests/README.md) — [menjalankan test](tests/README.md#running-the-tests) (`composer run test` via [scripts/run-tests.php](scripts/run-tests.php)), CI [.github/workflows/phpunit.yml](.github/workflows/phpunit.yml).

## 1. Versi dan Prasyarat

| Komponen | Versi yang dipakai |
| --- | --- |
| PHP | 8.3.33 (minimal 8.2, ekstensi `mysqli` dan `intl` aktif) |
| CodeIgniter | 4.7.4 |
| MySQL | 8.4.3 (minimal 5.7) |
| Composer | 2.x |

Prasyarat lain: ekstensi PHP `mysqli`, `intl`, `mbstring`, dan `json` aktif. Aplikasi dijalankan lokal tanpa deployment khusus.

## 2. Diagram Database dan Urutan Setup Skema

Diagram ERD Mermaid ada di [`docs/database.md`](docs/database.md), lengkap dengan penjelasan [primary key, foreign key, unique constraint, dan indeks](docs/database.md#kunci-dan-indeks), [model stok](docs/database.md#model-stok), serta [aturan konsistensi transaksi](docs/database.md#konsistensi-transaksi).

Urutan pembuatan skema dari database kosong:

1. Buat database kosong: `CREATE DATABASE pharmacy_farmagitechs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Jalankan migrasi aplikasi: `php spark migrate`
3. Muat data awal (seed): `php spark db:seed StockSeeder`
4. Buat dua akun demo: `php spark db:seed DemoUsersSeeder`

Catatan: isi `app/Database/seed_farmasi.sql` (3 pemasok, 25 obat, 10 batch awal, 3 pemakaian) sudah dipindah ke `StockSeeder` — tidak perlu impor SQL manual, aman dijalankan berulang, dan ledger `stock_movements` ikut disinkronkan (baris receipt penerimaan nyata dibiarkan utuh).

### Script `composer` untuk database

Langkah 2–4 di atas dirangkai menjadi script `composer` supaya setup cukup satu perintah (analog `pnpm run`):

| Script | Isi |
| --- | --- |
| `composer db:migrate` | `php spark migrate` |
| `composer db:seed` | `StockSeeder` lalu `DemoUsersSeeder` |
| `composer db:bootstrap` | `db:migrate` + `db:seed` — **cukup ini dari database kosong** |
| `composer db:refresh` | `migrate:refresh` + `db:seed` — kembalikan baseline setelah run Postman |

Contoh dari database kosong:

```
composer install
composer db:bootstrap
```

`db:refresh` menghapus seluruh tabel lalu membangunnya ulang, jadi tolak dijalankan saat `CI_ENVIRONMENT = production`. Semua script berhenti pada langkah pertama yang gagal — ini perlu karena `spark` sendiri selalu keluar dengan kode `0` walau command-nya gagal (exception ditangkap, trace dicetak, lalu `EXIT_SUCCESS` dikembalikan), sehingga rantai `composer run` biasa akan melanjutkan seeder di atas database yang belum siap dan melaporkan sukses palsu.

## 3. Cara Menjalankan Aplikasi

1. Salin `.env.example` menjadi `.env` (`copy .env.example .env` di Windows, `cp .env.example .env` di Linux/macOS). Semua baris di berkas contoh masih dikomentari, jadi hapus tanda `#` pada baris yang dipakai, lalu sesuaikan bagian database:

   ```
   database.default.hostname = localhost
   database.default.database = pharmacy_farmagitechs
   database.default.username = <user_database_anda>
   database.default.password = <kata_sandi_anda>
   database.default.DBDriver = MySQLi
   database.default.port = 3306
   ```

2. Install dependensi: `composer install`
3. Siapkan skema dan data awal: `composer db:bootstrap` (setara migrasi + dua seeder pada bagian 2).
4. Jalankan server lokal: `php spark serve --port 8080`
5. Buka `http://localhost:8080`.

Kata sandi database tidak dicantumkan di repositori ini. Isi sesuai konfigurasi lokal Anda.

## 4. Akun Demo dan Autentikasi

Dua akun dibuat oleh `DemoUsersSeeder`:

| Nama | Username | Email | Kata sandi | Peran |
| --- | --- | --- | --- | --- |
| Rina Supervisor | `supervisor` | `supervisor@farmagitechs.test` | `supervisor123` | Supervisor farmasi |
| Dewi Petugas | `petugas` | `petugas@farmagitechs.test` | `petugas123` | Petugas penerimaan |

- Kata sandi disimpan sebagai hash Argon2id (`Config\Hash`; bisa diubah lewat `hash.algo` di `.env`, fallback bcrypt). `email` wajib dan unik.
- Login `POST /api/login` (JSON), logout `POST /logout` (web) atau `POST /api/logout`. Identitas pembuat/pengubah selalu diambil dari session, bukan body request.

## 5. Endpoint Utama

| Metode | Path | Fungsi | Autentikasi |
| --- | --- | --- | --- |
| POST | `/api/login` | Login, mengembalikan JSON | Tidak perlu (ditolak `403` bila sudah login) |
| POST | `/api/logout` | Logout | Session |
| GET | `/api/csrf` | Token CSRF untuk klien lintas origin (frontend Astro) | Tidak perlu |
| GET | `/api/me` | Identitas sesi + `permissions` role (dipakai middleware SSR Astro) | Session |
| GET | `/api/receipts` | Daftar seluruh penerimaan | Session |
| POST | `/api/receipts` | Membuat penerimaan beserta seluruh item | Session |
| GET | `/api/receipts/{id}` | Detail satu penerimaan | Session |
| PUT | `/api/receipts/{id}` | Memperbarui penerimaan (keadaan akhir lengkap) | Session |
| GET | `/api/stocks?on_date=YYYY-MM-DD` | Laporan stok per obat dan batch — satu daftar `batches` ber-flag `is_expired` (satu query agregat) | Session |
| GET | `/api/medicines?q=&status=` | Master obat: seluruh katalog (`status` = `all`/`active`/`inactive`) | Session |
| POST | `/api/medicines` | Menambah obat | Session + supervisor |
| GET | `/api/medicines/{id}` | Detail satu obat beserta riwayat aksinya (`logs`) | Session |
| PUT | `/api/medicines/{id}` | Mengubah obat, termasuk status aktif/nonaktif | Session + supervisor |
| GET | `/api/references/suppliers` | Dropdown pemasok aktif (`id`, `name`) | Session |
| GET | `/api/references/medicines` | Dropdown obat aktif (`id`, `name`, `unit`) | Session |
| GET | `/api/references/batches` | Dropdown batch dari ledger (`medicine_id`, `batch_no`, `expires_on`) | Session |

Detail perilaku API — hak akses & policy, CSRF, validasi payload, permissions, dan audit trail — ada di [`docs/security.md`](docs/security.md), [`docs/database.md`](docs/database.md), dan [`docs/postman.md`](docs/postman.md).

Panduan lain: [struktur proyek & lapisan](docs/conventions.md#struktur-proyek), [pengujian](docs/testing.md), [Postman](docs/postman.md), [frontend Astro](frontend/README.md).

Contoh request membuat penerimaan (setelah login, kirim cookie session):

```
POST /api/receipts
Content-Type: application/json

{
  "reference_no": "PB-001",
  "supplier_id": 1,
  "received_at": "2026-10-03T10:00:00+07:00",
  "items": [
    {"medicine_id": 101, "batch_no": "PCT-2601", "expires_on": "2027-12-31", "quantity": 10},
    {"medicine_id": 104, "batch_no": "IBU-2602", "expires_on": "2028-06-30", "quantity": 5}
  ]
}
```

Contoh request laporan stok:

```
GET /api/stocks?on_date=2026-10-03
```

## 6. Pengujian dan Verifikasi

Pengujian otomatis: `composer run test` (PHPUnit 10 lewat `scripts/run-tests.php`; memakai database `pharmacy_farmagitechs_test` sehingga data development tidak tersentuh, dan jatuh ke SQLite3 `:memory:` bila `.env` tidak ada). Hasil run terakhir: **161 test, 561 assertion, hijau** (2026-10-07). Cakupan per fitur: [docs/testing.md](docs/testing.md#cakupan); cara membuat test baru: [tests/README.md](tests/README.md#running-the-tests).

Verifikasi manual skenario inti:

1. Dari database kosong: `composer db:bootstrap`.
2. Jalankan `composer run test` — semua test harus lulus.
3. `php spark migrate:rollback` lalu `php spark migrate` — migrasi turun dan naik bersih.
4. Tanpa login: `/receptions` dialihkan ke `/login`; `GET /api/stocks` dijawab `401` JSON.
5. `POST /api/receipts` tanpa header `X-CSRF-TOKEN` dijawab `403` JSON `"error": "csrf"` walau sudah login; ulangi dengan token dari cookie `csrf_cookie_name` → lolos ke validasi.
6. Login lalu buka `/login` → `403` halaman "Sudah Masuk"; `POST /api/login` saat sesi aktif → `403` JSON.
7. `php spark routes` — pastikan filter `auth`/`guest` terpasang pada seluruh path.

Angka baseline seed (`on_date=2026-10-03`) dapat diperiksa langsung lewat `GET /api/stocks?on_date=2026-10-03`: obat 101 tersedia 134 (fisik 142, kedaluwarsa 8), 102 = 16, 103 = 15, 104 = 3, 106 = 0, dan 107 tersedia 0 dari fisik 6.

Asumsi dan batasan solusi:

- Batch dengan jumlah fisik nol tetap ditampilkan agar jejak batch tidak hilang; total tetap benar. Alasan: [docs/database.md](docs/database.md#sumber-tulis).
- Penghapusan penerimaan (DELETE) tidak disediakan; perubahan lewat `PUT` dengan daftar item sebagai keadaan akhir lengkap — mengirim `PUT` identik dua kali tidak menggandakan stok.
- `created_by` tidak pernah berubah; `updated_by` bernilai `NULL` selama penerimaan belum pernah diubah. Alasan: [docs/database.md](docs/database.md#konvensi).
- Stok adalah agregasi data tersimpan, bukan laporan historis: `on_date` hanya menentukan status kedaluwarsa.
- Daftar penerimaan dan laporan stok mengembalikan seluruh baris tanpa paginasi — untuk jumlah data besar, penambahan paginasi belum dilakukan.

Catatan lengkap (database test, cakupan, batasan lain): [docs/testing.md](docs/testing.md).

## 7. Postman Collection

Berkas: [`postman/Pharmacy-Farmagitechs.postman_collection.json`](postman/Pharmacy-Farmagitechs.postman_collection.json) (45 request, 111 assertion, 6 folder) dan [`postman/Local.postman_environment.json`](postman/Local.postman_environment.json) (environment `Pharmacy Farmagitechs - Local`, berisi `base_url` — default `http://localhost:8080`).

Menjalankan lewat CLI (Newman 6.2.2 sudah tersedia di `node_modules/`):

```
node node_modules/newman/bin/newman.js run postman/Pharmacy-Farmagitechs.postman_collection.json -e postman/Local.postman_environment.json
```

Lewat aplikasi Postman: import kedua berkas, pilih environment `Pharmacy Farmagitechs - Local`, lalu jalankan folder **berurutan**: `0. Bootstrap CSRF` → `1. Auth` → `2. Stocks` → `3. Receipts` → `4. Medicines` → `5. Unauthenticated`. Item `0.1` wajib jalan lebih dulu karena menerbitkan cookie CSRF.

Autentikasi: sesi cookie `ci_session` dari `POST /api/login` (dua akun demo di [§4](#4-akun-demo-dan-autentikasi)). Seluruh `POST`/`PUT` wajib header `X-CSRF-TOKEN`; token berotasi setiap mutasi sukses dan script level collection mengurusnya otomatis — jangan di-hardcode. Run penuh mengubah data development (membuat `PB-001`, menambah obat uji); kembalikan baseline dengan `composer db:refresh` sebelum run berikutnya.

Peta folder, variabel, skenario uji end-to-end, dan bentuk respons: [docs/postman.md](docs/postman.md).

## Catatan Alat AI dan Referensi

- **Tools**: Deepseek Harness dengan beberapa plugin (persistent memory, MCP) untuk membantu AI mengenal konteks proyek.
- **Riwayat memori**: 63 catatan keputusan, pelajaran, dan progres tersimpan di [`.harness/memory/`](.harness/memory) — diringkas di [`docs/ai-memory.md`](docs/ai-memory.md).
- **Model planning**: Muse 1.3 Spark, Mimo V2.6 Pro, Kimi K3.
- **Model eksekutor**: Deepseek V4.1 Flash, GLM 5.3 Flash, Mimo V2.6 Flash, Kimi 2.7 Code.
- **Referensi**: dokumentasi resmi CodeIgniter 4 (backend) dan Astro (frontend, lihat [`frontend/README.md`](frontend/README.md)). Keputusan desain diverifikasi manual; detail tercatat di riwayat commit dan berkas pada `docs/`.
