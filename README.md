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
3. Jalankan migrasi dan seeder sesuai urutan pada bagian 2.
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
| GET | `/api/stocks?on_date=YYYY-MM-DD` | Laporan stok per obat dan batch (satu query agregat) | Session |
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

## Catatan Alat AI dan Referensi

- **Tools**: Deepseek Harness dengan beberapa plugin (persistent memory, MCP) untuk membantu AI mengenal konteks proyek.
- **Riwayat memori**: 45 catatan keputusan, pelajaran, dan progres tersimpan di [`.harness/memory/`](.harness/memory) — diringkas di [`docs/ai-memory.md`](docs/ai-memory.md).
- **Model planning**: Muse 1.3 Spark, Mimo V2.6 Pro, Kimi K3.
- **Model eksekutor**: Deepseek V4.1 Flash, GLM 5.3 Flash, Mimo V2.6 Flash, Kimi 2.7 Code.
- **Referensi**: dokumentasi resmi CodeIgniter 4 (backend) dan Astro (frontend, lihat [`frontend/README.md`](frontend/README.md)). Keputusan desain diverifikasi manual; detail tercatat di riwayat commit dan berkas pada `docs/`.
