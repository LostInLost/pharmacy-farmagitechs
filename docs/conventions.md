# Konvensi Kode

## Lapisan

- Controller (`app/Controllers`) hanya menerjemahkan HTTP ke service. Tidak ada SQL, tidak ada keputusan hak akses di sini.
- Service (`app/Services`) memegang satu transaksi per operasi, menerima data + id aktor, dan tidak mengenal session/request sehingga bisa dipakai API, Web, maupun CLI.
- Repository (`app/Repositories`) menyembunyikan Query Builder. Service tidak tahu nama tabel.
- Policy (`app/Policies`) memegang keputusan hak ubah. Dipanggil service sebelum write, di dalam transaksi.
- Validator (`app/Validation`) memusatkan aturan validasi payload.
- Filter (`app/Filters`) menangani autentikasi (`AuthFilter`) dan kegagalan CSRF (`CsrfFilter`). Tidak ada logika bisnis di sini.

## Gaya

- Minimalkan komentar. Nama yang ekspresif lebih diutamakan daripada komentar.
- Komentar hanya untuk algoritma yang tidak obvious.
- Tidak ada docblock rutin yang hanya mengulang nama method.
