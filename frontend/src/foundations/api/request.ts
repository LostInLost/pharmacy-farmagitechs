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

/**
 * Kegagalan yang sudah siap ditampilkan.
 *
 * `message` — ringkasan tunggal untuk pengguna.
 * `errors`  — rincian per aturan dari server (`errors[]`), kosong bila tidak ada.
 */
export type ApiFailure = {
  ok: false
  status: number
  message: string
  errors: string[]
}

export type ApiResult<T> = { ok: true; data: T } | ApiFailure

export const NETWORK_MESSAGE = "Tidak dapat menghubungi server."

/** Kegagalan jaringan (fetch melempar): status 0, tanpa rincian server. */
export function networkFailure(): ApiFailure {
  return { ok: false, status: 0, message: NETWORK_MESSAGE, errors: [] }
}

/** Respons sukses tapi bentuknya tidak dikenali skema. */
export function unrecognizedFailure(status: number): ApiFailure {
  return {
    ok: false,
    status,
    message: "Respons server tidak dikenali.",
    errors: [],
  }
}

/** Terjemahkan respons gagal jadi pesan + rincian yang bisa dirender. */
export function readFailure(
  status: number,
  body: unknown,
  fallback: string
): ApiFailure {
  const parsed = apiErrorSchema.safeParse(body)
  const message = parsed.success ? parsed.data.message : undefined

  return {
    ok: false,
    status,
    message: message ?? fallback,
    errors: parsed.success ? (parsed.data.errors ?? []) : [],
  }
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
