# Memori AI dan Riwayat Proses

> Bagian dari [README](../README.md). Lihat juga [Konvensi Kode](conventions.md#lapisan) dan [Pengujian](testing.md).

Proyek ini dikembangkan bersama asisten AI yang menyimpan memorinya di dalam repositori, tepatnya di folder [`.harness/`](../.harness). Tujuannya agar keputusan desain, pelajaran teknis, dan progres tiap sesi bisa ditelusuri kembali — baik oleh manusia maupun oleh sesi AI berikutnya.

## Isi folder `.harness/`

| Path | Isi | Status git |
| --- | --- | --- |
| [`.harness/memory/decisions/`](../.harness/memory/decisions) | Keputusan desain beserta alasannya | Dilacak |
| [`.harness/memory/learnings/`](../.harness/memory/learnings) | Pelajaran teknis, jebakan, dan cara mengatasinya | Dilacak |
| [`.harness/memory/tasks/`](../.harness/memory/tasks) | Progres dan rencana per sesi kerja | Dilacak |
| `.harness/tmp/` | Arsip sementara (mis. salinan README sebelum dipangkas) | Diabaikan `.gitignore` |

Per 7 Oktober 2026 ada **46 entri** yang ikut ter-commit, sehingga riwayatnya terlihat langsung di GitHub tanpa perlu alat khusus. Katalog di bawah ikut bertambah setiap kali sesi AI mencatat temuan baru.

## Format satu entri

Setiap entri adalah satu berkas markdown dengan frontmatter YAML:

```yaml
---
title: "Keputusan stack farmasi"
type: decision          # decision | learning | task
summary: "Stack CI4 + MySQL, Hono ditolak, Astro ditunda setelah backend tuntas"
tags: ["farmasi", "stack", "codeigniter", "frontend"]
source: "dsh"           # asal catatan (sesi harness, commit, atau hasil probe)
confidence: high        # high | medium
scope: project
created_at: "2026-10-05T11:46:10Z"
updated_at: "2026-10-05T11:46:10Z"
---

Isi catatan...
```

Penamaan berkas memakai stempel waktu `YYYY-MM-DDTHH-mm-ss` + slug judul, sehingga urutan kronologisnya jelas dari nama berkas. Entri yang menggantikan entri lama menandainya lewat tag `supersedes:<stempel-waktu>`, contohnya keputusan [`audit_logs` polimorfik](../.harness/memory/decisions/2026-10-07T00-51-55-audit-log-generik-reception-logs-audit-logs-polimo.md) yang menggantikan desain audit trail awal.

## Ringkasan proses pengerjaan

### Fase 1 — Fondasi (5–6 Oktober 2026)

Stack dikunci di awal: **PHP CodeIgniter 4 + MySQL**, dengan Hono ditolak karena backend wajib PHP dan Astro ditunda agar autentikasi serta CORS tidak menambah risiko. Skema database dirancang lebih dulu (8 tabel, 4 di antaranya provisional menunggu berkas seed), permission di-hardcode per role alih-alih memakai Shield, dan autentikasi memakai session CI4 manual.

Backend lalu dibangun berlapis (Controller → Service → Repository → Policy), disusul UI Bootstrap, lokalisasi `id`/`en`, test otomatis, dan Postman collection. Di titik ini **37 test** dan **23 request Newman** sudah hijau.

### Fase 2 — Keamanan dan frontend alternatif (6 Oktober 2026)

Perhatian berpindah ke jalur keamanan: CSRF dijadikan global termasuk `/api/*` lewat `CsrfFilter` kustom, `GuestFilter` menolak rute tamu dengan `403` (bukan redirect), dan CORS dibuka hanya untuk origin frontend `http://localhost:4321`. Setelah itu frontend **Astro + shadcn** dibangun dengan middleware chaining `session → guest → authenticated`, memakai cookie sesi CI4 dan endpoint `/api/csrf`.

Sepanjang fase ini jumlah test naik dari 37 menjadi **99**, dan assertion dari 93 menjadi **274**.

### Fase 3 — Pendalaman model data dan UI (7 Oktober 2026)

Bagian data diperdalam: laporan stok dipindahkan ke **ledger `stock_movements`** sebagai sumber tunggal, `reception_logs` digeneralisasi menjadi **`audit_logs` polimorfik**, dan penulisan audit dipusatkan di `AuditService`. Label aksi audit disimpan sebagai kunci i18n (`Audit.receptions.action.create`) dengan terjemahan di `app/Language/{id,en}/Audit.php`.

Di sisi antarmuka, halaman Receptions dan Laporan Stok dibuat di Astro, sidebar di-port ke gaya sidebar-07, master obat ditambahkan dengan hak tulis khusus supervisor, dan pengelolaan penerimaan dipindahkan ke pola sheet (`?new=1` / `?view=` / `?edit=`). Fase ini berakhir di **147 test / 477 assertion** dan **43 request / 104 assertion Postman**.

### Fase 4 — Dokumentasi (7 Oktober 2026)

README dipangkas dari 18.826 menjadi ~7.600 karakter dan diubah menjadi hub dengan [Peta Dokumentasi](../README.md#peta-dokumentasi) yang menaut langsung ke bagian relevan tiap berkas. Konten pengujian dipindah ke [`docs/testing.md`](testing.md), struktur proyek ke [`docs/conventions.md`](conventions.md#struktur-proyek), dan dokumentasi Postman dicerminkan ke [`docs/postman.md`](postman.md).

## Metrik perjalanan

Angka-angka ini dikutip dari catatan memori pada tiap titik, jadi terlihat bagaimana cakupan test tumbuh seiring fitur:

| Tanggal | Test | Assertion | Request Postman | Tonggak |
| --- | --- | --- | --- | --- |
| 2026-10-06 | 37 | 93 | 23 | Backend + UI + test + Postman selesai (`d2cf754`) |
| 2026-10-06 | 60 | 144 | 23 | Runner `composer run test` + perbaikan `mysqli` |
| 2026-10-06 | 68 | 159 | 26 | CSRF global untuk `/api/*` |
| 2026-10-06 | 74 | 144 | 30 | `GuestFilter` 403 view/JSON |
| 2026-10-06 | 99 | 274 | 30 | Middleware chaining Astro |
| 2026-10-06 | 101 | 292 | 30 | `StockSeeder` menggantikan seeder provisional |
| 2026-10-07 | 102 | 296 | 30 | `audit_logs` polimorfik (`5d1b06d`) |
| 2026-10-07 | 115 | 368 | 30 | Ledger `stock_movements` (`b1243c1`) |
| 2026-10-07 | 125 | 397 | 30 | `AuditService` satu pintu audit |
| 2026-10-07 | 139 | 449 | 43 | Master obat |
| 2026-10-07 | 142 | 459 | 43 | `audit_logs.action` jadi kunci i18n |
| 2026-10-07 | **147** | **477** | **43** | Dokumentasi README hub + `docs/postman.md` |

## Katalog memori

### Keputusan desain

**2026-10-05**

- [Desain database farmasi](../.harness/memory/decisions/2026-10-05T11-46-10-desain-database-farmasi.md) — Skema DB farmasi: seed dipertahankan + 4 tabel users/receptions/items/logs, stok agregasi ledger
- [Keputusan stack farmasi](../.harness/memory/decisions/2026-10-05T11-46-10-keputusan-stack-farmasi.md) — Stack CI4 + MySQL, Hono ditolak, Astro ditunda setelah backend tuntas
- [Permission hardcoded farmasi](../.harness/memory/decisions/2026-10-05T11-46-10-permission-hardcoded-farmasi.md) — Permission farmasi di-hardcode per role, Shield ditolak, auth session CI4 manual

**2026-10-06**

- [Audit trail before/after farmagitechs](../.harness/memory/decisions/2026-10-06T03-34-39-audit-trail-before-after-farmagitechs.md) — Audit trail tetap reception-scoped + kolom data_before/data_after JSON; FK reception_id jadi RESTRICT; receipt_id statis di Postman…
- [Helper lang_html() untuk view farmagitechs](../.harness/memory/decisions/2026-10-06T05-25-20-helper-lang-html-untuk-view-farmagitechs.md) — Helper lang_html() untuk view: nama dipilih agar tidak menyesatkan (trans() Laravel tidak escape), tanpa parameter context karena…
- [CSRF global untuk web + /api/*, filter kustom CsrfFilter, token segar di header response](../.harness/memory/decisions/2026-10-06T09-01-14-csrf-global-untuk-web-api-filter-kustom-csrffilter.md) — CSRF kini global termasuk `/api/*`; `CsrfFilter` kustom mengembalikan 403 JSON + token segar untuk API dan redirect untuk web; semua…
- [Setup PHP & MySQL farmagitechs: PHP system, MySQL via Laragon](../.harness/memory/decisions/2026-10-06T10-25-11-setup-php-mysql-farmagitechs-php-system-mysql-via-.md) — PHP wajib dari system (C:\Users\php8.4\php.exe), MySQL hanya ada di Laragon sehingga Laragon harus jalan saat test; mysqld tidak bisa…
- [Filter guest farmagitechs: 403 view/JSON, bukan redirect](../.harness/memory/decisions/2026-10-06T12-24-58-filter-guest-farmagitechs-403-view-json-bukan-redi.md) — GuestFilter menolak login saat sesi aktif: web 403 + view \"Sudah Masuk\" (bukan redirect), API 403 JSON + X-CSRF-TOKEN segar; Postman…
- [Setup frontend Astro + shadcn preset b7D6016rcO dan login CI4](../.harness/memory/decisions/2026-10-06T12-41-53-setup-frontend-astro-shadcn-preset-b7d6016rco-dan-.md) — Frontend Astro pnpm-only di frontend/ (preset b7D6016rcO, base radix); login CI4 session+CSRF dengan retry; dashboard-01 diadaptasi dari…
- [CORS backend untuk frontend Astro (origin :4321 saja) + bootstrap CSRF /api/csrf](../.harness/memory/decisions/2026-10-06T13-18-59-cors-backend-untuk-frontend-astro-origin-4321-saja.md) — CORS CI4: allowedOrigins hanya http://localhost:4321 (tanpa wildcard), credentials true; filter cors global sebelum csrf agar 403 CSRF…
- [Middleware chaining Astro: session → guest → authenticated (SSR)](../.harness/memory/decisions/2026-10-06T14-43-08-middleware-chaining-astro-session-guest-authentica.md) — Guard rute Astro kini middleware chaining sequence(session, guest, authenticated): session isi locals.user dari GET /api/me (cookie…
- [Seeder lampiran farmagitechs: StockSeeder + migrasi usage details](../.harness/memory/decisions/2026-10-06T23-46-31-seeder-lampiran-farmagitechs-stockseeder-migrasi-u.md) — Lampiran seed_farmasi.sql dipindahkan ke StockSeeder (menggantikan ProvisionalStockSeeder), plus migrasi kolom used_at/unit_name dan…

**2026-10-07**

- [Refactor frontend lib→features+foundations dengan validasi Zod](../.harness/memory/decisions/2026-10-07T00-44-53-refactor-frontend-lib-features-foundations-dengan-.md) — Refactor frontend lib/→features+foundations selesai: Zod v4 (z.flattenError, looseObject, {error}), foundations tanpa impor features…
- [Audit log generik: reception_logs → audit_logs polimorfik](../.harness/memory/decisions/2026-10-07T00-51-55-audit-log-generik-reception-logs-audit-logs-polimo.md) — reception_logs digeneralisasi jadi audit_logs polimorfik (entity_type/entity_id, tanpa FK entity_id); migrasi 000011 backfill + down…
- [Ledger stock_movements sebagai sumber tunggal laporan stok](../.harness/memory/decisions/2026-10-07T01-19-22-ledger-stock-movements-sebagai-sumber-tunggal-lapo.md) — Ledger stock_movements jadi sumber tunggal laporan stok: skema, write-through, is_expired computed, dan jebakan (netting per batch,…
- [Label aksi audit: token kanonik di DB, terjemahan saat render](../.harness/memory/decisions/2026-10-07T02-01-24-label-aksi-audit-token-kanonik-di-db-terjemahan-sa.md) — Token kanonik di audit_logs.action, label aksi diterjemahkan saat render (commit 2e3b486).
- [AuditService: satu pintu penulisan audit, kontrak transaksi mengikuti pemanggil](../.harness/memory/decisions/2026-10-07T02-33-23-auditservice-satu-pintu-penulisan-audit-kontrak-tr.md) — AuditService (logCreated/logUpdated/logDeleted + forEntity) jadi satu-satunya penulis audit; tanpa transaksi sendiri;…
- [Halaman Receptions + Laporan Stok (mirror CI4) dan pembersihan demo dashboard](../.harness/memory/decisions/2026-10-07T03-25-55-halaman-receptions-laporan-stok-mirror-ci4-dan-pem.md) — Halaman Receptions + Laporan Stok di frontend Astro, demo dashboard-01 dibersihkan. Kunci: can_update hanya di index/show (bukan respons…
- [Sidebar gaya sidebar-07: port manual, grup Utama/Operasional](../.harness/memory/decisions/2026-10-07T05-14-59-sidebar-gaya-sidebar-07-port-manual-grup-utama-ope.md) — Sidebar di-port ke gaya sidebar-07 (collapsible=icon, SidebarRail, grup Utama/Operasional) via port manual, bukan `shadcn add` — karena…
- [Master obat: API + halaman Astro, tanpa DELETE dan audit lewat tabel polimorfik](../.harness/memory/decisions/2026-10-07T06-14-13-master-obat-api-halaman-astro-tanpa-delete-dan-aud.md) — Master obat (API + halaman Astro) selesai: hak tulis khusus supervisor lewat medicine.write, tanpa DELETE (is_active=0), audit pakai…
- [audit_logs.action menyimpan kunci i18n (Audit.receptions.action.create), label di app/Language/{id,en}/Audit.php; ENUM->VARCHAR(100); commit 45a784f..0fe6a02, 142 test hijau MySQLi+SQLite3](../.harness/memory/decisions/2026-10-07T06-54-34-audit-logs-action-menyimpan-kunci-i18n-audit-recep.md) — audit_logs.action menyimpan kunci i18n (Audit.receptions.action.create), label di app/Language/{id,en}/Audit.php; ENUM->VARCHAR(100);…
- [Penerimaan lewat sheet + permissions via /api/me (jalur ke JWT cookie)](../.harness/memory/decisions/2026-10-07T07-39-41-penerimaan-lewat-sheet-permissions-via-api-me-jalu.md) — Penerimaan kini dikelola lewat sheet (tambah/detail/ubah) di /receptions dengan URL ?new=1|?view=|?edit=; permissions role dikirim lewat…
- [README ringkas + Peta Dokumentasi ter-link; konten §6 pindah ke docs/testing.md](../.harness/memory/decisions/2026-10-07T09-09-23-readme-ringkas-list-peta-dokumentasi-konten-6-pind.md) — Keputusan user 2026-10-07: README dipangkas drastis (18.826 -> 7.220 char) menjadi pengantar + Peta Dokumentasi (list bullet, deep-link)…
- [Catatan Alat AI README: Deepseek Harness + plugin (memory/MCP); planning Muse/Mimo/Kimi, eksekutor Deepseek/GLM/Mimo/Kimi](../.harness/memory/decisions/2026-10-07T09-10-30-catatan-alat-ai-readme-deepseek-harness-plugin-mem.md) — Bagian \"Catatan Alat AI dan Referensi\" di README diperbarui sesuai info user 2026-10-07: tools = Deepseek Harness dengan plugin…
- [Referensi README + Astro (frontend stack: Astro 7.3.5 SSR, React 19, Tailwind 4, shadcn)](../.harness/memory/decisions/2026-10-07T09-12-09-referensi-readme-astro-frontend-stack-astro-7-3-5-.md) — Bullet \"Referensi\" di README ditambah Astro sesuai permintaan user 2026-10-07: \"dokumentasi resmi CodeIgniter 4 (backend) dan Astro…
- [Konvensi Peta Dokumentasi README: nama berkas jadi link tebal, bukan code span (biar jelas bisa diklik)](../.harness/memory/decisions/2026-10-07T09-21-22-konvensi-peta-dokumentasi-readme-nama-berkas-jadi-.md) — User 2026-10-07 bertanya apakah Peta Dokumentasi bisa diklik. Ternyata sudah link sejak awal, tapi nama berkas dibungkus backtick…
- [Dokumentasi riwayat memori AI + ringkasan proses + katalog entri](../.harness/memory/decisions/2026-10-07T09-50-51-docs-ai-memory-md-dokumentasi-riwayat-memori-ai-ha.md) — Berkas yang sedang Anda baca ini: isi `.harness/`, format entri, ringkasan 4 fase, dan katalog lengkap memori.

### Pelajaran teknis

**2026-10-05**

- [Environment & workaround farmagitechs](../.harness/memory/learnings/2026-10-05T12-43-23-environment-workaround-farmagitechs.md) — Environment farmagitechs: push via MCP, server harus unconfined, MySQL Laragon E:\, PHP pakai binary Laragon

**2026-10-06**

- [Pelajaran testing CI4 farmagitechs](../.harness/memory/learnings/2026-10-06T02-08-54-pelajaran-testing-ci4-farmagitechs.md) — Test CI4: wajib $namespace=null, trait yang migrasi test DB, isolasi data lewat fixture clear(), npm/npx rusak pakai node langsung
- [Runner composer test + perbaikan mysqli farmagitechs](../.harness/memory/learnings/2026-10-06T05-58-03-runner-composer-test-perbaikan-mysqli-farmagitechs.md) — composer run test via scripts/run-tests.php; menjalankan PHPUnit seperti vendor/bin/phpunit dulu, retry -d extension=mysqli hanya bila…
- [Prioritas env var test CI4: xml > .env > env OS](../.harness/memory/learnings/2026-10-06T06-38-45-prioritas-env-var-test-ci4-xml-env-env-os.md) — Prioritas env var test CI4: blok <env> phpunit.xml menang atas .env, dan .env menang atas env var OS — kebalikan intuisi. Terverifikasi…
- [Sandbox DSH: is_writable() false → CI4 session 500; jalankan server unconfined](../.harness/memory/learnings/2026-10-06T09-01-14-sandbox-dsh-is-writable-false-ci4-session-500-jala.md) — Sandbox DSH membuat `is_writable()` false untuk semua path (padahal tulis berhasil), sehingga CI4 menolak start session dan semua…
- [Portabilitas driver DB: migrasi Forge & query tanpa prefix](../.harness/memory/learnings/2026-10-06T10-32-10-portabilitas-driver-db-migrasi-forge-query-tanpa-p.md) — Fallback SQLite3 menyingkap dua bug MySQL-only: migrasi 000009 (information_schema + ALTER TABLE mentah) dan raw SQL StockRepository…
- [Repo dikerjakan multi-tab: commit segera, reset --hard bisa hapus kerja uncommitted](../.harness/memory/learnings/2026-10-06T10-44-39-repo-dikerjakan-multi-tab-commit-segera-reset-hard.md) — Repo ini dikerjakan beberapa tab bersamaan dan ada riwayat `git reset` ke `origin/main`, sehingga pekerjaan uncommitted berisiko hilang…
- [Verifikasi layout tanpa mata: probe geometri DOM via Chrome headless](../.harness/memory/learnings/2026-10-06T13-07-05-verifikasi-layout-tanpa-mata-probe-geometri-dom-vi.md) — Verifikasi layout tanpa bisa melihat gambar: dump HTML via FeatureTestTrait, sisipkan probe DOM, baca geometri lewat `chrome --headless…

**2026-10-07**

- [UI tidak reaktif di Astro: island tanpa client:load dan cache Vite basi](../.harness/memory/learnings/2026-10-07T04-54-12-ui-tidak-reaktif-di-astro-island-tanpa-client-load.md) — Island mati tanpa error: AppShell tanpa client:load + cache Vite basi (504 sonner.js); cara diagnosis via CDP
- [Commit tanpa pathspec menyapu staging sesi paralel; pecah dengan commit ber-pathspec](../.harness/memory/learnings/2026-10-07T05-34-33-commit-tanpa-pathspec-menyapu-staging-sesi-paralel.md) — Commit git tanpa pathspec menyapu file yang di-stage sesi paralel; perbaikan: pecah via reset --soft + commit ber-pathspec eksplisit;…
- [Cara menjalankan jalur test SQLite3 saat ekstensi sqlite3 mati (php -d extension=sqlite3 + config sementara ber-<env>), lang() hanya memuat berkas bila diminta per grup, API test dari PowerShell pakai file payload + cookie jar, dan Chrome headless harus diserahkan ke user](../.harness/memory/learnings/2026-10-07T06-55-56-cara-menjalankan-jalur-test-sqlite3-saat-ekstensi-.md) — Cara menjalankan jalur test SQLite3 saat ekstensi sqlite3 mati (php -d extension=sqlite3 + config sementara ber-<env>), lang() hanya…
- [Cara push dari sandbox: GIT_ASKPASS berisi token dari git-credential-manager, karena sh.exe diblokir](../.harness/memory/learnings/2026-10-07T09-36-26-cara-push-dari-sandbox-git-askpass-berisi-token-da.md) — 2026-10-07 push berhasil. Kendala: git spawn sh.exe (bootstrap credential helper) diblokir sandbox (Win32 error 5) -> \"could not read…

### Progres dan rencana

**2026-10-05**

- [Progress inisiasi farmagitechs](../.harness/memory/tasks/2026-10-05T12-43-23-progress-inisiasi-farmagitechs.md) — Progress: 5 commit init selesai (scaffold, struktur, migrasi, ERD, README); berikutnya implementasi backend

**2026-10-06**

- [Backend farmagitechs selesai](../.harness/memory/tasks/2026-10-06T02-08-54-backend-farmagitechs-selesai.md) — Backend + UI + test + Postman selesai di commit d2cf754; 37 test & 23 request Newman lolos
- [Integrasi Bootstrap + lokalisasi farmagitechs selesai](../.harness/memory/tasks/2026-10-06T04-46-22-integrasi-bootstrap-lokalisasi-farmagitechs-selesa.md) — UI Bootstrap 5.3.8 + lokalisasi id/en selesai (belum commit); 44 test & 23 request Newman lolos, bug redirect JS diperbaiki

**2026-10-07**

- [Verifikasi backend vs success criteria selesai semua lolos](../.harness/memory/tasks/2026-10-07T04-27-22-verifikasi-backend-vs-success-criteria-selesai-sem.md) — Verifikasi backend vs success criteria 2026-10-07: 125 test OK + skenario 1-5 live lolos, DB dev bersih kembali
- [Rencana redesign README sebagai hub dokumentasi + temuan audit dokumen](../.harness/memory/tasks/2026-10-07T07-08-18-rencana-redesign-readme-sebagai-hub-dokumentasi-te.md) — Audit 2026-10-07: README 18.826 char/10 bagian, hanya menautkan docs/database.md, docs/security.md, docs/conventions.md,…
- [Temuan audit Postman collection + rencana docs/postman.md](../.harness/memory/tasks/2026-10-07T07-26-14-temuan-audit-postman-collection-rencana-docs-postm.md) — Dokumentasi Postman hanya hidup di dalam JSON collection (info.description + 6 folder description + 43 request description) sehingga tak…
- [Eksekusi redesign README hub + docs/postman.md + repair mojibake collection (selesai, belum di-commit)](../.harness/memory/tasks/2026-10-07T08-10-33-eksekusi-redesign-readme-hub-docs-postman-md-repai.md) — Selesai dieksekusi 2026-10-07: README jadi hub (Peta Dokumentasi 9 baris + GET /api/csrf + Struktur Proyek…

## Cara memakai memori ini

- **Mencari konteks**: telusuri kategori di atas, atau cari langsung dengan kata kunci pada tag (mis. `csrf`, `ledger`, `sidebar-07`) lewat pencarian GitHub.
- **Membaca satu keputusan**: setiap entri memuat alasan, alternatif yang ditolak, dan commit terkait sehingga bisa ditelusuri ke kode.
- **Menambah catatan**: sesi AI menulis entri baru lewat tool memori harness, lalu meng-commit-nya dengan pesan `chore(memory): ...` — konvensi yang sudah dipakai sejak commit pertama memori.
- **Yang tidak ikut ter-commit**: isi `.harness/tmp/` bersifat sementara; berkas di sana boleh hilang kapan saja.
- **Memperbarui katalog**: katalog di atas adalah cuplikan per 7 Oktober 2026. Setiap kali ada entri memori baru, tambahkan barisnya di bagian yang sesuai — cukup satu baris berisi judul, tautan relatif ke berkas, dan ringkasannya.
