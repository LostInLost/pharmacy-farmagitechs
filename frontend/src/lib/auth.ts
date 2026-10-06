import {
  CSRF_HEADER,
  bootstrapCsrfToken,
  readCsrfToken,
  readCsrfTokenFromResponse,
} from "./csrf"

export const API_BASE_URL =
  import.meta.env.PUBLIC_API_BASE_URL ?? "http://localhost:8080"

export const POST_LOGIN_PATH =
  import.meta.env.PUBLIC_POST_LOGIN_PATH ?? "/dashboard"

export type AuthUser = {
  id: number
  name: string
  username: string
  role: string
}

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

type ApiErrorBody = {
  message?: string
  error?: string
}

function isCsrfError(status: number, body: ApiErrorBody): boolean {
  return status === 403 && body.error === "csrf"
}

async function parseBody(response: Response): Promise<ApiErrorBody> {
  try {
    return (await response.json()) as ApiErrorBody
  } catch {
    return {}
  }
}

function classify(status: number, body: ApiErrorBody): LoginErrorKind {
  if (status === 422) return "validation"
  if (status === 401) return "invalid-credentials"
  if (status === 403 && body.error === "csrf") return "csrf"
  if (status === 403) return "already-authenticated"
  if (status >= 500) return "server"
  return "server"
}

async function postLogin(
  username: string,
  password: string,
  token: string | null
): Promise<Response> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  }

  if (token) headers[CSRF_HEADER] = token

  return fetch(`${API_BASE_URL}/api/login`, {
    method: "POST",
    credentials: "include",
    headers,
    body: JSON.stringify({ username, password }),
  })
}

/**
 * Login ke backend CI4. Token CSRF berotasi setiap mutasi, jadi bila
 * backend menolak dengan 403 `csrf`, ambil token segar dari header respons
 * lalu coba sekali lagi.
 */
export async function login(
  username: string,
  password: string
): Promise<LoginResult> {
  let token = await bootstrapCsrfToken(API_BASE_URL)

  try {
    let response = await postLogin(username, password, token)
    let body = await parseBody(response)

    if (isCsrfError(response.status, body)) {
      token = readCsrfTokenFromResponse(response) ?? readCsrfToken()
      response = await postLogin(username, password, token)
      body = await parseBody(response)
    }

    if (response.ok) {
      const payload = body as ApiErrorBody & { user?: AuthUser }
      const user = payload.user ?? (await response.json().catch(() => null))

      if (user) return { ok: true, user }

      return {
        ok: false,
        status: response.status,
        kind: "server",
        message: "Respons login tidak dikenali.",
      }
    }

    return {
      ok: false,
      status: response.status,
      kind: classify(response.status, body),
      message: body.message ?? "Login gagal.",
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
  const token = await bootstrapCsrfToken(API_BASE_URL)

  const send = (csrf: string | null) =>
    fetch(`${API_BASE_URL}/api/logout`, {
      method: "POST",
      credentials: "include",
      headers: csrf ? { [CSRF_HEADER]: csrf, Accept: "application/json" } : { Accept: "application/json" },
    })

  try {
    let response = await send(token)

    if (response.status === 403) {
      const fresh = readCsrfTokenFromResponse(response) ?? readCsrfToken()
      response = await send(fresh)
    }
  } catch {
    // Logout lokal tetap dilanjutkan walau server tidak terjangkau.
  }
}
