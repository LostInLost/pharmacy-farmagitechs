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
3. Muat data awal lampiran: `php spark db:seed StockSeeder`
4. Buat dua akun demo: `php spark db:seed DemoUsersSeeder`

Catatan: isi lampiran `app/Database/seed_farmasi.sql` (3 pemasok, 25 obat, 10 batch stok awal, 3 baris pemakaian) sudah dipindahkan ke `StockSeeder`, sehingga tidak perlu impor SQL manual. Seeder mencocokkan `suppliers` dan `medicines` per `id`, lalu memuat ulang `seed_batch_stock` dan `stock_usage` agar aman dijalankan berulang. Ledger `stock_movements` ikut disinkronkan untuk baris seed/usage, sedangkan baris receipt milik penerimaan nyata dibiarkan utuh.

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

Kata sandi disimpan sebagai hash Argon2id (`Config\Hash`, dapat diubah lewat `hash.algo` di `.env`; otomatis jatuh ke bcrypt bila Argon2 tidak tersedia di mesin tersebut). `email` wajib dan unik agar alur pemulihan kata sandi berbasis email dapat ditambahkan nanti; fitur pemulihan itu sendiri di luar cakupan tes. Autentikasi memakai session CodeIgniter 4: login lewat `POST /api/login` (JSON, dipakai form login via jQuery), logout lewat `POST /logout` (form web) atau `POST /api/logout`. Identitas pembuat/pengubah selalu diambil server dari session, bukan dari body request.

## 5. Endpoint Utama

| Metode | Path | Fungsi | Autentikasi |
| --- | --- | --- | --- |
| POST | `/api/login` | Login, mengembalikan JSON | Tidak perlu (ditolak `403` bila sudah login) |
| POST | `/api/logout` | Logout | Session |
| GET | `/api/receipts` | Daftar seluruh penerimaan | Session |
| POST | `/api/receipts` | Membuat penerimaan beserta seluruh item | Session |
| GET | `/api/receipts/{id}` | Detail satu penerimaan | Session |
| PUT | `/api/receipts/{id}` | Memperbarui penerimaan (keadaan akhir lengkap) | Session |
| GET | `/api/stocks?on_date=YYYY-MM-DD` | Laporan stok per obat dan batch | Session |
| GET | `/api/references/suppliers` | Dropdown pemasok aktif (`id`, `name`) | Session |
| GET | `/api/references/medicines` | Dropdown obat aktif (`id`, `name`, `unit`) | Session |

Halaman web (`/login`, `/receptions`, `/receptions/new`, `/receptions/{id}/edit`, `/stocks`) tidak mengambil data sendiri: controller Web hanya merender cangkang + objek `window.FARMASI_BOOT` (endpoint, string bahasa), dan jQuery di `public/assets/js/` (bootstrap `app.js`, pustaka bersama `lib/`, satu file per halaman di `pages/`) memanggil endpoint di atas. `GET /api/receipts` menyertakan `can_update` per baris agar UI tahu kapan menampilkan tombol Ubah; penegakan hak tetap di server (`ReceptionPolicy` via service).

Status implementasi: seluruh endpoint sudah berfungsi penuh. Autentikasi memakai session cookie (`ci_session`), sehingga request berikutnya setelah login cukup mengirim cookie tersebut. Request tanpa login ditolak: `401` JSON untuk path `/api/*` dan redirect ke `/login` untuk halaman web. Sebaliknya, rute tamu (`GET /login` dan `POST /api/login`) dilindungi filter `guest`: pengguna yang sudah login menerima `403` — halaman error "Sudah Masuk" berisi tautan ke `/receptions` untuk web, JSON `{"message": "..."}` (beserta header `X-CSRF-TOKEN` terbaru) untuk `/api/*`.

