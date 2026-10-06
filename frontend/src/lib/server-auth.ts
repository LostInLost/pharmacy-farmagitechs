import { API_BASE_URL, type AuthUser } from "./auth"

type MeResponse = {
  user?: Partial<AuthUser>
}

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

    const body = (await response.json().catch(() => null)) as MeResponse | null
    const user = body?.user

    if (
      !user ||
      typeof user.id !== "number" ||
      typeof user.name !== "string"
    ) {
      return null
    }

    return {
      id: user.id,
      name: user.name,
      username: typeof user.username === "string" ? user.username : "",
      role: typeof user.role === "string" ? user.role : "",
    }
  } catch {
    return null
  }
}
