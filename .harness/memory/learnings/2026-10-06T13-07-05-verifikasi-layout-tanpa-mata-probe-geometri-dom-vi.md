---
title: "Verifikasi layout tanpa mata: probe geometri DOM via Chrome headless"
type: learning
summary: "Verifikasi layout tanpa bisa melihat gambar: dump HTML via FeatureTestTrait, sisipkan probe DOM, baca geometri lewat `chrome --headless --dump-dom`; butuh danger-full-access dan file screenshot harus dihapus dulu sebelum ditimpa"
tags: ["verifikasi", "chrome-headless", "layout", "testing", "sandbox", "farmagitechs"]
source: "dsh"
confidence: medium
scope: project
created_at: "2026-10-06T13:07:05Z"
updated_at: "2026-10-06T13:07:05Z"
---

## Konteks
Model tanpa input gambar tidak bisa memeriksa screenshot. Alternatif yang terbukti jalan di repo ini: ukur geometri DOM langsung di Chrome headless.

## Langkah
1. Dump HTML hasil render nyata: buat test sementara `CIUnitTestCase` + `FeatureTestTrait`, tulis `$result->response()->getBody()` ke `writable/tmp-render/*.html`. Hapus test itu setelah selesai (jangan di-commit).
2. Sisipkan probe sebelum `</body>`: script yang menulis `JSON.stringify({...})` (dari `getBoundingClientRect()` + `getComputedStyle()`) ke `<div id="probe">`.
3. Baca hasil: `chrome --headless=new --dump-dom --virtual-time-budget=3000 <file-url>` lalu filter baris `id="probe"`.

## Jebakan yang sudah kena
- **Chrome headless diblokir sandbox DSH** (crashpad `OpenProcess: Access is denied`) → perlu `sandbox_permissions: danger-full-access`.
- **Chrome gagal menimpa file screenshot yang sudah ada** (diam-diam, exit 0) → hapus dulu file target, atau pakai nama baru.
- **`--user-data-dir` wajib**; pakai folder unik per proses agar tidak rebutan lock.
- **`getComputedStyle` menipu untuk anak elemen `d-lg-none`**: anaknya tetap melaporkan `display:block` walau induknya tidak dirender. Ukur dengan `getBoundingClientRect()` + cek `offsetParent`/lebar>0.
- **PowerShell: `` `r `` di dalam string ganda = carriage return**, bukan literal — merusak URL (`...tmp-render` + `r` + `eceptions.html`). Pakai variabel URL utuh.
- Overflow halaman di HTML hasil test berasal dari **toolbar debug CI4** yang dirender statis saat dibuka dari `file://`; di server nyata toolbar itu `position: fixed`. Bukan bug layout.

## Kegunaan
Memverifikasi klaim layout (posisi sticky, drawer offcanvas, elemen menempel dasar) tanpa mata: bandingkan koordinat `x/y/bottom` terhadap ekspektasi, dan uji state interaktif dengan memanggil API Bootstrap (`bootstrap.Offcanvas.getOrCreateInstance(el).show()`) di dalam probe.