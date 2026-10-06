# Konvensi Kode

## Lapisan

- Controller (`app/Controllers`) hanya menerjemahkan HTTP ke service. Tidak ada SQL, tidak ada keputusan hak akses di sini.
- Service (`app/Services`) memegang satu transaksi per operasi, menerima data + id aktor, dan tidak mengenal session/request sehingga bisa dipakai API, Web, maupun CLI.
- Repository (`app/Repositories`) menyembunyikan Query Builder. Service tidak tahu nama tabel.
- Policy (`app/Policies`) memegang keputusan hak ubah. Dipanggil service sebelum write, di dalam transaksi.
- Validator (`app/Validation`) memusatkan aturan validasi payload.
- Filter (`app/Filters`) menangani autentikasi (`AuthFilter`) dan kegagalan CSRF (`CsrfFilter`). Tidak ada logika bisnis di sini.
- Web controller (`app/Controllers/Web`) hanya merender cangkang halaman + objek `window.FARMASI_BOOT`. Tidak ada query, service, policy, maupun validasi di sini; seluruh data diambil jQuery dari `/api/*`.
- JS (`public/assets/js`): `app.js` bootstrap namespace `Farmasi`; `lib/` berisi utilitas bersama (`csrf.js`, `api.js`, `ui.js`); `pages/` satu file per halaman. JS tidak boleh menduplikasi aturan validasi/policy — `can_update` dari API hanya affordance tampilan, penegakan tetap di server.

## Gaya

- Minimalkan komentar. Nama yang ekspresif lebih diutamakan daripada komentar.
- Komentar hanya untuk algoritma yang tidak obvious.
- Tidak ada docblock rutin yang hanya mengulang nama method.
