import { defineMiddleware, sequence } from "astro:middleware"

import { POST_LOGIN_PATH } from "./lib/auth"
import { getSessionUser } from "./lib/server-auth"

/**
 * Auth guard berantai ala docs Astro
 * (https://docs.astro.build/en/guides/middleware):
 *
 * 1. `session`       — mengisi `locals.user` dari backend (`GET /api/me`).
 * 2. `guest`         — halaman tamu (`/login`) menolak pengguna yang sudah masuk.
 * 3. `authenticated` — semua rute lain wajib login (deny-by-default), sehingga
 *                      halaman baru otomatis terlindungi tanpa daftar manual.
 */

const LOGIN_PATH = "/login"

// Rute yang boleh diakses tanpa login (halaman tamu + aset).
// `/` sengaja publik: halaman itu hanya mengalihkan (lihat index.astro),
// sehingga arah redirect ditentukan di satu tempat.
const PUBLIC_PATHS = new Set([
  "/",
  LOGIN_PATH,
  "/favicon.svg",
  "/favicon.ico",
])

// Awalan aset statis/hasil build: publik dan tanpa cek sesi.
// `/@`, `/node_modules/`, dan `/_image` adalah internal Vite/Astro yang
// tidak pernah dipetakan ke halaman, jadi aman dilewati.
const ASSET_PREFIXES = ["/_astro/", "/_image", "/@", "/node_modules/"]

// Halaman tamu: pengguna yang sudah masuk dialihkan ke dashboard.
const GUEST_ONLY_PATHS = new Set([LOGIN_PATH])

function normalizePath(pathname: string): string {
  return pathname.length > 1 ? pathname.replace(/\/+$/, "") : pathname
}

function isAssetPath(path: string): boolean {
  return ASSET_PREFIXES.some((prefix) => path.startsWith(prefix))
}

function isPublicPath(path: string): boolean {
  return isAssetPath(path) || PUBLIC_PATHS.has(path)
}

/** Mengisi `locals.user` dari `GET /api/me` (cookie browser diteruskan). */
const session = defineMiddleware(async (context, next) => {
  const path = normalizePath(context.url.pathname)

  if (isAssetPath(path)) {
    context.locals.user = null
    return next()
  }

  context.locals.user = await getSessionUser(
    context.request.headers.get("cookie")
  )

  return next()
})

/** Kebalikan guard login: yang sudah masuk dilarang membuka halaman tamu. */
const guest = defineMiddleware(async (context, next) => {
  const path = normalizePath(context.url.pathname)

  if (GUEST_ONLY_PATHS.has(path) && context.locals.user) {
    return context.redirect(POST_LOGIN_PATH)
  }

  return next()
})

/** Deny-by-default: apa pun di luar daftar publik wajib login. */
const authenticated = defineMiddleware(async (context, next) => {
  const path = normalizePath(context.url.pathname)

  if (!isPublicPath(path) && !context.locals.user) {
    return context.redirect(LOGIN_PATH)
  }

  return next()
})

export const onRequest = sequence(session, guest, authenticated)