Aturan hak ubah: petugas penerimaan hanya dapat mengubah penerimaan yang ia buat; supervisor dapat mengubah semua. Pelanggaran mengembalikan `403` tanpa mengubah penerimaan, stok, maupun log aksi. Identitas pembuat/pengubah diambil server dari sesi, bukan dari body request.

Validasi penerimaan yang berlaku: `reference_no` wajib dan unik; pemasok dan obat harus ada serta aktif; `items` minimal satu baris; `quantity` bilangan bulat positif; kombinasi `(medicine_id, batch_no)` hanya sekali per penerimaan; `expires_on` konsisten untuk batch yang sama; dan `expires_on` harus lebih akhir daripada tanggal penerimaan (zona Asia/Jakarta). Kegagalan mengembalikan `422` dengan daftar pesan, dan seluruh perubahan dibatalkan.

CSRF aktif untuk **semua** POST/PUT, termasuk `/api/*`. Token dikirim lewat header `X-CSRF-TOKEN`; nilainya sama dengan cookie `csrf_cookie_name` yang diterbitkan saat halaman login dimuat (`GET /login`). Karena itu klien API harus memulai dari `GET /login` untuk mendapatkan cookie tersebut. Token **berotasi** setiap mutasi berhasil, dan nilai terbaru selalu dikembalikan pada header `X-CSRF-TOKEN` di setiap response API — klien wajib memakai nilai terakhir itu untuk request berikutnya. Kegagalan token dijawab `403` JSON berisi `"error": "csrf"` (disertai token segar) untuk `/api/*`, dan redirect kembali ke halaman asal untuk form web. Detail alasan desain ada di [`docs/security.md`](docs/security.md).

Audit trail: setiap aksi buat/ubah menulis satu baris `audit_logs` berisi `entity_type`, `entity_id`, `actor_id`, `action`, dan waktu, ditambah kolom `data_before`/`data_after` (JSON) berisi snapshot penerimaan sebelum dan sesudah perubahan. Kolom snapshot adalah tambahan di atas syarat minimal soal (soal menyebut isi sebelum/sesudah "tidak diwajibkan") dan dipakai agar perubahan yang menggeser stok tetap dapat ditelusuri; tanpa itu, item yang dihapus saat `PUT` hilang tanpa jejak karena `reception_items` selalu diganti penuh. `data_before` bernilai `null` pada `CREATE`, dan `PUT` identik tetap tercatat dengan `data_before == data_after`. Baris audit bertahan saat entitasnya dihapus (`entity_id` polimorfik tanpa FK), berbeda dari `reception_items` yang memakai `CASCADE`. Detail kontrak snapshot ada di [`docs/database.md`](docs/database.md).

Tabel log sengaja **generik** lewat pasangan `entity_type`/`entity_id`, bukan reception-scoped: kolom inti audit (`actor_id`, `action`, waktu, snapshot) sama untuk entitas apa pun, sehingga domain tulis lain (mis. master obat) cukup memakai tabel yang sama tanpa skema baru. `entity_type` berisi `reception` untuk baris penerimaan. Trade-off-nya `entity_id` tidak dapat di-FK karena menunjuk ke banyak tabel — diterima karena jejak audit justru harus hidup ketika datanya dihapus; `actor_id` tetap ber-FK ke `users`.

Kolom `action` menyimpan **token kanonik** (`CREATE`/`UPDATE`/`DELETE`), bukan kalimat siap tampil. Label berbahasa Indonesia/Inggris dirakit di lapisan render: `Reception.log.action_create` dkk. dikirim lewat boot i18n dan JS memetakannya saat menampilkan riwayat aksi (token tak dikenal tampil apa adanya). Dengan begitu data audit tetap stabil lintas bahasa dan tetap nyaman difilter (`WHERE action = 'CREATE'`).

Penulisan audit terpusat di `App\Services\AuditService` (`logCreated`/`logUpdated`/`logDeleted`, plus `forEntity` untuk membaca). Service domain memanggilnya **di dalam transaksinya sendiri**, sehingga baris audit ikut batal ketika operasi gagal dan tidak ada service yang menulis log untuk transaksi milik service lain.

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

