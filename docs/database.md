# Desain Database

## Diagram ERD

```mermaid
erDiagram
    users ||--o{ receptions : "creates"
    users ||--o{ receptions : "updates"
    users ||--o{ audit_logs : "acts"
    suppliers ||--o{ receptions : "supplies"
    medicines ||--o{ seed_batch_stock : "has_initial"
    medicines ||--o{ stock_usage : "consumed"
    medicines ||--o{ reception_items : "received"
    receptions ||--o{ reception_items : "contains"
    receptions ||--o{ audit_logs : "audited (entity_type='reception')"

    users {
        int id PK
        varchar name
        varchar username UK
        varchar email UK
        varchar password_hash
        enum role "reception|supervisor"
        tinyint is_active
    }
    suppliers {
        int id PK
        varchar name
        tinyint is_active
    }
    medicines {
        int id PK
        varchar code UK
        varchar name
        varchar unit
        tinyint is_active
    }
    seed_batch_stock {
        int id PK
        int medicine_id FK
        varchar batch_no
        date expires_on
        int quantity
    }
    stock_usage {
        int id PK
        int medicine_id FK
        varchar batch_no
        datetime used_at "NULL = tidak disebut lampiran"
        varchar unit_name "NULL = tidak disebut lampiran"
        int quantity
    }
    receptions {
        int id PK
        varchar reference_no UK
        int supplier_id FK
        datetime received_at "waktu barang datang"
        int created_by FK
        datetime created_at "waktu dicatat sistem"
        int updated_by FK "NULL = never edited"
        datetime updated_at "NULL = never edited"
    }
    reception_items {
        int id PK
        int reception_id FK
        int medicine_id FK
        varchar batch_no
        date expires_on
        int quantity
    }
    audit_logs {
        int id PK
        varchar entity_type "reception = entitas yang diaudit"
        int entity_id "id entitas, polimorfik tanpa FK"
        int actor_id FK
        enum action "CREATE|UPDATE|DELETE"
        json data_before "NULL saat CREATE"
        json data_after "snapshot keadaan akhir"
        datetime created_at
    }
```

## Tabel

| Tabel | Peran |
| --- | --- |
| `users` | Akun petugas dan perannya. Dua akun demo dari seeder. |
| `suppliers` | Katalog pemasok beserta status aktifnya. |
| `medicines` | Katalog obat, satuan (`unit`), dan status aktifnya. |
| `seed_batch_stock` | Stok awal per batch pada 2026-10-01, sebelum pemakaian seed. |
| `stock_usage` | Pemakaian final oleh unit pelayanan yang mengurangi stok, beserta waktu pakai dan unit pelayanannya. |
| `receptions` | Header satu transaksi kedatangan dari satu pemasok. |
| `reception_items` | Rincian obat, batch, kedaluwarsa, dan jumlah per penerimaan. |
| `audit_logs` | Jejak aksi tulis semua entitas (kini `reception`) beserta snapshot sebelum/sesudah; polimorfik lewat pasangan `entity_type`/`entity_id`. |

`suppliers`, `medicines`, `seed_batch_stock`, dan `stock_usage` berasal dari lampiran `app/Database/seed_farmasi.sql`. Isinya dipindahkan apa adanya ke `StockSeeder` (3 pemasok, 25 obat, 10 batch awal, 3 baris pemakaian) supaya angka laporan stok sama dengan contoh soal tanpa impor SQL manual. Seeder mencocokkan baris per `id`: `suppliers` dan `medicines` diperbarui di tempat karena dirujuk foreign key, sedangkan `seed_batch_stock` dan `stock_usage` dimuat ulang seluruhnya agar stok penerimaan lama tidak menumpuk. Kolom `used_at` dan `unit_name` hanya dibaca lampiran; perhitungan stok tetap memakai `quantity`.

## Kunci dan Indeks

