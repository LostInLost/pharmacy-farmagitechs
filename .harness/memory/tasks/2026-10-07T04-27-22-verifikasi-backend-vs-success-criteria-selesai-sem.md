---
title: "Verifikasi backend vs success criteria selesai semua lolos"
type: task
summary: "Verifikasi backend vs success criteria 2026-10-07: 125 test OK + skenario 1-5 live lolos, DB dev bersih kembali"
tags: ["farmasi", "verifikasi", "backend", "testing", "environment"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T04:27:22Z"
updated_at: "2026-10-07T04:27:22Z"
---

Verifikasi backend vs success criteria soal (2026-10-07): PHPUnit 125 test/397 assertion OK. Live HTTP di 127.0.0.1:8090 setelah reset DB ke baseline (migrate rollback+migrate+seed StockSeeder+DemoUsersSeeder): baseline on_date=2026-10-03 persis (101=142/134/8, 102=16, 103=15, 104=3, 106=0, 107=6/0/6); skenario 1-5 soal semuanya lolos (create 201 → 144/8; update → 141/18/3; PUT identik 2x idempoten; supervisor ubah milik petugas: pembuat tetap petugas + 5 log CREATE/UPDATE; petugas ubah milik supervisor → 403 tanpa ubah stok/items/log, can_update=false); tanpa login → 401; 7 aturan validasi → 422 dengan pesan jelas (duplikat reference, duplikat batch, expires==tgl terima, expiry inkonsisten, qty 0, items kosong, supplier/obat nonaktif) dan DB tidak berubah. Logout 200 + /api/me jadi 401. Temuan env: (1) mysqld Laragon hanya bisa start via Laragon GUI/pid lama — start langsung gagal component_reference_cache.dll (paket kehilangan folder lib); (2) server PHP dev HARUS unconfined (danger-full-access) karena token confined berlabel Low ditolak tulis ke writable/session walau ACL sudah diperbaiki via skill diagnose-windows-sandbox-acl (grant terverifikasi, rollback tersedia di .acl-diag/acl-20261007); (3) CSRF token berotasi tiap respons — klien harus sinkron dari header X-CSRF-TOKEN terakhir, kalau tidak dapat 403 csrf yang menyesatkan; (4) DB dev dikembalikan ke baseline bersih setelah verifikasi.