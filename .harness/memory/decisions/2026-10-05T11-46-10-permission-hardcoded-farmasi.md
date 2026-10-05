---
title: "Permission hardcoded farmasi"
type: decision
summary: "Permission farmasi di-hardcode per role, Shield ditolak, auth session CI4 manual"
tags: ["farmasi", "auth", "permissions", "codeigniter"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-05T11:46:10Z"
updated_at: "2026-10-05T11:46:10Z"
---

Permission di-hardcode sebagai pemetaan role di kode, tanpa tabel permissions/roles. Supervisor = receipt.create/view/update-own/update-any; penerimaan = create/view/update-own. canUpdate(): supervisor lolos langsung, penerimaan hanya jika created_by = current user. Ditolak 403 tanpa mengubah penerimaan/stok/log, dicek dalam transaksi sebelum write. Shield ditolak karena 8 tabel auth_*, overkill untuk 2 akun statis. Auth session CI4 manual + password_hash/verify, identitas dari sesi server.