# Postman Collection

> Bagian dari [README](../README.md). Lihat juga [Keamanan](security.md#proteksi-csrf), [Desain Database](database.md), dan [frontend/README.md](../frontend/README.md).

Dokumentasi koleksi Postman untuk seluruh API Farmagitechs: cara menjalankan, variabel, bentuk respons, dan aturan validasi. Deskripsi aslinya tersimpan di dalam berkas collection (terbaca di aplikasi Postman); berkas ini adalah cermin markdown-nya agar mudah dibaca dari GitHub.

## 1. Berkas

| Berkas | Isi |
| --- | --- |
| [`Pharmacy-Farmagitechs.postman_collection.json`](../postman/Pharmacy-Farmagitechs.postman_collection.json) | 44 request dalam 6 folder, 108 assertion (blok `pm.test`) |
| [`Local.postman_environment.json`](../postman/Local.postman_environment.json) | Environment `Pharmacy Farmagitechs - Local`, berisi `base_url` |

Keduanya ikut version control; tidak ada berkas lain yang perlu di-import.

## 2. Menjalankan

Lewat aplikasi Postman:

1. Jalankan backend (`php spark serve --port 8080`) di atas database baseline (lihat [README §2](../README.md#2-diagram-database-dan-urutan-setup-skema)).
2. Import kedua berkas, lalu pilih environment `Pharmacy Farmagitechs - Local`.
3. Jalankan folder **berurutan**: **0. Bootstrap CSRF** → **1. Auth** → **2. Stocks** → **3. Receipts** → **4. Medicines** → **5. Unauthenticated**. Item `0.1` wajib jalan lebih dulu karena menerbitkan cookie CSRF.

Lewat CLI (Newman 6.2.2 sudah tersedia di `node_modules/`; tidak ada `package.json` di root sehingga binary dipanggil langsung):

```
node node_modules/newman/bin/newman.js run postman/Pharmacy-Farmagitechs.postman_collection.json -e postman/Local.postman_environment.json
```

Menjalankan sebagian: tambahkan `--folder "2. Stocks"` (boleh diulang untuk beberapa folder).

Catatan operasional:

- Full run **mengubah data development**: folder 3 membuat `PB-001` dan `PB-SUP-*`, folder 4 menambah obat uji. Kembalikan baseline dengan `php spark migrate:refresh` lalu dua seeder (urutan di [README §2](../README.md#2-diagram-database-dan-urutan-setup-skema)).
- Folder **2. Stocks** memeriksa angka baseline, jadi harus dijalankan sebelum ada penerimaan baru.
- Folder **5. Unauthenticated** memakai cookie/token rusak — jalankan paling akhir.
- Pergantian akun (petugas ↔ supervisor) selalu didahului `POST /api/logout`; `POST /api/login` saat sesi aktif dijawab `403` oleh filter `guest`.

## 3. Environment dan variabel

Environment hanya berisi `base_url` (`http://localhost:8080`). Sisanya variabel collection yang di-set otomatis saat run:

| Variabel | Di-set oleh item | Dipakai untuk |
| --- | --- | --- |
| `base_url` (environment) | manual | seluruh request |
| `csrf_token` | `0.1` (dan test script setiap respons) | fallback header `X-CSRF-TOKEN` |
| `receipt_id` | `3.1` | `3.7`, `3.8`, `3.10` |
| `foreign_receipt_id` | `3.13` | `3.16` |
| `foreign_reference` | `3.13` | disiapkan; belum dirujuk request lain (`3.16` memakai `reference_no` tetap) |
| `medicine_code` | `4.6` | `4.7`, `4.10` |
| `medicine_id` | `4.6` | `4.7`, `4.8`, `4.9`, `4.13` |

Menjalankan folder 3 atau 4 sebagian tetap butuh item pemicunya (mis. `3.1` sebelum `3.8`).

## 4. Autentikasi dan CSRF

Ringkas — detail desain di [docs/security.md](security.md#proteksi-csrf):

- Sesi memakai cookie `ci_session` yang terbit saat `POST /api/login`.
- Seluruh `POST`/`PUT` wajib header `X-CSRF-TOKEN`; nilainya sama dengan cookie `csrf_cookie_name` yang terbit dari `GET /login` (item `0.1`) atau `GET /api/csrf`.
- Token **berotasi** setiap mutasi sukses. Script level collection mengisi header dari cookie (fallback variabel `csrf_token`) dan menyimpan nilai terbaru dari header `X-CSRF-TOKEN` di setiap respons — jangan hardcode.
- `POST /api/logout` memusnahkan sesi dan wajib dilakukan sebelum login akun lain.

## 5. Peta folder

| Folder | Request | Assertion | Yang dibuktikan |
| --- | --- | --- | --- |
| **0. Bootstrap CSRF** | 1 | 2 | Cookie `csrf_cookie_name` terbit dari `GET /login` |
| **1. Auth** | 5 | 12 | Login gagal (`401`), login supervisor dan petugas beserta `permissions`, logout, `GET /api/me` |
| **2. Stocks** | 5 | 19 | Angka baseline (101 fisik 142 / tersedia 134 / kedaluwarsa 8, 102=16, 103=15, 104=3, 106=0, 107 fisik 6 / tersedia 0), default `on_date`, format tanggal salah (`422`), dropdown hanya baris aktif |
| **3. Receipts** | 16 | 39 | Create/update/list/detail, validasi (`422`), idempotensi, atribusi pembuat/pengubah, `403` hak ubah, efek ke stok |
| **4. Medicines (master obat)** | 13 | 28 | Petugas baca tanpa tulis (`403`), supervisor tulis (`201`/`200`), nonaktif ≠ terhapus, kode duplikat (`422`), riwayat audit di detail (`data.logs`) |
| **5. Unauthenticated** | 4 | 8 | `401` tanpa sesi; `403` tanpa/keliru token CSRF, lengkap dengan token segar untuk percobaan ulang |

Total: 44 request, 108 assertion.

## 6. Bentuk respons dan error

Bentuk sukses:

| Bentuk | Dipakai oleh |
| --- | --- |
| `{"data": ...}` | GET receipts (daftar/detail), references |
| `{"on_date": ..., "medicines": [...]}` | GET /api/stocks |
| `{"user": ...}` | GET /api/me; POST /api/login memakai `message` + `user` |
| `{"message": ..., "data": ...}` | POST/PUT /api/receipts |
| `{"message": ...}` | POST /api/logout |
| `{"token": ...}` | GET /api/csrf |

Bentuk gagal — umumnya `{"message": "..."}`; validasi (`422`) dan gagal simpan (`500`) pada receipts menambah `errors: [...]` (create: `message` = "Validasi gagal."; update: `message` = `errors[0]`):

| Kondisi | Status | Body |
| --- | --- | --- |
| Sesi tidak ada (AuthFilter) | 401 | `{"message":"Belum masuk."}` — tanpa header `X-CSRF-TOKEN` |
| Token CSRF hilang/salah/kedaluwarsa (CsrfFilter) | 403 | `{"message":"Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.","error":"csrf"}` + header `X-CSRF-TOKEN` berisi token baru |
| `POST /api/login` saat sesi aktif (GuestFilter) | 403 | `{"message":"Sudah masuk."}` |
| Hak ubah ditolak (policy) | 403 | `{"message":"Anda tidak berhak mengubah penerimaan ini.","errors":[...]}` |
| id tidak ada | 404 | GET tanpa `errors`; PUT dengan `errors` |
| Validasi payload | 422 | `errors[]` |
| Kegagalan transaksi | 500 | rollback; tidak ada data parsial |

Urutan filter global: `cors` → `csrf` → `guest`/`auth`. Karena `csrf` berjalan lebih dulu, `POST` tanpa token **dan** tanpa sesi dijawab `403` (bukan `401`).

## 7. Aturan validasi payload

`POST`/`PUT /api/receipts`:

| Field | Aturan |
| --- | --- |
| `reference_no` | wajib; unik antar penerimaan (saat update, miliknya sendiri tidak dianggap duplikat) |
| `supplier_id` | harus ada dan aktif |
| `received_at` | tanggal-waktu valid (zona Asia/Jakarta) |
| `items` | minimal satu baris |
| `items[].medicine_id` | harus ada dan aktif |
| `items[].batch_no` | wajib; kombinasi (medicine_id, batch_no) hanya sekali per payload |
| `items[].quantity` | bilangan bulat positif |
| `items[].expires_on` | `YYYY-MM-DD`, lebih akhir dari tanggal `received_at`, konsisten dengan batch yang sama di database |

`POST`/`PUT /api/medicines`:

| Field | Aturan |
| --- | --- |
| `code` | wajib, maks 50 karakter, unik case-insensitive |
| `name` | wajib, maks 200 karakter |
| `unit` | wajib, maks 50 karakter |
| `is_active` | opsional; bila tidak dikirim saat update, status tersimpan dipertahankan |

Katalog pesan `422` (`errors[]`) untuk receipts — daftar lengkap ada di deskripsi folder **3. Receipts** di dalam collection:

- `reference_no wajib diisi.` / `reference_no {no} sudah dipakai penerimaan lain.`
- `Pemasok tidak ditemukan.` / `Pemasok tidak aktif.`
- `received_at harus berupa tanggal-waktu yang valid.`
- `items harus berisi setidaknya satu baris.`
- `Baris {n}: obat tidak ditemukan.` / `Baris {n}: obat tidak aktif.`
- `Baris {n}: batch_no wajib diisi.` / `Baris {n}: quantity harus bilangan bulat positif.` / `Baris {n}: expires_on harus berupa tanggal YYYY-MM-DD.`
- `Baris {n}: kombinasi obat dan batch_no {b} muncul lebih dari sekali.`
- `Baris {n}: expires_on harus lebih akhir daripada tanggal penerimaan.`
- `Batch {b} obat {id} sudah tercatat dengan kedaluwarsa {lama}, bukan {baru}.`

## 8. Skenario uji end-to-end

| Skenario | Item pembukti |
| --- | --- |
| 1. Buat penerimaan → stok 101 = 144, 104 = 8, kedaluwarsa 101 tetap 8 | `3.1` → `3.2` |
| 2. Ubah penerimaan (101 → 7, hapus 104, tambah 103) → stok 101 = 141, 103 = 18, 104 = 3 | `3.8` → `3.9` |
| 3. Kirim PUT identik → stok tidak berlipat | `3.10` |
| 4. Atribusi petugas vs supervisor | `3.1` (`created_by_name`), `3.8` (`updated_by`), `3.12`–`3.15` (ganti peran); verifikasi lengkap di `tests/Feature/ReceptionScenarioTest.php` |
| 5. Petugas tidak boleh mengubah milik orang lain; request tanpa login ditolak | `3.13` → `3.16`, folder `5` |

## 9. Jebakan umum

1. **Ganti akun tanpa logout** → `POST /api/login` berikutnya `403 {"message":"Sudah masuk."}`.
2. **Folder 5 dijalankan di tengah** → cookie/token rusak terbawa ke request berikutnya.
3. **Folder 2 dijalankan setelah ada penerimaan baru** → angka baseline tidak cocok.
4. **Token CSRF di-hardcode** → `403` setelah mutasi pertama; biarkan script collection yang mengurus.
5. **Mulai dari folder 1 tanpa folder 0** → header `X-CSRF-TOKEN` kosong pada `POST /api/login`; jalankan `0.1` dulu (atau `GET /api/csrf`).
6. **Run sebagian folder 3 tanpa `3.1`** → `receipt_id`/`foreign_receipt_id` kosong.
7. **Database belum baseline** → jalankan migrasi dan seeder lebih dulu ([README §2](../README.md#2-diagram-database-dan-urutan-setup-skema)).

## 10. Pemeliharaan

- Sumber asli dokumentasi adalah **deskripsi di dalam collection** (dibaca aplikasi Postman). Berkas ini cermin markdown; saat collection berubah, perbarui keduanya.
- Angka request/assertion di berkas ini dihitung dari collection: request = jumlah item; assertion = jumlah blok `pm.test` (Newman melaporkan angka yang sama pada ringkasan run).
- Perubahan collection tetap terekam di riwayat git berkas JSON-nya.