Pengujian otomatis (125 test, 397 assertion):

```
composer run test
```

`composer run test` menjalankan `scripts/run-tests.php`, yang memakai interpreter PHP pemanggil Composer dan meneruskan argumen apa pun ke PHPUnit (mis. `composer run test -- --filter HashTest`). Skrip memindahkan direktori kerja ke root proyek agar `phpunit.dist.xml` selalu ditemukan, dan mencari binary PHPUnit mengikuti aturan Composer (`COMPOSER_BIN_DIR`, `config.bin-dir`/`vendor-dir` di `composer.json`, PATH, lalu default `vendor/bin`).

Secara default skrip menjalankan PHPUnit persis seperti `vendor/bin/phpunit`, jadi konfigurasi dan fallback bawaan CodeIgniter (SQLite3 `:memory:` di `Config\Database::$tests`) tetap berlaku. Bila test gagal karena `mysqli` tidak aktif di php.ini, test diulang dengan `-d extension=mysqli`; bila ekstensi itu tetap tidak bisa dimuat, skrip berhenti dengan pesan yang menyebut binary PHP dan php.ini penyebabnya. Alternatif langsung:

```
vendor/bin/phpunit
```

Mencakup: skenario 1-5 soal, angka laporan stok contoh soal, batas `expires_on` sama dengan `on_date`, ledger `stock_movements` (write-through penerimaan, backfill seeder, flag `is_expired`), isolasi rollback, stamping timestamp, autentikasi, proteksi CSRF (token wajib, rotasi, tolak pakai ulang), audit trail (snapshot before/after), endpoint references (hanya aktif, field minimal, butuh login), `can_update` di daftar, dan helper hash. Test berjalan pada database `pharmacy_farmagitechs_test` (lihat `database.tests.*` di `.env`), sehingga tidak menyentuh data development. Tanpa `.env` (mis. di CI), test otomatis memakai fallback SQLite3 `:memory:`; seluruh migrasi dan query aplikasi dijaga tetap portabel agar kedua driver sama-sama lulus. Laporan coverage tidak diaktifkan di `phpunit.dist.xml` agar mesin tanpa driver coverage tidak gagal; jalankan `vendor/bin/phpunit --coverage-text` bila driver Xdebug/PCOV tersedia.

Verifikasi manual:

1. Dari database kosong, jalankan `php spark migrate`, `php spark db:seed StockSeeder`, lalu `php spark db:seed DemoUsersSeeder`.
2. Jalankan `composer run test`; semua test harus lulus.
3. Jalankan `php spark migrate:rollback` lalu `php spark migrate` untuk memastikan migrasi turun dan naik bersih.
4. Buka `/receptions` tanpa login; harus redirect ke `/login`. Akses `/api/stocks` tanpa login; harus `401` JSON.
5. `POST /api/receipts` tanpa header `X-CSRF-TOKEN`; harus `403` JSON berisi `"error": "csrf"` walau sudah login. Ulangi dengan token dari cookie `csrf_cookie_name` (didapat dari `GET /login`); harus lolos ke validasi (`422`).
6. Login, lalu buka `/login`; harus `403` halaman "Sudah Masuk" (bukan redirect). `POST /api/login` dengan sesi aktif harus `403` JSON `{"message": "Sudah masuk."}`.
7. Jalankan `php spark routes` untuk memastikan seluruh path terdaftar: `auth` untuk halaman/endpoint privat dan `guest` untuk `/login` dan `POST /api/login`.

Asumsi dan batasan saat ini:

