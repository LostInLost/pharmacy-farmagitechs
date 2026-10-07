import { z } from "zod"

/**
 * Bentuk error API CI4 yang dipakai lintas feature.
 *
 * `message`  — ringkasan untuk pengguna.
 * `error`    — penanda mesin (mis. `"csrf"`), bukan untuk ditampilkan.
 * `errors`   — daftar kesalahan per aturan validasi server (mis. dari
 *              `ReceptionValidator`). Hanya backend yang tahu aturan ini,
 *              jadi klien merendernya apa adanya.
 *
 * `looseObject` (bukan `object`) agar field tambahan dari backend tidak
 * membuat parse gagal.
 */
export const apiErrorSchema = z.looseObject({
  message: z.string().optional(),
  error: z.string().optional(),
  errors: z.array(z.string()).optional(),
})

export type ApiError = z.infer<typeof apiErrorSchema>
