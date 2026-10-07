import { apiFetch } from "@/foundations/api/client"
import {
  readStorageRaw,
  removeStorageKey,
  writeStorageRaw,
} from "@/foundations/storage"

import { authUserSchema, type AuthUser } from "./schemas"

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
 * Pastikan sesi backend masih valid. Dipakai halaman dashboard:
 * - Ada info user lokal → pakai itu.
 * - Belum ada → cek endpoint ber-auth; 200 = sesi valid (user anonim UI),
 *   401 = belum login.
 *
 * TODO(/api/me): backend sudah menyediakan `GET /api/me` — migrasikan probe
 * ini + fallback sintetis `id: 0` ke sana agar nama asli tampil dan sesi
 * palsu tidak lagi dipersist ke sessionStorage.
 */
export async function ensureSession(): Promise<SessionUser | null> {
  const local = getUserSession()
  if (local) return local

  try {
    const response = await apiFetch("/api/receipts")

    if (response.status === 401) return null

    if (response.ok) {
      const fallback: SessionUser = {
        id: 0,
        name: "Pengguna",
        username: "",
        role: "",
      }
      setUserSession(fallback)
      return fallback
    }

    return null
  } catch {
    return null
  }
}
