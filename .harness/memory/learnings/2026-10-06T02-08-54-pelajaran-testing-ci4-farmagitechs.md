---
title: "Pelajaran testing CI4 farmagitechs"
type: learning
summary: "Test CI4: wajib $namespace=null, trait yang migrasi test DB, isolasi data lewat fixture clear(), npm/npx rusak pakai node langsung"
tags: ["farmasi", "testing", "codeigniter", "environment"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T02:08:54Z"
updated_at: "2026-10-06T02:08:54Z"
---

CI4 test DB: CIUnitTestCase default `$namespace = 'Tests\Support'` sehingga hanya migrasi contoh yang jalan — WAJIB set `protected $namespace = null;` di test yang butuh tabel aplikasi, kalau tidak error "Table users doesn't exist". `$DBGroup = 'tests'` dan `$refresh = true` sudah default. `spark migrate -g tests` TIDAK memigrasi ke DB test (MigrationRunner::setGroup hanya ubah filter riwayat, koneksi tetap default) — biarkan trait yang menangani. Data antar test tidak terisolasi otomatis: buat fixture dengan method clear() + emptyTable() dan panggil di setUp. Catatan lain: `php spark db:seed` butuh CI_ENVIRONMENT default; `npx`/`npm` di mesin ini rusak (alias memotong argumen) — pakai `node "C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js"` dan `node node_modules/newman/bin/newman.js`.