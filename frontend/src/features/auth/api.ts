import { apiFetch, parseJsonSafe, postJson } from "@/foundations/api/client"
import {
  CSRF_HEADER,
  bootstrapCsrfToken,
  readCsrfTokenFromResponse,
} from "@/foundations/api/csrf"

import {
  apiErrorSchema,
  loginFormSchema,
  loginSuccessSchema,
  type ApiError,
  type AuthUser,
} from "./schemas"

export type LoginResult =
  | { ok: true; user: AuthUser }
  | { ok: false; status: number; message: string; kind: LoginErrorKind }

export type LoginErrorKind =
  | "validation"
  | "invalid-credentials"
  | "already-authenticated"
  | "csrf"
  | "network"
  | "server"

function readApiError(body: unknown): ApiError {
  const parsed = apiErrorSchema.safeParse(body)
  return parsed.success ? parsed.data : {}
}

function isCsrfError(status: number, error: ApiError): boolean {
  return status === 403 && error.error === "csrf"
}

function classify(status: number, error: ApiError): LoginErrorKind {
  if (status === 422) return "validation"
  if (status === 401) return "invalid-credentials"
  if (status === 403 && error.error === "csrf") return "csrf"
  if (status === 403) return "already-authenticated"
  return "server"
}

/**
 * Login ke backend CI4. Input divalidasi ulang di sini (kontrak 422) dan
 * yang dikirim ke server adalah hasil parse (sudah ter-trim). Token CSRF
 * berotasi setiap mutasi, jadi bila backend menolak dengan 403 `csrf`,
 * ambil token segar dari header respons lalu coba sekali lagi.
 */
export async function login(
  username: string,
  password: string
): Promise<LoginResult> {
  const parsed = loginFormSchema.safeParse({ username, password })

  if (!parsed.success) {
    return {
      ok: false,
      status: 422,
      kind: "validation",
      message:
        parsed.error.issues[0]?.message ?? "Username dan kata sandi wajib diisi.",
    }
  }

  const credentials = parsed.data
  let token = await bootstrapCsrfToken()

  try {
    let response = await postJson("/api/login", credentials, token)
    let body = await parseJsonSafe(response)

    if (isCsrfError(response.status, readApiError(body))) {
      // Token sudah berotasi; header respons membawa nilai terbaru.
      token = readCsrfTokenFromResponse(response)
      response = await postJson("/api/login", credentials, token)
      body = await parseJsonSafe(response)
    }

    if (response.ok) {
      const success = loginSuccessSchema.safeParse(body)

      if (success.success) return { ok: true, user: success.data.user }

      return {
        ok: false,
        status: response.status,
        kind: "server",
        message: "Respons login tidak dikenali.",
      }
    }

    const error = readApiError(body)

    return {
      ok: false,
      status: response.status,
      kind: classify(response.status, error),
      message: error.message ?? "Login gagal.",
    }
  } catch {
    return {
      ok: false,
      status: 0,
      kind: "network",
      message: "Tidak dapat menghubungi server.",
    }
  }
}

export async function logout(): Promise<void> {
  const token = await bootstrapCsrfToken()

  const send = (csrf: string | null) =>
    apiFetch("/api/logout", {
      method: "POST",
      headers: csrf ? { [CSRF_HEADER]: csrf } : undefined,
    })

  try {
    const response = await send(token)

    if (response.status === 403) {
      await send(readCsrfTokenFromResponse(response))
    }
  } catch {
    // Logout lokal tetap dilanjutkan walau server tidak terjangkau.
  }
}
