# Frontend (Astro + shadcn/ui)

Frontend Astro (SSR, adapter Node) untuk aplikasi farmasi Farmagitechs.
Login memakai session CodeIgniter 4 lewat `POST /api/login` (cookie
`ci_session` + header CSRF). Guard rute dijalankan server-side lewat
middleware berantai `sequence()`.

## Prasyarat

- Node.js >= 22.12
- pnpm 11.x (semua perintah di dokumen ini memakai pnpm)
- Backend CI4 berjalan, misalnya `php spark serve` di root repo (default
  `http://localhost:8080`)

## Menjalankan

Terminal 1 (root repo, backend):

```bash
php spark serve
```

Terminal 2 (folder ini, frontend):

```bash
pnpm install
pnpm dev
```

Buka `http://localhost:4321/login`.

## Perintah

| Perintah | Fungsi |
| --- | --- |
| `pnpm dev` | Dev server di port 4321 |
| `pnpm build` | Build produksi ke `dist/` (SSR) |
| `pnpm preview` | Preview hasil build |
| `node dist/server/entry.mjs` | Jalankan server SSR hasil build (env `HOST`/`PORT`) |
| `pnpm typecheck` | `astro check` |
| `pnpm lint` | ESLint |
| `pnpm format` | Prettier |

## Konfigurasi

Salin `.env.example` menjadi `.env` lalu sesuaikan bila perlu:

- `PUBLIC_API_BASE_URL` — base URL backend CI4 (default `http://localhost:8080`)
- `PUBLIC_POST_LOGIN_PATH` — tujuan setelah login (default `/dashboard`)
- `PUBLIC_APP_NAME` — nama yang tampil di UI (default `Farmagitechs`)

## Struktur

```
src/
  components/
    app-shell.tsx     # chrome bersama: sidebar + header + toaster
    app-sidebar.tsx   # menu bergrup (Utama / Operasional)
    nav-main.tsx      # grup menu + aksi cepat "Tambah Penerimaan"
    nav-user.tsx      # blok profil + tema + keluar
    feedback.tsx      # alert inline khusus pesan error (sukses = toast)
    ui/               # komponen shadcn
  features/
    auth/             # login, sesi, guard, permission (`hasPermission`)
    audit/            # label aksi audit (disiapkan untuk menu Audit)
    dashboard/        # ringkasan
    receptions/       # daftar + sheet tambah/detail/ubah
    stocks/           # laporan stok
    medicines/        # master obat (daftar + dialog tambah/ubah)
  foundations/
    api/              # client, requestJson ber-CSRF, skema error
    format.ts         # format tanggal/waktu tanpa new Date()
    storage.ts, theme.ts
  layouts/
    base-layout.astro
  middleware.ts       # guard berantai: session → guest → authenticated
  pages/
    index.astro       # redirect pintar: login ↔ dashboard sesuai sesi
    login.astro       # halaman tamu (island LoginForm)
    dashboard.astro
    receptions/       # index (daftar + sheet); new & [id]/edit hanya redirect
    stocks.astro
    medicines.astro
```

Aturan lapisan: `foundations/` adalah infra generik dan **tidak boleh**
mengimpor `features/` (ditegakkan ESLint `no-restricted-imports`);
`features/` menyimpan skema Zod, pemanggilan API, dan komponen per domain.

## Halaman

| Rute | Isi |
| --- | --- |
| `/login` | Form masuk (halaman tamu) |
| `/dashboard` | Ringkasan penerimaan, stok tersedia, dan obat perlu perhatian |
| `/receptions` | Daftar penerimaan + sheet tambah/detail/ubah (`?new=1`, `?view=<id>`, `?edit=<id>`) |
| `/receptions/new`, `/receptions/{id}/edit` | Rute lama; hanya mengalihkan ke sheet di atas |
| `/stocks` | Laporan stok per obat dan batch |
| `/medicines` | Master obat: cari/filter, tambah, ubah, dan aktif/nonaktif |

### Sheet penerimaan

Semua alur dokumen terjadi di atas daftar `/receptions`:

