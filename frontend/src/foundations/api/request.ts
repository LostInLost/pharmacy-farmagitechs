import { apiFetch, parseJsonSafe, sendJson } from "./client"
import { bootstrapCsrfToken, readCsrfTokenFromResponse } from "./csrf"
import { apiErrorSchema } from "./schemas"

export type HttpMethod = "GET" | "POST" | "PUT"

/**
 * Hasil request yang sudah diparse. Body dikembalikan bersama status agar
 * pemanggil tidak perlu membaca `Response` dua kali (body hanya bisa dibaca
 * sekali) dan tetap bisa membedakan 403 CSRF dari 403 izin.
 */
export type JsonResult = {
  status: number
  ok: boolean
  body: unknown
}

function isCsrfFailure(status: number, body: unknown): boolean {
  if (status !== 403) return false

  const parsed = apiErrorSchema.safeParse(body)

  return parsed.success && parsed.data.error === "csrf"
}

/**
 * Request JSON ke backend CI4 dengan penanganan CSRF bawaan.
 *
 * `GET` adalah metode aman, jadi CsrfFilter melewatkannya tanpa token.
 * Mutasi butuh token; karena backend memakai `regenerate = true`, token
 * berotasi setiap permintaan — nilai terbaru selalu dibaca dari header
 * respons `X-CSRF-TOKEN`. Bila backend tetap menolak dengan 403
 * `error: "csrf"` (mis. token basi dari tab lain), ambil token segar dari
 * header respons tersebut lalu coba sekali lagi.
 *
 * Pemetaan status → pesan domain tetap milik masing-masing feature.
 */
export async function requestJson(
  method: HttpMethod,
  path: string,
  body?: unknown
): Promise<JsonResult> {
  if (method === "GET") {
    const response = await apiFetch(path)

    return {
      status: response.status,
      ok: response.ok,
      body: await parseJsonSafe(response),
    }
  }

  let token = await bootstrapCsrfToken()
  let response = await sendJson(method, path, body, token)
  let parsedBody = await parseJsonSafe(response)

  if (isCsrfFailure(response.status, parsedBody)) {
    token = readCsrfTokenFromResponse(response)
    response = await sendJson(method, path, body, token)
    parsedBody = await parseJsonSafe(response)
  }

  return { status: response.status, ok: response.ok, body: parsedBody }
}
