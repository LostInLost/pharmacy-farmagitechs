/**
 * Penanganan token CSRF CodeIgniter 4.
 *
 * Backend memakai `csrfProtection = 'cookie'` dengan `regenerate = true`:
 * token berotasi setiap permintaan mutasi, jadi nilai terbaru harus selalu
 * diambil dari cookie `csrf_cookie_name` (non-HttpOnly) atau dari header
 * respons `X-CSRF-TOKEN`, bukan dari session cookie.
 */

export const CSRF_HEADER = "X-CSRF-TOKEN"
export const CSRF_COOKIE = "csrf_cookie_name"

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null

  const match = document.cookie
    .split("; ")
    .find((row) => row.startsWith(`${name}=`))

  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

export function readCsrfToken(): string | null {
  return readCookie(CSRF_COOKIE)
}

export function readCsrfTokenFromResponse(response: Response): string | null {
  return response.headers.get(CSRF_HEADER)
}

/**
 * Ambil token awal: cookie lebih dulu, lalu fallback ke halaman backend
 * yang men-set cookie CSRF baru.
 */
export async function bootstrapCsrfToken(
  apiBaseUrl: string
): Promise<string | null> {
  const fromCookie = readCsrfToken()
  if (fromCookie) return fromCookie

  try {
    const response = await fetch(`${apiBaseUrl}/login`, {
      credentials: "include",
    })
    return readCsrfTokenFromResponse(response) ?? readCsrfToken()
  } catch {
    return null
  }
}
