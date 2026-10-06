# Pharmacy Farmagitechs

Aplikasi pencatatan penerimaan obat dan laporan stok untuk fasilitas kesehatan. Dibuat sebagai jawaban Tes Fullstack Web Developer PT Farma Global Teknologi.

## 1. Versi dan Prasyarat

| Komponen | Versi yang dipakai |
| --- | --- |
| PHP | 8.3.33 (minimal 8.2, ekstensi `mysqli` dan `intl` aktif) |
| CodeIgniter | 4.7.4 |
| MySQL | 8.4.3 (minimal 5.7) |
| Composer | 2.x |

Prasyarat lain: ekstensi PHP `mysqli`, `intl`, `mbstring`, dan `json` aktif. Aplikasi dijalankan lokal tanpa deployment khusus.

## 2. Diagram Database dan Urutan Setup Skema

Diagram ERD Mermaid ada di [`docs/database.md`](docs/database.md), lengkap dengan penjelasan primary key, foreign key, unique constraint, indeks, model stok, dan aturan konsistensi transaksi.

Urutan pembuatan skema dari database kosong:

1. Buat database kosong: `CREATE DATABASE pharmacy_farmagitechs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Jalankan migrasi aplikasi: `php spark migrate`
3. Muat data awal lampiran (bila tersedia): `mysql -u <user> -p pharmacy_farmagitechs < Lampiran/seed_farmasi.sql`
4. Buat dua akun demo: `php spark db:seed DemoUsersSeeder`

Catatan: tabel `suppliers`, `medicines`, `seed_batch_stock`, dan `stock_usage` dibuat oleh migrasi sebagai skema provisional. Saat `Lampiran/seed_farmasi.sql` tersedia, impor file tersebut menggantikan isi keempat tabel tanpa mengubah struktur tabel transaksi.

## 3. Cara Menjalankan Aplikasi

1. Salin `env` menjadi `.env`, lalu sesuaikan bagian database:

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

Kata sandi disimpan sebagai hash Argon2id (`Config\Hash`, dapat diubah lewat `hash.algo` di `.env`; otomatis jatuh ke bcrypt bila Argon2 tidak tersedia di mesin tersebut). `email` wajib dan unik agar alur pemulihan kata sandi berbasis email dapat ditambahkan nanti; fitur pemulihan itu sendiri di luar cakupan tes. Autentikasi memakai session CodeIgniter 4: login lewat `POST /login` (form web) atau `POST /api/login` (JSON), logout lewat `GET /logout` atau `POST /api/logout`. Identitas pembuat/pengubah selalu diambil server dari session, bukan dari body request.

## 5. Endpoint Utama

| Metode | Path | Fungsi | Autentikasi |
| --- | --- | --- | --- |
| POST | `/api/login` | Login, mengembalikan JSON | Tidak perlu |
| POST | `/api/logout` | Logout | Session |
| GET | `/api/receipts` | Daftar seluruh penerimaan | Session |
| POST | `/api/receipts` | Membuat penerimaan beserta seluruh item | Session |
| GET | `/api/receipts/{id}` | Detail satu penerimaan | Session |
| PUT | `/api/receipts/{id}` | Memperbarui penerimaan (keadaan akhir lengkap) | Session |
| GET | `/api/stocks?on_date=YYYY-MM-DD` | Laporan stok per obat dan batch | Session |

Status implementasi: seluruh endpoint sudah berfungsi penuh. Autentikasi memakai session cookie (`ci_session`), sehingga request berikutnya setelah login cukup mengirim cookie tersebut. Request tanpa login ditolak: `401` JSON untuk path `/api/*` dan redirect ke `/login` untuk halaman web.

Aturan hak ubah: petugas penerimaan hanya dapat mengubah penerimaan yang ia buat; supervisor dapat mengubah semua. Pelanggaran mengembalikan `403` tanpa mengubah penerimaan, stok, maupun log aksi. Identitas pembuat/pengubah diambil server dari sesi, bukan dari body request.

Validasi penerimaan yang berlaku: `reference_no` wajib dan unik; pemasok dan obat harus ada serta aktif; `items` minimal satu baris; `quantity` bilangan bulat positif; kombinasi `(medicine_id, batch_no)` hanya sekali per penerimaan; `expires_on` konsisten untuk batch yang sama; dan `expires_on` harus lebih akhir daripada tanggal penerimaan (zona Asia/Jakarta). Kegagalan mengembalikan `422` dengan daftar pesan, dan seluruh perubahan dibatalkan.

CSRF aktif untuk form web dan dikecualikan untuk `/api/*` agar endpoint dapat diuji langsung dari Postman.

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

Pengujian otomatis (37 test, 93 assertion):

```
vendor/bin/phpunit
```

Mencakup: skenario 1-5 soal, angka laporan stok contoh soal, batas `expires_on` sama dengan `on_date`, isolasi rollback, stamping timestamp, autentikasi, dan helper hash. Test berjalan pada database `pharmacy_farmagitechs_test` (lihat `database.tests.*` di `.env`), sehingga tidak menyentuh data development.

Verifikasi manual:

1. Dari database kosong, jalankan `php spark migrate`, `php spark db:seed DemoUsersSeeder`, lalu `php spark db:seed ProvisionalStockSeeder`.
2. Jalankan `vendor/bin/phpunit`; semua test harus lulus.
3. Jalankan `php spark migrate:rollback` lalu `php spark migrate` untuk memastikan migrasi turun dan naik bersih.
4. Buka `/receptions` tanpa login; harus redirect ke `/login`. Akses `/api/stocks` tanpa login; harus `401` JSON.
5. Jalankan `php spark routes` untuk memastikan seluruh path terdaftar dengan filter `auth`.

Asumsi dan batasan saat ini:

- Lampiran `Lampiran/seed_farmasi.sql` belum tersedia; `ProvisionalStockSeeder` memuat angka yang disebut soal (obat 101, 102, 103, 104, 106, 107) agar perhitungan stok dapat diverifikasi. Saat lampiran asli diterima, impor menggantikan isi tabel seed dan seeder provisional dilewati otomatis.
- Obat di luar daftar contoh soal (105, dan obat lain dari 25 katalog) belum ada di seed provisional.
- UI menyediakan 4 tampilan wajib (login, daftar/detail penerimaan, form penerimaan, daftar stok). Filter tanggal `on_date` di UI tersedia di halaman stok; angka contoh soal paling akurat diverifikasi lewat API.

## 7. Postman Collection

Tersedia di [`postman/`](postman):

- `Pharmacy-Farmagitechs.postman_collection.json` — 23 request dalam 4 folder
- `Local.postman_environment.json` — variabel `base_url` (`http://localhost:8080`), `receipt_id`, `foreign_receipt_id`

Cara menjalankan:

1. Jalankan aplikasi (`php spark serve --port 8080`) dengan database baseline.
2. Di Postman: Import kedua berkas, pilih environment `Pharmacy Farmagitechs - Local`.
3. Jalankan folder secara berurutan: **1. Auth** → **2. Stocks** → **3. Receipts** → **4. Unauthenticated**.

Urutan penting: folder **2. Stocks** memeriksa angka baseline sehingga harus dijalankan sebelum ada penerimaan baru, dan folder **4. Unauthenticated** sengaja memakai cookie tidak valid sehingga dijalankan paling akhir.

Autentikasi memakai cookie session: request `1.4 Login petugas` menyimpan `ci_session` otomatis, dan Postman mengirimkannya pada request berikutnya. Jalur CLI:

```
node node_modules/newman/bin/newman.js run postman/Pharmacy-Farmagitechs.postman_collection.json -e postman/Local.postman_environment.json
```

## Struktur Proyek

```
app/
  Config/         Konfigurasi, peta permission hardcoded, konfigurasi hash
  Controllers/    Api/ dan Web/ hanya menerjemahkan HTTP
  Database/       Migrations/ dan Seeds/
  Filters/        AuthFilter (menolak request tanpa login)
  Models/         CRUD tipis + model event stamping timestamp
  Policies/       Keputusan hak ubah
  Repositories/   Query database
  Services/       Logika transaksi
  Validation/     Aturan validasi payload
  Views/          Tampilan web
  Helpers/        Helper lintas lapisan (Hash)
docs/             Dokumentasi database dan konvensi kode
postman/          Postman collection dan environment
public/assets/    CSS dan JS untuk UI
tests/            Test otomatis (Feature, database, unit)
```

Aturan lapisan ada di [`docs/conventions.md`](docs/conventions.md).

## Catatan Alat AI dan Referensi

Solusi ini disusun dengan bantuan AI (CodeBuddy) dan referensi dokumentasi resmi CodeIgniter 4. Keputusan desain, alasan pemilihan model stok, dan peta hak akses diverifikasi dan disesuaikan manual; detail keputusan tercatat di riwayat commit dan dokumentasi pada folder `docs/`.
