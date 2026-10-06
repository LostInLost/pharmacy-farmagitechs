import { API_BASE_URL, type AuthUser } from "./auth"

/**
 * Info user untuk UI (nama/role). Bukan data otorisasi: otorisasi tetap
 * ditentukan session cookie HttpOnly di backend.
 */
const STORAGE_KEY = "farmasi.user"

export type SessionUser = AuthUser

export function setUserSession(user: SessionUser): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(user))
  } catch {
    // Private mode / storage penuh: abaikan, UI punya fallback.
  }
}

export function getUserSession(): SessionUser | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null

    const parsed = JSON.parse(raw) as Partial<SessionUser>
    if (typeof parsed?.name !== "string") return null

    return {
      id: typeof parsed.id === "number" ? parsed.id : 0,
      name: parsed.name,
      username: typeof parsed.username === "string" ? parsed.username : "",
      role: typeof parsed.role === "string" ? parsed.role : "",
    }
  } catch {
    return null
  }
}

export function clearUserSession(): void {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    // abaikan
  }
}

/**
 * Pastikan sesi backend masih valid. Dipakai halaman dashboard:
 * - Ada info user lokal → pakai itu.
 * - Belum ada → cek endpoint ber-auth; 200 = sesi valid (user anonim UI),
 *   401 = belum login.
 */
export async function ensureSession(): Promise<SessionUser | null> {
  const local = getUserSession()
  if (local) return local

  try {
    const response = await fetch(`${API_BASE_URL}/api/receipts`, {
      credentials: "include",
      headers: { Accept: "application/json" },
    })

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
