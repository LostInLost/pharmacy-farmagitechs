---
title: "Label aksi audit: token kanonik di DB, terjemahan saat render"
type: decision
summary: "Token kanonik di audit_logs.action, label aksi diterjemahkan saat render (commit 2e3b486)."
tags: ["audit-log", "i18n", "reception", "action-label", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T02:01:24Z"
updated_at: "2026-10-07T02:01:24Z"
---

## Keputusan
`audit_logs.action` menyimpan **token kanonik** (`CREATE`/`UPDATE`/`DELETE`), bukan kalimat tampilan seperti "Menambah data penerimaan". Terjemahan dirakit di lapisan render.

## Alasan
- Data audit stabil lintas bahasa; kalimat terjemahan yang tersimpan akan mengunci bahasa saat tulis.
- Tetap nyaman difilter/agregasi: `WHERE action = 'CREATE'`.
- Konteks entitas sudah dibawa `entity_type`, jadi key gabungan ala `receptions.insert` tidak perlu.
- Mengganti ENUM→VARCHAR demi menyimpan key akan memutus kontrak API/Postman/test tanpa manfaat nyata.

## Implementasi (commit 2e3b486)
- `app/Language/{id,en}/Reception.php`: `log.action_create` = 'Menambah data penerimaan' / 'Added reception data'; `action_update`; `action_delete`.
- `app/Views/receptions/form.php`: boot i18n `logActionCreate`/`logActionUpdate`/`logActionDelete`.
- `public/assets/js/pages/reception-form.js`: `actionLabel(action)` memetakan token (uppercase) → key; token tak dikenal dikembalikan apa adanya agar log lama tak hilang.
- Kontrak API tidak berubah: `logs[].action` tetap token, Postman & test backend tetap membacanya.
- Test: `tests/unit/AuditActionLabelTest.php` (id/en + fallback key tak dikenal), `ReceptionListRenderTest::testFormBootsActionLabelsForLogRendering`.

## Verifikasi nyata
Probe DOM via Chrome headless (aset lokal + stub API) pada HTML hasil render: CREATE→"Menambah data penerimaan", UPDATE→"Mengubah data penerimaan", DELETE→"Menghapus data penerimaan", ARCHIVE→"ARCHIVE" (fallback). Suite: 119 test, 381 assertion OK.

## Jebakan saat verifikasi
`$msg | Out-File -Encoding utf8` menambahkan BOM `EF BB BF` yang ikut masuk ke pesan commit → perbaiki dengan `git commit --amend -m` (hindari file perantara). Chrome headless di sandbox ini butuh `danger-full-access` + redirection via `cmd /c` ke file; stdout tidak tertangkap langsung dari PowerShell.
