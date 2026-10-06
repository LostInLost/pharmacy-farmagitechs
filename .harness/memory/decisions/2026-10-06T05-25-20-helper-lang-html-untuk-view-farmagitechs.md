---
title: "Helper lang_html() untuk view farmagitechs"
type: decision
summary: "Helper lang_html() untuk view: nama dipilih agar tidak menyesatkan (trans() Laravel tidak escape), tanpa parameter context karena esc('html') sudah ENT_QUOTES, dan hanya untuk view"
tags: ["farmasi", "lokalisasi", "helper", "codeigniter", "decision", "security"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-06T05:25:20Z"
updated_at: "2026-10-06T05:25:20Z"
---

# Helper lang_html() untuk view farmagitechs

## Keputusan
Pola `esc(lang(...))` di view diganti helper `lang_html($line, $args = [], $locale = null)` yang mengembalikan `esc(lang(...))`. Helper hanya untuk view; lapisan API/service/validator tetap `lang()` polos.

## Alasan memilih nama `lang_html` (bukan `trans`)
- `trans()` di Laravel TIDAK meng-escape (escaping dilakukan `{{ }}`), jadi memakai nama itu untuk helper escape-otomatis menyesatkan developer yang terbiasa Laravel.
- `lang_html` menyatakan konteks keluaran (HTML) secara eksplisit.

## Alasan tanpa parameter `$context`
`esc()` konteks `html` memakai `htmlspecialchars` dengan flag `ENT_QUOTES | ENT_SUBSTITUTE` (vendor/codeigniter4/framework/system/ThirdParty/Escaper/Escaper.php:162,189), jadi sudah aman untuk atribut bertanda kutip ganda maupun tunggal. Kalau suatu saat butuh `js`/`css`/`url`, pakai `esc(lang(...), 'js')` langsung.

## Titik yang SENGAJA tidak diubah (kalau diubah jadi bug)
1. `form.php` — `json_encode(lang('Reception.js.*'))` untuk `window.RECEPTION_DATA.i18n`: konteks JS, bukan HTML. JS sudah punya `escapeHtml()` sendiri; meng-escape di sini = double-escape.
2. `form.php` — `$summary` ternary `lang(...)` yang di-`esc($summary)` saat output: sudah benar, itu escape variabel hasil terjemahan.
3. `layout.php` — `esc($title ?? lang('App.brand'))`: `$title` sudah diterjemahkan di controller; memanggil `lang_html($title)` akan menerjemahkan hasil terjemahan (rapuh meski kebetulan tidak merusak).
4. `app/Views/errors/html/*.php` — view error bawaan framework, string statis tanpa input user.

## Implementasi
- `app/Helpers/lang_html_helper.php` — file helper CI4 pertama di proyek ini (sebelumnya `app/Helpers/Hash.php` itu kelas namespaced, bukan `*_helper.php`).
- Didaftarkan di `app/Config/Autoload.php`: `$helpers = ['form', 'lang_html']`.
- 59 pemakaian di 5 view diganti. `esc(lang(` sekarang 0 di `app/Views`.

## Bahaya yang dimitigasi
Validator menyisipkan input user ke pesan terjemahan (`reference_taken`). Rantainya: validator → JSON → `reception-form.js` yang menjalankan `escapeHtml()` sebelum `innerHTML`. Kalau validator ikut meng-escape, escape jadi dua lapis dan user melihat `&amp;quot;`. Karena itu helper sengaja hanya didaftarkan untuk view, dan docblock memuat peringatan agar tidak dipakai di lapisan API.

## Verifikasi
- 60 test / 144 assertion hijau (44 lama + 9 LangHtmlTest + 7 render test baru).
- Newman 23 request / 51 assertion hijau.
- Smoke test HTTP id + en: tidak ada entity nyasar (`&amp;amp;`/`&quot;`) di teks label.