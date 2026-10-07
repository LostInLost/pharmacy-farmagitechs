# AGENTS.md — Panduan Asisten AI

> Anda (agen AI) bekerja di repositori **Pharmacy Farmagitechs** — aplikasi pencatatan penerimaan obat dan laporan stok untuk fasilitas kesehatan. Dokumen ini merangkum arsitektur, alat, lingkungan, dan jebakan yang sudah terbukti agar Anda tidak menebak dan tidak mengulang kesalahan lama. Bahasa kerja: **Indonesia**.
>
> Sumber lengkap: [README.md](README.md) (hub) → [Peta Dokumentasi](README.md#peta-dokumentasi). Riwayat keputusan: [.harness/memory/](.harness/memory), diringkas di [docs/ai-memory.md](docs/ai-memory.md).

## 1. Ringkasan Stack

| Lapisan | Teknologi |
| --- | --- |
| Backend | PHP ≥ 8.2 + CodeIgniter 4.7.4, arsitektur berlapis (Controller → Service → Repository → Policy) |
| Database | MySQL 8.4.3 (via Laragon, port 3306) — `pharmacy_farmagitechs`, `pharmacy_farmagitechs_test`, `pharmacy_farmagitechs_baseline` |
| UI klasik | CI4 views (cangkang) + jQuery di `public/assets/js` |
| Frontend alternatif | Astro 7.3.5 SSR (adapter Node) + React 19 + Tailwind 4 + shadcn/ui, di `frontend/`, **pnpm-only**, Node ≥ 22.12 |
| Test | PHPUnit 10 via `composer run test` (MySQL, bukan SQLite); Postman/Newman di `postman/` |
| CI | GitHub Actions `.github/workflows/phpunit.yml` |

Status terakhir: **179 test / 640 assertion** hijau dan **57 request Postman** — pertahankan tetap hijau setiap mengubah kode.

## 2. Struktur Proyek

```
app/              Backend CI4: Controllers/Api (HTTP), Controllers/Web (cangkang), Services,
                  Repositories, Policies, Validation, Filters, Models, Database/{Migrations,Seeds},
                  Config, Language/{id,en}, Views, Helpers
docs/             Dokumentasi pendalaman (database, security, conventions, testing, postman,
                  backend-cors, frontend-theme, ai-memory)
frontend/         Astro SSR + shadcn/ui (src/features, src/components, src/lib)
postman/          Collection + environment (Newman)
public/assets/    UI klasik: js/app.js (namespace Farmasi), js/lib/{csrf,api,ui}.js, js/pages/
scripts/          Runner script composer: run-tests.php (test), db-bootstrap.php (migrate/seed)
tests/            PHPUnit (Feature, database, unit); docs di tests/README.md
writable/         Runtime CI4 (session, logs, cache) — JANGAN diedit manual
build/ vendor/    Artefak test & dependensi — JANGAN diedit
.harness/         Memory AI (decisions/learnings/tasks, git-tracked); .harness/tmp/ gitignored
```

## 3. Aturan Arsitektur (WAJIB dipatuhi)

Detail: [docs/conventions.md](docs/conventions.md).

- **Controller Api** hanya menerjemahkan HTTP ↔ service. Tanpa SQL, tanpa keputusan hak akses.
- **Service** memegang satu transaksi per operasi, menerima data + id aktor, tidak mengenal session/request. Hanya service paling luar yang membuka transaksi; service yang dipanggil tidak membuka transaksi sendiri.
- **Repository** menyembunyikan Query Builder; service tidak tahu nama tabel.
- **Policy** (`app/Policies`) memegang keputusan hak; dipanggil service di dalam transaksi sebelum write.
- **Validator** (`app/Validation`) memusatkan aturan payload. JS tidak boleh menduplikasi validasi/policy — `can_update` dari API hanya affordance tampilan.
- **AuditService** satu-satunya penulis audit (`logCreated/logUpdated/logDeleted`). Tabel `audit_logs` polimorfik (`entity_type`/`entity_id`); kolom `action` berisi **kunci i18n** (`Audit.receptions.action.create`), label di `app/Language/{id,en}/Audit.php`.
- **Stok** dibaca dari ledger `stock_movements` (sumber tunggal, write-through). Jangan agregasi dari tabel lain.
- **Web controller** hanya render cangkang + `window.FARMASI_BOOT`; data via `/api/*`.
- **Auth**: session CI4 manual (bukan Shield), permission di-hardcode per role di `Config`. Identitas pembuat/pengubah SELALU dari session, bukan body request. Kata sandi hash Argon2id.
- **CSRF global** termasuk `/api/*` via filter kustom `CsrfFilter` (403 JSON + header `X-CSRF-TOKEN` segar); `GuestFilter` menolak dengan 403 (bukan redirect). CORS hanya untuk `http://localhost:4321`.
- **Tanpa DELETE** untuk master obat — nonaktifkan lewat `is_active = 0`. Hak tulis master obat khusus supervisor (`medicine.write`). Pola yang sama dipakai master pemasok (`supplier.write`, unique `name`).
- Gaya kode: minimalkan komentar; nama ekspresif dulu; komentar hanya untuk algoritma non-obvious.

## 4. Frontend Astro (`frontend/`)

- **pnpm only** (Node ≥ 22.12). Perintah: `pnpm dev` (port 4321), `pnpm build`, `pnpm typecheck`, `pnpm lint`, `pnpm format`.
- Login memakai session CI4: `POST /api/login` → cookie `ci_session` + CSRF dari `GET /api/csrf`; identitas + `permissions` role dari `GET /api/me`.
- Guard rute: middleware chaining `sequence(session → guest → authenticated)` (deny-by-default, SSR aktif via `@astrojs/node`).
- Struktur: `src/features` (fitur, validasi Zod v4), `src/components` (shell: app-sidebar gaya sidebar-07 grup Utama/Operasional/Master Data), fondasi di `src` terpisah dari fitur — foundations TIDAK boleh mengimpor features.
- Pola halaman: **sheet** untuk tambah/detail/ubah (`?new=1` / `?view=` / `?edit=`), bukan rute terpisah.
- Komponen shadcn yang sudah dikustomisasi (auth/tema) di-port manual — jangan asal `shadcn add` yang bisa menimpa.
- Verifikasi wajib: `pnpm typecheck` + `pnpm lint` + `pnpm build`.
- Bila ragu soal API/sintaks Astro, cek dokumentasi resmi lewat MCP **astro** (`mcp__astro__search_astro_docs`) sebelum menebak.

## 5. Lingkungan Mesin (Windows) & Perintah

- **PHP**: pakai PHP system `C:\Users\php8.4\php.exe` (8.4.x, `mysqli` aktif di php.ini user). PHP Laragon TIDAK dipakai. Bila composer butuh ekstraksi zip: `php -d extension=zip`.
- **MySQL**: HANYA ada di Laragon (`E:\laragon\bin\mysql\mysql-8.4.3-winx64`, port 3306, root tanpa password). **Laragon harus berjalan** — `mysqld` tidak bisa distart manual (paket kehilangan folder `lib\`). Cek port: `Get-NetTCPConnection -LocalPort 3306 -State Listen`. Jika kosong, test gagal `HY000/2002 connection refused`.
- **npm/npx rusak** di mesin ini — panggil node langsung, contoh Newman: `node node_modules/newman/bin/newman.js`.
- **Port 8080** bisa dipegang proses PHP zombie lama (gejala "extension mysqli not loaded" palsu). Cek `Get-NetTCPConnection -LocalPort 8080 -State Listen` sebelum start server.
- Setup dari nol: `copy .env.example .env` (sesuaikan DB) → `composer install` → `composer db:bootstrap` (migrate + `StockSeeder` + `DemoUsersSeeder`) → `php spark serve --port 8080`. Script `composer` lain: `db:migrate`, `db:seed`, `db:refresh` (kembalikan baseline setelah Newman).
- Test backend: `composer run test` (memakai MySQL, butuh Laragon jalan). Env var OS **tidak** menimpa `.env` CI4 — untuk mengarahkan DB, edit `.env`.
- Akun demo: `supervisor/supervisor123` (Supervisor), `petugas/petugas123` (Petugas).

## 6. Jebakan Sandbox DSH (kritis untuk agen)

- **`is_writable()` selalu false di sandbox** (padahal tulis berhasil) → CI4 menolak start session → semua halaman 500. Jalankan server PHP `php spark serve` **unconfined** (`sandbox_permissions: danger-full-access`) untuk uji HTTP live. Ini bukan masalah izin nyata.
- **`pnpm build` di sandbox sempit bisa EPERM** (esbuild) → jalankan unconfined bila perlu.
- **git push**: `sh.exe` (bootstrap credential helper) diblokir sandbox. Resep yang terbukti: ambil kredensial dari `git-credential-manager.exe get`, tulis script askpass ke `.harness/tmp/askpass.cmd`, set `GIT_ASKPASS` + `GIT_TERMINAL_PROMPT=0`, push, lalu **hapus askpass.cmd segera** (berisi token) dan scan workspace. Detail: memory `.harness/memory/learnings/2026-10-07T09-36-26-*`.
- **Jangan gunakan MCP GitHub untuk commit/push** (`push_files` dsb.) — itu membuat commit terpisah di remote dan menelantarkan commit lokal. MCP GitHub hanya untuk **verifikasi** (list_commits, list_branches).
- **Repo dikerjakan multi-tab**: commit segera setelah kerja selesai; commit SELALU ber-pathspec eksplisit (commit tanpa pathspec menyapu staging sesi paralel); hindari `git reset --hard` — bisa menghapus kerja tab lain yang belum di-commit.

## 7. Memory AI & MCP (Deepseek Harness)

Proyek ini dikembangkan bersama AI dan **memorinya di-commit ke repo** di [.harness/memory/](.harness/memory) (`decisions/`, `learnings/`, `tasks/`), katalog manusia di [docs/ai-memory.md](docs/ai-memory.md).

- **Sebelum bekerja**: `memory_search` dulu untuk topik terkait (mis. "stok", "audit", "astro middleware", "sandbox"). Keputusan yang sudah tercatat **mengalahkan** inferensi Anda sendiri — jangan re-derive, dan jangan kontradiksi diam-diam.
- **Setelah keputusan/pelajaran penting**: `memory_write` satu entri tajam (summary membawa faktanya, tags relevan, confidence jujur). Entri yang menggantikan entri lama ditandai `supersedes:<stempel-waktu>`. Jangan catat hal trivial (typo, output rutin).
- **MCP tersedia**: **github** (OAuth, profil web), **gitlab** (via mcp-remote), **shadcn/ui** (bundel lokal; pencarian butuh `components.json` — jalankan dari `frontend/`), dan **astro** (kedatangan baru; bundel `@local/astro-mcp` via mcp-remote) untuk dokumentasi resmi Astro — tool `mcp__astro__search_astro_docs`.
- Digunakan untuk: verifikasi remote, dokumentasi, mencari komponen shadcn — bukan pengganti git lokal (lihat §6).
- Update [docs/ai-memory.md](docs/ai-memory.md) bila menambah entri bermakna agar katalog tetap sinkron.

## 8. Dokumentasi & Konvensi Penulisan

- README adalah **hub ringkas**: jangan tumbuhkan isinya; konten pendalaman masuk ke `docs/*` dan di-link dari [Peta Dokumentasi](README.md#peta-dokumentasi) (nama berkas ditulis tebal sebagai link, bukan code span).
- Endpoint API baru wajib: terdaftar di tabel endpoint README, ada di Postman collection (`postman/`), tercakup test, dan perilaku hak/CSRF konsisten dengan [docs/security.md](docs/security.md).
- Perubahan skema: migrasi baru (jangan edit migrasi lama), ERD di [docs/database.md](docs/database.md) diperbarui, pastikan jalan di MySQL **dan** SQLite (test punya jalur SQLite3).
- Bahasa dokumen: Indonesia. UI mendukung `id`/`en` via `app/Language` — string UI baru masuk kedua locale.

## 9. Definition of Done

1. `composer run test` hijau (Laragon menyala) — atau alasan eksplisit bila tidak bisa dijalankan.
2. Frontend sentuh: `pnpm typecheck` + `pnpm lint` + `pnpm build` bersih.
3. API berubah: Newman collection dijalankan; README + docs diperbarui.
4. Keputusan desain/pelajaran teknis baru: `memory_write`.
5. Kerja selesai: commit ber-pathspec eksplisit segera (multi-tab!).
