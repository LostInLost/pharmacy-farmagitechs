import { API_BASE_URL } from "./config"
import { CSRF_HEADER } from "./csrf"

/**
 * Klien HTTP tipis untuk backend CI4. Tanpa klasifikasi status/error:
 * pemetaan status ke pesan domain tetap milik masing-masing feature.
 */

export async function apiFetch(
  path: string,
  init: RequestInit = {}
): Promise<Response> {
  const headers = new Headers(init.headers)

  if (!headers.has("Accept")) headers.set("Accept", "application/json")

  return fetch(`${API_BASE_URL}${path}`, {
    credentials: "include",
    ...init,
    headers,
  })
}

export async function parseJsonSafe(response: Response): Promise<unknown> {
  try {
    return await response.json()
  } catch {
    return null
  }
}

/** Kirim body JSON dengan metode apa pun; `csrf` opsional. */
export async function sendJson(
  method: "POST" | "PUT",
  path: string,
  body: unknown,
  csrf: string | null
): Promise<Response> {
  const headers = new Headers({ "Content-Type": "application/json" })

  if (csrf) headers.set(CSRF_HEADER, csrf)

  return apiFetch(path, {
    method,
    headers,
    body: JSON.stringify(body),
  })
}

export async function postJson(
  path: string,
  body: unknown,
  csrf: string | null
): Promise<Response> {
  return sendJson("POST", path, body, csrf)
}