- Primary key: `id` surrogate auto-increment untuk `users`, `receptions`, `reception_items`, `audit_logs`, dan tabel seed yang membutuhkannya. Surrogate dipilih agar join stabil dan tidak bergantung pada data bisnis yang bisa berubah.
- Foreign key: `receptions.supplier_id` ke `suppliers.id`, `receptions.created_by`/`updated_by` ke `users.id`, `reception_items.reception_id` ke `receptions.id` dengan `ON DELETE CASCADE` supaya menghapus penerimaan tidak meninggalkan item yatim, dan `audit_logs.actor_id` ke `users.id` dengan `ON DELETE RESTRICT`. Satu-satunya kolom FK yang sengaja kosong adalah `audit_logs.entity_id`: nilainya polimorfik (menunjuk ke tabel sesuai `entity_type`), dan kolom FK memang tidak dapat menunjuk ke banyak tabel sekaligus. Konsekuensinya menghapus entitas yang diaudit tidak lagi ditolak oleh log — justru diinginkan, karena jejak audit harus tetap hidup ketika datanya sudah hilang.
- Unique: `users.username`, `users.email`, `medicines.code`, `receptions.reference_no`, `seed_batch_stock(medicine_id, batch_no)` mencegah stok awal batch ganda, dan `reception_items(reception_id, medicine_id, batch_no)` mencegah kombinasi obat-batch muncul dua kali dalam satu penerimaan.
- Indeks: `reception_items(medicine_id, batch_no)` untuk agregasi stok per batch, `receptions(supplier_id, received_at)` untuk daftar dan filter penerimaan, `audit_logs(entity_type, entity_id, created_at)` untuk riwayat aksi per entitas (menggantikan `(reception_id, created_at)`).

## Model Stok

Stok dihitung sebagai agregasi ledger, bukan kolom stok yang dimutasi:

```
stok fisik batch = seed_batch_stock.quantity
                 + SUM(reception_items.quantity)
                 - SUM(stock_usage.quantity)
```

Kunci agregasi adalah pasangan `(medicine_id, batch_no)` sesuai identitas batch pada soal. Batch dikelompokkan menjadi tersedia bila `expires_on >= on_date` dan kedaluwarsa bila `expires_on < on_date`. Stok kedaluwarsa tetap dihitung sebagai stok fisik.

Alasan model ini: pembaruan penerimaan memakai keadaan akhir lengkap (`PUT` mengganti seluruh item), sehingga menghapus item cukup menghapus barisnya dan stok otomatis menyesuaikan tanpa rekonsiliasi manual. Pengiriman `PUT` identik dua kali tidak menggandakan stok karena tidak ada penambahan kumulatif di luar baris item. Batch nol tetap ditampilkan agar jejak batch tidak hilang dan urutan `expires_on` mendukung pemilihan FEFO manual.

## Konsistensi Transaksi

Setiap operasi buat/ubah penerimaan dijalankan dalam satu transaksi database:

1. Validasi payload dan hak akses dijalankan sebelum write. Kegagalan mengembalikan HTTP 4xx tanpa mengubah data.
2. Header penerimaan, seluruh item, dan log aksi ditulis dalam transaksi yang sama.
3. Jika satu baris item gagal, transaksi di-rollback sehingga penerimaan, stok, dan log tidak berubah sebagian.

Karena stok adalah hasil agregasi atas data tersimpan, rollback otomatis mengembalikan angka stok tanpa perhitungan kompensasi tambahan.

## Audit Trail

`audit_logs` menyimpan satu baris per aksi tulis: `CREATE` atau `UPDATE` (enum juga menyediakan `DELETE`, tetapi endpoint hapus tidak ada di lingkup tes). Tabelnya generik — satu tabel melayani semua entitas — dengan pasangan `entity_type`/`entity_id` sebagai penunjuk entitas; baris milik penerimaan memakai `entity_type = 'reception'` dan `entity_id` berisi `receptions.id`.

| Kolom | Isi |
| --- | --- |
| `entity_type` | Jenis entitas yang diaudit; saat ini hanya `reception`. |
| `entity_id` | ID entitas yang diaudit (untuk reception: `receptions.id`). |
| `actor_id` | Petugas pelaku, diambil server dari sesi. |
| `action` | `CREATE` atau `UPDATE`. |
| `data_before` | Snapshot keadaan entitas **sebelum** perubahan. `NULL` untuk `CREATE`. |
| `data_after` | Snapshot keadaan entitas **sesudah** perubahan. |
| `created_at` | Waktu aksi. |

