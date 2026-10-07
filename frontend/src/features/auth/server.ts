import { API_BASE_URL } from "@/foundations/api/config"
import { parseJsonSafe } from "@/foundations/api/client"

import { meResponseSchema, type AuthUser } from "./schemas"

/**
 * Cek sesi backend dari server (dipakai middleware SSR).
 *
 * Cookie browser (`ci_session`, HttpOnly) diteruskan apa adanya ke
 * `GET /api/me`; backend yang menilai validitas sesi. GET adalah metode
 * aman sehingga lolos CsrfFilter tanpa token.
 *
 * Mengembalikan user bila sesi valid, `null` bila anonim, backend mati,
 * atau respons tak dikenali (fail-closed: rute protektif mengalihkan
 * ke /login).
 */
export async function getSessionUser(
  cookieHeader: string | null
): Promise<AuthUser | null> {
  if (!cookieHeader) return null

  try {
    const response = await fetch(`${API_BASE_URL}/api/me`, {
      headers: { Accept: "application/json", Cookie: cookieHeader },
      signal: AbortSignal.timeout(3000),
    })

    if (!response.ok) return null

    const body = await parseJsonSafe(response)
    const parsed = meResponseSchema.safeParse(body)

    return parsed.success ? parsed.data.user : null
  } catch {
    return null
  }
}
