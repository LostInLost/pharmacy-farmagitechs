import { z } from "zod"

/**
 * Skema master pemasok — cerminan `Api\SupplierController`.
 *
 * `can_write` dihitung `SupplierPolicy` hanya pada `index()` dan `show()`;
 * respons tulis (`POST`/`PUT`) tidak menyertakannya, jadi skema tulis
 * dipisah agar tidak menolak respons yang sah.
 */

/** Satu baris master pemasok. */
export const supplierRowSchema = z.object({
  id: z.number(),
  name: z.string(),
  is_active: z.boolean(),
})

export type SupplierRow = z.infer<typeof supplierRowSchema>

export const supplierListResponseSchema = z.looseObject({
  data: z.array(supplierRowSchema),
  can_write: z.boolean(),
})

export const supplierDetailResponseSchema = z.looseObject({
  data: supplierRowSchema,
  can_write: z.boolean(),
})

/** Respons sukses `POST`/`PUT /api/suppliers`. */
export const supplierWriteResponseSchema = z.looseObject({
  data: supplierRowSchema,
})

/** Filter daftar; nilainya dipetakan langsung ke query string API. */
export const supplierStatusSchema = z.enum(["all", "active", "inactive"])

export type SupplierStatus = z.infer<typeof supplierStatusSchema>

/**
 * Validasi form — satu sumber kebenaran pesan per-field.
 *
 * Batas panjang mengikuti kolom database (`name` 150) supaya kesalahan bentuk
 * tertangkap sebelum dikirim. Aturan yang butuh data server (nama sudah
 * dipakai pemasok lain) tetap milik backend dan dirender dari `errors[]`.
 */
export const supplierFormSchema = z.object({
  name: z
    .string({ error: "Nama pemasok wajib diisi." })
    .trim()
    .min(1, { error: "Nama pemasok wajib diisi." })
    .max(150, { error: "Nama pemasok maksimal 150 karakter." }),
  is_active: z.boolean(),
})

export type SupplierFormInput = z.infer<typeof supplierFormSchema>