- **Tambah** — tombol header / aksi cepat sidebar / `?new=1`.
- **Detail** — klik reference atau `?view=<id>`; read-only, boleh dibuka
  semua role (petugas dapat melihat dokumen milik orang lain) dan
  **tidak menampilkan Riwayat Aksi** (audit akan jadi menu tersendiri).
- **Ubah** — tombol Ubah di baris (bila `can_update`) atau di dalam sheet
  detail, atau `?edit=<id>`; bila policy menolak, sheet menampilkan pesan
  beserta tombol "Lihat detail".

Membuka/menutup sheet menyinkronkan URL lewat `history.replaceState`
(tanpa entri riwayat baru), jadi tautan `?view=<id>` bisa dimuat langsung
lewat SSR. Konsekuensinya tombol Back browser keluar dari halaman, bukan
menutup sheet.

Halaman **Master Obat** mengikuti pola `can_update` pada penerimaan:
`GET /api/medicines` mengembalikan `can_write` dari `MedicinePolicy`, jadi
petugas penerimaan melihat katalognya tanpa tombol tambah/ubah, sedangkan
supervisor mendapat keduanya. Tampilan itu hanya affordance — penegakan
tetap di server, dan permintaan tulis dari petugas dijawab `403`.
Obat **tidak dihapus** dari halaman ini: yang tersedia adalah menandainya
nonaktif, sehingga riwayat stok dan penerimaannya tetap utuh.

## Permission di UI

`GET /api/me` (dan `POST /api/login`) mengembalikan `user.permissions` —
array datar permission milik role, mis.
`["receipt.create", "receipt.view", "receipt.update-own", "medicine.view"]`
— hasil `Config\Permissions::forRole()`. Backend menghitungnya per request
(tidak disimpan di session), jadi mengubah konfigurasi langsung berlaku
tanpa login ulang. Bentuk array datar sengaja dipilih agar nanti mudah
dipindah apa adanya ke klaim cookie JWT.

Frontend memakainya lewat `features/auth/permissions.ts`
(`PERMISSIONS` + `hasPermission()`) untuk **menggating tampilan**:

- tombol yang tidak bergantung baris: "Tambah Penerimaan" (aksi cepat
  sidebar + header daftar + dashboard) → `receipt.create`;
- untuk aksi **per baris** tetap dipakai `can_update`/`can_write` dari
  respons server, karena `receipt.update-own` butuh data pemilik baris.

Bila field `permissions` tidak ada (mis. backend versi lama), skema Zod
memberi nilai bawaan `[]` sehingga tombol aksi hilang — fail-closed, bukan
error. Semua ini murni affordance: penegakan tetap di policy server.

## Middleware (guard berantai)

`src/middleware.ts` memakai `sequence()` dari `astro:middleware`, jadi tiap
tahap punya satu tanggung jawab dan dipanggil berurutan:

1. **`session`** — memanggil `GET {API}/api/me` dengan header `Cookie`
   browser diteruskan apa adanya, lalu mengisi `locals.user` (tipe ada di
   `src/env.d.ts`). Aset statis (`/_astro/`, favicon) dilewati agar tidak
   membanjiri backend. Backend mati → `locals.user = null` (fail-closed).
2. **`guest`** — rute tamu (`/login`): pengguna yang sudah masuk dialihkan
   ke `PUBLIC_POST_LOGIN_PATH`.
3. **`authenticated`** — **deny-by-default**: semua rute di luar daftar
   publik (`/login`, favicon, aset) wajib login; tamu dialihkan ke `/login`.
   Halaman baru otomatis terlindungi tanpa daftar manual.

Konsekuensi: SSR wajib aktif (`output: "server"` + `adapter: node`), dan
middleware hanya berjalan lewat dev server atau `node dist/server/entry.mjs`
— bukan sebagai file statis. `GET /api/me` dipilih sebagai pengecekan
sesi karena metode aman lolos CSRF dan mengembalikan data user sekaligus.

## Alur login

1. Token CSRF diambil dari `GET {API}/api/csrf` (header `X-CSRF-TOKEN`,
   body `{ token }`). Cookie CSRF bersifat HttpOnly, jadi JavaScript tidak
   bisa membacanya.
