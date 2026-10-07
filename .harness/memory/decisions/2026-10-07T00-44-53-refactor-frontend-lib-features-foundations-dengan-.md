---
title: "Refactor frontend lib→features+foundations dengan validasi Zod"
type: decision
summary: "Refactor frontend lib/→features+foundations selesai: Zod v4 (z.flattenError, looseObject, {error}), foundations tanpa impor features (ESLint no-restricted-imports), setUserSession tanpa parse, login kirim result.data, 4 commit terpisah; verifikasi typecheck/lint/build + 8 skenario guard SSR + alur CSRF basi live hijau"
tags: ["frontend", "astro", "refactor", "features", "foundations", "zod", "eslint", "auth", "csrf", "farmagitechs"]
source: "dsh"
confidence: high
scope: project
created_at: "2026-10-07T00:44:53Z"
updated_at: "2026-10-07T00:44:53Z"
---

# Refactor frontend: lib/ → features/ + foundations/ + Zod (2026-10-07)

## Struktur baru
- `src/foundations/` = infra generik, **tidak boleh** impor `features` (dijaga ESLint `no-restricted-imports`):
  - `api/config.ts` (API_BASE_URL, POST_LOGIN_PATH), `api/csrf.ts` (bootstrapCsrfToken default param), `api/client.ts` (apiFetch/parseJsonSafe/postJson — tipis, tanpa klasifikasi status), `api/index.ts`
  - `storage.ts` (readStorageRaw/writeStorageRaw/removeStorageKey — swallow error)
  - `ui/cn.ts`, `ui/use-mobile.ts`
- `src/features/auth/`: `schemas.ts` (Zod: loginFormSchema, authUserSchema, apiErrorSchema=looseObject, loginSuccessSchema, meResponseSchema), `api.ts` (login/logout + retry CSRF sekali), `server.ts` (getSessionUser untuk middleware SSR), `session.ts` (set/get/clearUserSession, ensureSession), `components/login-form.tsx`, `index.ts` (tanpa server.ts).
- `src/features/dashboard/`: `schemas.ts` (dashboardRowSchema, dashboardDataSchema), `components/dashboard-shell.tsx`.
- `src/middleware.ts` & `src/env.d.ts` tetap di tempatnya (konvensi Astro), impor via `./foundations/*` dan `./features/auth/*`.

## Keputusan penting
- Zod v4 idiom: `{ error: "..." }` bukan string; `z.looseObject` bukan `.catchall(z.unknown())`; **`parsed.error.flatten()` deprecated** → pakai `z.flattenError(parsed.error).fieldErrors`.
- `setUserSession` hanya `JSON.stringify` (tanpa parse — tidak boleh throw; validasi runtime di `getUserSession` + batas network).
- `login()` kirim `result.data` (sudah trim), kontrak 422 dipertahankan; form render pesan per-field dari skema (satu sumber kebenaran).
- Skema user lebih ketat (id/username/role wajib) → sesi lama hanya-name dihapus + self-heal `removeStorageKey`; kompatibilitas hanya lewat probe `ensureSession`.
- `ensureSession` fallback sintetis `id:0` masih dipersist + TODO(/api/me) — migrasi menyusul.
- `dashboard.json` divalidasi saat module load = pengaman runtime saja (warn bisa 2×: SSR+client).

## Verifikasi (semua hijau)
- typecheck 0 error, lint 0, build SSR OK, grep `@/lib`/`@/hooks` kosong.
- 8 skenario guard SSR live (anon /dashboard /rute-x / → 302 /login; anon /login favicon → 200; login /dashboard → 200; login /login / → 302 /dashboard).
- Alur CSRF basi live: bootstrap → POST token basi → 403 `error:"csrf"` + `X-CSRF-TOKEN` segar di header → retry → 200.
- Dev :4321 memuat modul baru (`/src/features/auth/schemas.ts` → 200).

## Catatan lingkungan
- `pnpm build` di harness butuh sekali `danger-full-access` (esbuild `spawn EPERM`), bukan masalah kode.
- Edit file yang baru di-`git mv` bisa gagal `ReplaceFileW EIO (Win32 1175)` transien — retry setelah jeda ~1 detik berhasil.
- Commit: 9bec545 (foundations) → f0d5eb8 (rename murni) → 1205593 (features+rewiring) → 5b56628 (hapus lib/hooks).