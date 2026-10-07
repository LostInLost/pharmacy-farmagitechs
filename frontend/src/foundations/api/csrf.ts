import { API_BASE_URL } from "./config"

/**
 * Penanganan token CSRF CodeIgniter 4.
 *
 * Backend memakai `csrfProtection = 'cookie'` dengan `regenerate = true`:
 * token berotasi setiap permintaan mutasi, jadi nilai terbaru harus selalu
 * diambil dari header respons `X-CSRF-TOKEN` (diekspos lewat CORS).
 *
 * Cookie `csrf_cookie_name` bersifat HttpOnly (warisan `Config\Cookie`
 * `$httponly = true`), sehingga `document.cookie` tidak bisa membacanya —
 * token awal diambil dari endpoint bootstrap `GET /api/csrf`.
 */

export const CSRF_HEADER = "X-CSRF-TOKEN"

export function readCsrfTokenFromResponse(response: Response): string | null {
  return response.headers.get(CSRF_HEADER)
}

/**
 * Ambil token awal dari `GET {API}/api/csrf`. Endpoint ini publik (di luar
 * filter auth) dan mengembalikan token pada header `X-CSRF-TOKEN` serta
 * body `{ token }`. Responsnya juga men-set cookie CSRF baru bila belum ada.
 */
export async function bootstrapCsrfToken(
  apiBaseUrl: string = API_BASE_URL
): Promise<string | null> {
  try {
    const response = await fetch(`${apiBaseUrl}/api/csrf`, {
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    })

    const fromHeader = readCsrfTokenFromResponse(response)
    if (fromHeader) return fromHeader

    const body = (await response.json().catch(() => null)) as {
      token?: string
    } | null

    return body?.token ?? null
  } catch {
    return null
  }
}
