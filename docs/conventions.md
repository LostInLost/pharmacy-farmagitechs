# Konvensi Kode

> Bagian dari [README](../README.md). Lihat juga [Desain Database](database.md), [Keamanan](security.md#proteksi-csrf), [Pengujian](testing.md), dan [Postman Collection](postman.md).

## Lapisan

- Controller (`app/Controllers`) hanya menerjemahkan HTTP ke service. Tidak ada SQL, tidak ada keputusan hak akses di sini.
- Service (`app/Services`) memegang satu transaksi per operasi, menerima data + id aktor, dan tidak mengenal session/request sehingga bisa dipakai API, Web, maupun CLI. Service boleh memanggil service lain; yang membuka transaksi adalah service paling luar, dan service yang dipanggil tidak membuka transaksi sendiri.
- `AuditService` (`app/Services`) adalah satu-satunya penulis jejak audit: `logCreated`/`logUpdated`/`logDeleted` + `forEntity`. Service domain memanggilnya di dalam transaksinya sendiri, sehingga baris log ikut batal saat operasi gagal. Repository domain tidak menulis audit dan tidak menjadi perantara audit.
- Nilai `audit_logs.action` adalah kunci i18n (`Audit.receptions.action.create`), dirangkai `AuditService` dari `entity_type` + aksi — bukan token aksi telanjang dan bukan kalimat terjemahan. Labelnya milik `app/Language/{id,en}/Audit.php` (satu grup per entitas); entitas baru menambah grup di sana, tanpa migrasi kolom.
- Repository (`app/Repositories`) menyembunyikan Query Builder. Service tidak tahu nama tabel.
- Policy (`app/Policies`) memegang keputusan hak ubah. Dipanggil service sebelum write, di dalam transaksi.
- Validator (`app/Validation`) memusatkan aturan validasi payload.
- Filter (`app/Filters`) menangani autentikasi (`AuthFilter`) dan kegagalan CSRF (`CsrfFilter`). Tidak ada logika bisnis di sini.
- Web controller (`app/Controllers/Web`) hanya merender cangkang halaman + objek `window.FARMASI_BOOT`. Tidak ada query, service, policy, maupun validasi di sini; seluruh data diambil jQuery dari `/api/*`.
- JS (`public/assets/js`): `app.js` bootstrap namespace `Farmasi`; `lib/` berisi utilitas bersama (`csrf.js`, `api.js`, `ui.js`); `pages/` satu file per halaman. JS tidak boleh menduplikasi aturan validasi/policy — `can_update` dari API hanya affordance tampilan, penegakan tetap di server.

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
docs/             Dokumentasi pendalaman (lihat Peta Dokumentasi di README)
frontend/         Frontend alternatif Astro + shadcn/ui (lihat frontend/README.md)
postman/          Postman collection dan environment
public/assets/    CSS dan JS untuk UI (`js/app.js`, `js/lib/`, `js/pages/`)
scripts/          Runner `composer run test` (menangani ekstensi mysqli)
tests/            Test otomatis (Feature, database, unit)
.github/          Workflow CI PHPUnit
build/            Artefak test (testdox, JUnit XML, cache PHPUnit)
```

## Gaya

- Minimalkan komentar. Nama yang ekspresif lebih diutamakan daripada komentar.
- Komentar hanya untuk algoritma yang tidak obvious.
- Tidak ada docblock rutin yang hanya mengulang nama method.