2. `POST {API}/api/login` dengan `credentials: 'include'` + header
   `X-CSRF-TOKEN`.
3. Token berotasi setiap mutasi (`regenerate = true`). Bila respons 403
   dengan `error: 'csrf'`, token segar diambil dari header respons lalu
   request diulang sekali.
4. Respons dipetakan: 422 (wajib isi), 401 (kredensial salah), 403 tanpa
   `error: csrf` (sudah login — `GuestFilter`), 5xx/network (server).

## CORS

Backend sudah dikonfigurasi untuk origin frontend saja (lihat
`app/Config/Cors.php` dan `app/Config/Filters.php` di root repo):

- `allowedOrigins` = `http://localhost:4321` (origin dev Astro), tanpa
  wildcard; origin asing tidak pernah di-echo.
- `supportsCredentials = true` — cookie session ikut terkirim.
- `allowedHeaders` = `Content-Type`, `X-CSRF-TOKEN`;
  `exposedHeaders` = `X-CSRF-TOKEN` agar token rotasi terbaca JavaScript.
- Filter `cors` global dijalankan sebelum `csrf`, sehingga respons 403 CSRF
  tetap membawa header CORS (jalur retry token).
- Rute `OPTIONS api/(:any)` disediakan untuk preflight; rute `GET api/csrf`
  untuk bootstrap token.

Frontend (`localhost:4321`) dan backend (`localhost:8080`) berbagi host
`localhost`, jadi keduanya same-site dan cookie `SameSite=Lax` tetap
terkirim. Saat deploy, tambahkan origin frontend produksi ke
`allowedOrigins` (jangan pakai wildcard).

## Verifikasi browser (opsional)

Skrip di `scripts/` menjalankan Chrome headless lewat Chrome DevTools Protocol
untuk memeriksa DOM yang benar-benar tampil, bukan hanya membaca kode:

| Skrip | Yang dibuktikan |
| --- | --- |
| `verify-pages.mjs` | Halaman SSR + island React memanggil API dan merender data |
| `verify-stocks.mjs` | Angka laporan stok cocok dengan server; filter tanggal/status |
| `verify-sheets.mjs` | Sheet penerimaan: buka/tutup + sinkron URL, deep link `?view=`, pindah ke `?edit=`, lebar sheet |
| `verify-permission.mjs` | Petugas: baris orang lain tanpa Ubah, detail tetap bisa dibuka, sheet ubah diblokir |
| `verify-write.mjs`, `verify-update.mjs`, `verify-csrf-validation.mjs` | Alur simpan/ubah lewat sheet, dan kegagalan CSRF/validasi |
| `verify-medicines.mjs` | Master obat: petugas tanpa tombol tulis, supervisor bisa menyimpan, filter status lewat server |

Pemakaian umum (cookie sesi HttpOnly hanya bisa dipasang dari CDP, jadi harus
diberikan sebagai argumen):

```bash
node scripts/verify-medicines.mjs <chromePath> <petugasCookie> <supervisorCookie>
```

Chrome headless perlu dijalankan di luar sandbox ketat (butuh spawn proses dan
named pipe), dan dev server Astro (`pnpm dev`) serta backend CI4 harus hidup.

## Dokumentasi terkait

- [README utama](../README.md) — setup backend, endpoint API, akun demo.
- [Tema frontend](../docs/frontend-theme.md) — token warna dan tipografi.
- [CORS backend](../docs/backend-cors.md) — konfigurasi lintas origin dan `/api/csrf`.
- [Postman Collection](../docs/postman.md) — uji API langsung tanpa UI.
- [Keamanan](../docs/security.md#proteksi-csrf) — alur token CSRF.

## Catatan

- Pesan sukses tampil sebagai toast sonner di kanan atas (host `Toaster` di
  `app-shell.tsx`); `Feedback` inline hanya untuk error.
- Cookie session `ci_session` maupun cookie CSRF `csrf_cookie_name`
  keduanya HttpOnly; token CSRF selalu diambil lewat header respons.
- Jangan membuat `package-lock.json`/`yarn.lock` di folder ini; paket
  dikelola pnpm (`pnpm-lock.yaml`).
