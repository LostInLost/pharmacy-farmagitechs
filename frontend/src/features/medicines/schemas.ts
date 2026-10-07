import { z } from "zod"

/**
 * Skema master obat — cerminan `Api\MedicineController`.
 *
 * `can_write` dihitung `MedicinePolicy` hanya pada `index()` dan `show()`;
 * respons tulis (`POST`/`PUT`) tidak menyertakannya, jadi skema tulis
 * dipisah agar tidak menolak respons yang sah.
 */

/** Satu baris master obat. */
export const medicineRowSchema = z.object({
  id: z.number(),
  code: z.string(),
  name: z.string(),
  unit: z.string(),
  is_active: z.boolean(),
})

export type MedicineRow = z.infer<typeof medicineRowSchema>

export const medicineListResponseSchema = z.looseObject({
  data: z.array(medicineRowSchema),
  can_write: z.boolean(),
})

export const medicineDetailResponseSchema = z.looseObject({
  data: medicineRowSchema,
  can_write: z.boolean(),
})

/** Respons sukses `POST`/`PUT /api/medicines`. */
export const medicineWriteResponseSchema = z.looseObject({
  data: medicineRowSchema,
})

/** Filter daftar; nilainya dipetakan langsung ke query string API. */
export const medicineStatusSchema = z.enum(["all", "active", "inactive"])

export type MedicineStatus = z.infer<typeof medicineStatusSchema>

/**
 * Validasi form — satu sumber kebenaran pesan per-field.
 *
 * Batas panjang mengikuti kolom database (`code` 50, `name` 200, `unit` 50)
 * supaya kesalahan bentuk tertangkap sebelum dikirim. Aturan yang butuh data
 * server (kode sudah dipakai obat lain) tetap milik backend dan dirender dari
 * `errors[]`.
 */
export const medicineFormSchema = z.object({
  code: z
    .string({ error: "Kode wajib diisi." })
    .trim()
    .min(1, { error: "Kode wajib diisi." })
    .max(50, { error: "Kode maksimal 50 karakter." }),
  name: z
    .string({ error: "Nama obat wajib diisi." })
    .trim()
    .min(1, { error: "Nama obat wajib diisi." })
    .max(200, { error: "Nama obat maksimal 200 karakter." }),
  unit: z
    .string({ error: "Satuan wajib diisi." })
    .trim()
    .min(1, { error: "Satuan wajib diisi." })
    .max(50, { error: "Satuan maksimal 50 karakter." }),
  is_active: z.boolean(),
})

export type MedicineFormInput = z.infer<typeof medicineFormSchema>
