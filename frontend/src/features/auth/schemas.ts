import { z } from "zod"

/** User yang dikembalikan backend (login & `GET /api/me`). */
export const authUserSchema = z.object({
  id: z.number(),
  name: z.string().min(1),
  username: z.string(),
  role: z.string(),
})

export type AuthUser = z.infer<typeof authUserSchema>

/**
 * Validasi input form login — satu sumber kebenaran pesan validasi.
 * `trim()` dijalankan sebelum `min(1)`, jadi username berisi spasi saja
 * tetap ditolak dan nilai yang lolos sudah ter-trim.
 */
export const loginFormSchema = z.object({
  username: z
    .string({ error: "Username wajib diisi." })
    .trim()
    .min(1, { error: "Username wajib diisi." }),
  password: z
    .string({ error: "Kata sandi wajib diisi." })
    .min(1, { error: "Kata sandi wajib diisi." }),
})

export type LoginInput = z.infer<typeof loginFormSchema>

/** Body error API: toleran terhadap field ekstra dari backend. */
export const apiErrorSchema = z.looseObject({
  message: z.string().optional(),
  error: z.string().optional(),
})

export type ApiError = z.infer<typeof apiErrorSchema>

/** Body sukses `POST /api/login`. */
export const loginSuccessSchema = z.object({ user: authUserSchema })

/** Respons `GET /api/me` (dipakai middleware SSR). */
export const meResponseSchema = z.looseObject({ user: authUserSchema })
