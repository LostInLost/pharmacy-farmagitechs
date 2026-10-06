# Keamanan

## Proteksi CSRF

### Konfigurasi

| Aspek | Nilai | Alasan |
| --- | --- | --- |
| Cakupan filter | Global (`app/Config/Filters.php`), termasuk `/api/*` | Tidak ada jalur mutasi yang lolos tanpa token |
| Penyimpanan token | `csrfProtection = 'cookie'` | Tidak bergantung pada sesi; token tetap terbit untuk halaman publik seperti `/login` |
| Nama cookie / header | `csrf_cookie_name` / `X-CSRF-TOKEN` | Nilai cookie identik dengan token (`tokenRandomize = false`) sehingga klien cukup membaca cookie |
| Rotasi | `regenerate = true` | Token lama tidak dapat dipakai ulang |
| Masa berlaku | 7200 detik | Cukup untuk satu sesi kerja form |
| `redirect` | `false` | Penanganan kegagalan diserahkan ke `App\Filters\CsrfFilter` agar `/api/*` menerima JSON, bukan redirect |

### Alur token

1. `GET /login` (atau halaman apa pun) memicu konstruktor `Security` yang menerbitkan cookie `csrf_cookie_name` bila belum ada. Meta `<meta name="csrf-token">` di `app/Views/layout.php` memuat nilai yang sama untuk dibaca JavaScript.
2. Klien mengirim token pada setiap mutasi: form web lewat hidden input `csrf_test_name` (`csrf_field()`), JavaScript lewat header `X-CSRF-TOKEN`, Postman lewat header yang sama.
3. Bila token valid, `Security::verify()` merotasi hash dan menerbitkan cookie baru pada response.
4. Setiap response API menyertakan header `X-CSRF-TOKEN` berisi hash terbaru (`BaseApiController::withFreshCsrf()`), sehingga klien selalu punya nilai sah berikutnya tanpa perlu memuat ulang halaman.

### Kegagalan token

`App\Filters\CsrfFilter` menggantikan `CodeIgniter\Filters\CSRF` agar bentuk kegagalan sesuai jenis klien:

- `/api/*` → `403` JSON `{"message": "...", "error": "csrf"}` beserta header `X-CSRF-TOKEN` berisi token segar, sehingga klien dapat mencoba ulang sekali tanpa memuat ulang halaman. Penanda `"error": "csrf"` membedakannya dari `403` hak akses yang berasal dari policy.
- Halaman web → redirect kembali ke halaman asal dengan flashdata `error`.

### Urutan filter

`csrf` berjalan sebagai filter global *sebelum* filter route `auth`. Konsekuensinya, `POST` tanpa token **dan** tanpa sesi dijawab `403` (bukan `401`). Ini disengaja: permintaan yang tidak membawa bukti berasal dari origin yang sah tidak perlu diproses lebih jauh.

### Rute tamu (filter `guest`)

`GET /login` dan `POST /api/login` memakai filter `guest` (`App\Filters\GuestFilter`), kebalikan `auth`: keduanya menolak permintaan yang **sudah** login.

- Web (`GET /login`) → `403` dengan view `auth/already_authenticated.php` berisi tautan ke `/receptions` dan tombol keluar, bukan redirect diam-diam. Redirect 302 dari halaman login menyulitkan pengguna memahami kenapa form tidak muncul.
- API (`POST /api/login`) → `403` JSON `{"message": "Sudah masuk."}` beserta header `X-CSRF-TOKEN` berisi token terbaru, mengikuti kontrak "setiap response API membawa token segar" (`BaseApiController::withFreshCsrf()`). Tanpa header itu, klien yang ditolak akan memakai token basi pada request berikutnya dan tertahan `403 csrf`.
- Pengecekan login inline di `Web\AuthPages::login()` dihapus agar filter menjadi satu-satunya sumber kebenaran.

Konsekuensi: klien harus mengakhiri sesi (`POST /api/logout`) sebelum berganti akun; koleksi Postman melakukan ini di item `3.11` dan `3.14`.

### Konsekuensi untuk klien

- Klien API harus memulai dari `GET /login` untuk memperoleh cookie CSRF sebelum `POST /api/login`.
- Klien wajib menyimpan token terbaru dari header response dan memakainya pada request berikutnya; memakai ulang token lama menghasilkan `403`.
- Pergantian akun harus lewat logout dulu: `POST /api/login` saat sesi masih aktif dijawab `403` oleh filter `guest`.
- Logout web hanya tersedia lewat `POST /logout` (dilindungi CSRF), bukan `GET`.

### Batas yang belum ditangani

Cookie sesi belum memakai flag `Secure` (`Config\Cookie::$secure = false`) karena pengembangan berjalan di HTTP. Saat aplikasi dipasang di HTTPS, aktifkan `Secure` dan `app.forceGlobalSecureRequests` agar cookie tidak dapat disadap pada jaringan tidak terenkripsi.