- Data awal berasal dari lampiran `app/Database/seed_farmasi.sql`, dipindahkan ke `StockSeeder`. Jalankan seeder itu sebelum memakai angka contoh soal (obat 101, 102, 103, 104, 106, 107); menjalankannya ulang aman dan tidak menggandakan data.
- UI menyediakan 4 tampilan wajib (login, daftar/detail penerimaan, form penerimaan, daftar stok). Filter tanggal `on_date` dan filter status batch (semua/tersedia/kedaluwarsa) tersedia di halaman stok; angka contoh soal paling akurat diverifikasi lewat API.

## 7. Postman Collection

Tersedia di [`postman/`](postman):

- `Pharmacy-Farmagitechs.postman_collection.json` — 30 request dalam 5 folder (74 assertion)
- `Local.postman_environment.json` — variabel `base_url` (`http://localhost:8080`). `receipt_id` dan `foreign_receipt_id` di-set otomatis oleh collection saat request berjalan, jadi tidak perlu diisi di environment.

Cara menjalankan:

1. Jalankan aplikasi (`php spark serve --port 8080`) dengan database baseline.
2. Di Postman: Import kedua berkas, pilih environment `Pharmacy Farmagitechs - Local`.
3. Jalankan folder secara berurutan: **0. Bootstrap CSRF** → **1. Auth** → **2. Stocks** → **3. Receipts** → **4. Unauthenticated**.

Urutan penting: folder **0. Bootstrap CSRF** menerbitkan cookie `csrf_cookie_name` yang dipakai seluruh request berikutnya; folder **2. Stocks** memeriksa angka baseline sehingga harus dijalankan sebelum ada penerimaan baru; dan folder **4. Unauthenticated** sengaja memakai cookie tidak valid sehingga dijalankan paling akhir. Karena `POST /api/login` ditolak saat sesi masih aktif, setiap pergantian akun (mis. petugas → supervisor) didahului `POST /api/logout`.

Autentikasi memakai cookie session: request `1.4 Login petugas` menyimpan `ci_session` otomatis, dan Postman mengirimkannya pada request berikutnya. Script level koleksi menyisipkan header `X-CSRF-TOKEN` dari cookie `csrf_cookie_name` pada setiap request, lalu menyimpan nilai terbaru dari header response karena token berotasi tiap mutasi. Jalur CLI:

```
node node_modules/newman/bin/newman.js run postman/Pharmacy-Farmagitechs.postman_collection.json -e postman/Local.postman_environment.json
```

## Struktur Proyek

```
app/
  Config/         Konfigurasi, peta permission hardcoded, konfigurasi hash
  Controllers/    Api/ menerjemahkan HTTP; Web/ hanya cangkang halaman + boot object
  Database/       Migrations/ dan Seeds/
  Filters/        AuthFilter (menolak request tanpa login), GuestFilter (menolak rute tamu saat sudah login), dan CsrfFilter (403 JSON untuk /api/*)
  Models/         CRUD tipis + model event stamping timestamp
  Policies/       Keputusan hak ubah
  Repositories/   Query database
  Services/       Logika transaksi
  Validation/     Aturan validasi payload
  Views/          Cangkang web + objek window.FARMASI_BOOT (data diisi jQuery dari API)
  Helpers/        Helper lintas lapisan (Hash)
docs/             Dokumentasi database, keamanan, dan konvensi kode
postman/          Postman collection dan environment
public/assets/    CSS dan JS untuk UI (`js/app.js`, `js/lib/`, `js/pages/`)
scripts/          Runner `composer run test` (menangani ekstensi mysqli)
tests/            Test otomatis (Feature, database, unit)
```

Aturan lapisan ada di [`docs/conventions.md`](docs/conventions.md); detail proteksi CSRF ada di [`docs/security.md`](docs/security.md).

## Catatan Alat AI dan Referensi

Solusi ini disusun dengan bantuan AI (CodeBuddy) dan referensi dokumentasi resmi CodeIgniter 4. Keputusan desain, alasan pemilihan model stok, dan peta hak akses diverifikasi dan disesuaikan manual; detail keputusan tercatat di riwayat commit dan dokumentasi pada folder `docs/`.
