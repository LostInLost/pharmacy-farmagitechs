import { apiFetch, parseJsonSafe } from "@/foundations/api/client"
import {
  readStorageRaw,
  removeStorageKey,
  writeStorageRaw,
} from "@/foundations/storage"

import { authUserSchema, meResponseSchema, type AuthUser } from "./schemas"

/**
 * Info user untuk UI (nama/role). Bukan data otorisasi: otorisasi tetap
 * ditentukan session cookie HttpOnly di backend.
 */
const SESSION_KEY = "farmasi.user"

export type SessionUser = AuthUser

/**
 * Input sudah bertipe `AuthUser` (hasil login sukses / fallback), jadi
 * tidak ada validasi runtime di sini — helper storage tidak boleh throw.
 * Validasi runtime milik batas baca (`getUserSession`) dan batas network.
 */
export function setUserSession(user: SessionUser): void {
  writeStorageRaw(SESSION_KEY, JSON.stringify(user))
}

export function getUserSession(): SessionUser | null {
  const raw = readStorageRaw(SESSION_KEY)
  if (!raw) return null

  let parsed: unknown
  try {
    parsed = JSON.parse(raw)
  } catch {
    removeStorageKey(SESSION_KEY)
    return null
  }

  const result = authUserSchema.safeParse(parsed)

  if (!result.success) {
    // Self-heal: entri korup/invalid (mis. sesi format lama) dibersihkan
    // agar percobaan berikutnya tidak membaca sampah yang sama.
    removeStorageKey(SESSION_KEY)
    return null
  }

  return result.data
}

export function clearUserSession(): void {
  removeStorageKey(SESSION_KEY)
}

/**
 * Pastikan sesi backend masih valid. Dipakai `AppShell` sebagai jaring
 * pengaman klien ketika SSR tidak mendapat user (mis. backend sempat mati).
 *
 * - Ada info user lokal → pakai itu.
 * - Belum ada → tanya `GET /api/me`: 200 = sesi valid (nama + role +
 *   permissions asli), 401 = belum login, lainnya = menyerah (fail-closed).
 *
 * Sebelumnya probe memakai `GET /api/receipts` dan menulis user sintetis
 * `id: 0`; `/api/me` menghapus kebutuhan itu sekaligus membawa permissions
 * sehingga gating tombol tetap benar setelah muat ulang.
 */
export async function ensureSession(): Promise<SessionUser | null> {
  const local = getUserSession()
  if (local) return local

  try {
    const response = await apiFetch("/api/me")

    if (!response.ok) return null

    const parsed = meResponseSchema.safeParse(await parseJsonSafe(response))

    if (!parsed.success) return null

    setUserSession(parsed.data.user)
    return parsed.data.user
  } catch {
    return null
  }
}