Kontrak snapshot (dibentuk `ReceptionService::snapshot()`):

```
{
  "reference_no": "PB-001",
  "supplier_id": 1,
  "received_at": "2026-10-03 10:00:00",
  "items": [
    {"medicine_id": 101, "batch_no": "PCT-2601", "expires_on": "2027-12-31", "quantity": 10}
  ]
}
```

- Item diurutkan `(medicine_id, batch_no)` supaya perbandingan before/after stabil meski urutan kiriman berbeda.
- Snapshot `before` pada `UPDATE` dibaca dari keadaan tersimpan (`itemsOf()`), bukan dari request, sehingga merekam kenyataan database.
- Request yang ditolak (403/422) tidak menulis baris log sama sekali.
- `PUT` identik tetap tercatat sebagai aksi baru dengan `data_before == data_after`, sehingga beda antara "ada aksi" dan "ada perubahan" tetap terlihat.

Alasan tabel generik: `reception_logs` dahulu reception-scoped karena hanya penerimaan yang dapat ditulis aplikasi. Begitu log dimaksudkan dapat dipakai domain lain, bentuk generik `audit_logs` lebih tepat daripada menambah tabel log per entitas — kolom inti (`actor_id`, `action`, waktu, snapshot) identik untuk entitas apa pun, sehingga satu model dan satu repository cukup. Trade-off-nya: `entity_id` tidak dapat diberi foreign key karena satu kolom menunjuk ke banyak tabel. Itu diterima karena jejak audit justru harus tetap hidup ketika entitasnya dihapus; `actor_id` tetap ber-FK ke `users` agar pelaku selalu valid. Domain baru cukup mengisi `entity_type` sendiri (mis. `medicine`, `supplier`) tanpa perubahan skema.

## Konvensi

- `receptions.created_by` tidak pernah berubah. `receptions.updated_by` bernilai `NULL` selama penerimaan belum pernah diubah, sehingga beda antara "belum diubah" dan "diubah oleh pembuat" tetap terlihat.
- `receptions.received_at` adalah waktu barang datang sesuai payload dan boleh di-backdate; `receptions.created_at` adalah waktu petugas mencatat di sistem. Keduanya berbeda makna dan tidak saling menggantikan.
- `receptions.updated_at` simetris dengan `updated_by`: keduanya `NULL` selama belum pernah diubah, sehingga pasangan `(updated_by, updated_at)` selalu null-null atau terisi bersama. Waktu diisi otomatis oleh model event (`beforeInsert`, `beforeInsertBatch`, `beforeUpdate`) memakai `Time::now()` yang mengikuti `appTimezone` Asia/Jakarta; identitas petugas tetap di-set service dari sesi.
- Database tidak memakai `DEFAULT CURRENT_TIMESTAMP` maupun trigger MySQL. `CURRENT_TIMESTAMP` mengikuti zona waktu server database, bukan Asia/Jakarta yang diwajibkan soal, dan default kolom tidak dapat menjaga `updated_at` tetap `NULL` sampai edit pertama. Sebagai jaring pengaman, insert tanpa `created_at` ditolak database (`ERROR 1364`).
- Model CI4 memakai `useTimestamps = false`. Bila diaktifkan, CI4 mengisi `updated_at` pada saat insert juga (`BaseModel::insert()`), sehingga merusak konvensi null di atas.
- `reception_items` tidak punya timestamp: item selalu diganti penuh saat pembaruan sehingga waktu per baris menyesatkan. Waktu perubahan tercatat di `receptions.updated_at` dan `audit_logs.created_at`.
- Setiap aksi buat/ubah menambah satu baris `audit_logs` berisi `entity_type`, `entity_id`, `actor_id`, `action`, snapshot `data_before`/`data_after`, dan `created_at`.
- Satuan mengikuti `medicines.unit` tanpa konversi.
- `users.name` dipakai untuk menampilkan nama pembuat/pengubah pada detail penerimaan. `users.email` wajib dan unik, disiapkan untuk alur pemulihan kata sandi berbasis email (alurnya sendiri di luar cakupan tes).
