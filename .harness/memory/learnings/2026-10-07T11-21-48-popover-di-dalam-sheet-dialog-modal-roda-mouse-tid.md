---
title: "Popover di dalam Sheet/Dialog modal: roda mouse tidak bisa menggulir konten yang di-portal ke body — perbaikannya Popover `modal`"
type: decision
summary: "Gejala: daftar combobox (Popover Radix, konten di-portal ke document.body) di dalam Sheet tidak bisa digulir dengan roda mouse, padahal scrollbar manual dan scroll programatik (scrollIntoView) jalan — clientHeight 256 vs scrollHeight 1288 tapi wheel tidak mengubah scrollTop. Sebab: Sheet = Radix Dialog modal yang memasang react-remove-scroll; lock itu mem-preventDefault wheel untuk target di luar subtree Sheet, dan konten Popover yang di-portal dianggap 'luar'. Perbaikan: `<Popover modal>` — memasang scroll-lock bersarang pada konten sendiri, persis yang dilakukan Radix Select. Dibuktikan via CDP: scrollTop 0 → 400, Escape/klik-luar tetap menutup popover saja, Sheet tidak ikut tertutup."
tags: ["radix-ui", "popover", "sheet", "dialog", "react-remove-scroll", "scroll-lock", "combobox", "cdp-verification", "frontend", "debugging"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T11:21:48Z"
updated_at: "2026-10-07T11:21:48Z"
---

## Gejala
Daftar combobox (konten `Popover` Radix) di dalam `Sheet` **tidak bisa digulir dengan roda mouse**. Yang menyesatkan: scrollbar bisa digeser manual, `scrollIntoView()` berhasil, dan `overflow-y-auto` terpasang benar (`clientHeight` 256 vs `scrollHeight` 1288). Jadi daftarnya *bisa* di-scroll — yang diblokir hanya event wheel.

## Akar masalah
`Sheet` (via `@radix-ui/react-dialog`) selalu `modal`, sehingga memasang `react-remove-scroll`. Lock itu menambahkan listener `wheel` non-passive di `document` dan memanggil `preventDefault()` bila target dianggap di luar area yang diizinkan. Konten `Popover` di-portal ke `document.body` — **di luar subtree Sheet** — sehingga dianggap luar dan roda mouse-nya diblokir.

`Radix Select` menghadapi masalah yang sama dan solusinya: kontennya membungkus diri dengan `RemoveScroll` sendiri (scroll-lock bersarang).

## Perbaikan
Pakai `<Popover modal>` pada combobox. Mode modal memasang scroll-lock bersarang pada konten popover, sehingga roda mouse bekerja, tanpa mengubah cara menutup (Escape/klik-luar tetap menutup popover saja, bukan Sheet).

## Cara membuktikan (resep CDP yang berguna lagi)
1. Bandingkan dengan **kontrol**: `<div className="max-h-64 overflow-y-auto">` di dalam Sheet yang sama — kontrol tergulir, daftar tidak. Ini memisahkan bug komponen dari metode uji.
2. Ukur `scrollTop` sebelum/sesudah `Input.dispatchMouseEvent` bertipe `mouseWheel` (butuh `mouseMoved` lebih dulu di titik yang sama).
3. `list.scrollTop = 120` untuk memastikan elemennya memang scrollable.
4. Tempel `Event.prototype.preventDefault` untuk menangkap stack pelaku — di sini muncul `react-remove-scroll`.

Setelah perbaikan: `scrollTop` 0 → 400; panah bawah menggulir item tersorot ke tampilan (`scrollIntoView({block:'nearest'})` pada item `aria-activedescendant`); Escape/klik-luar menutup popover saja.