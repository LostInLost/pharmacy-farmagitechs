import { z } from "zod"

/**
 * Skema domain Receptions — cerminan respons `Api\ReceptionController`.
 *
 * Backend mengembalikan nama relasi sebagai `null` bila barisnya tidak ada
 * (join `left`), jadi kolom `*_name` sengaja nullable.
 */

/**
 * Kolom header penerimaan yang selalu ada di semua respons.
 *
 * `can_update` TIDAK termasuk di sini: nilainya dihitung `ReceptionPolicy`
 * hanya pada `index()` dan `show()`, sedangkan `create()`/`update()`
 * mengembalikan `ReceptionService::detail()` yang belum tersentuh policy.
 */
const receptionHeaderSchema = z.object({
  id: z.number(),
  reference_no: z.string(),
  supplier_id: z.number(),
  supplier_name: z.string().nullable(),
  received_at: z.string(),
  created_by: z.number(),
  created_by_name: z.string().nullable(),
  created_at: z.string(),
  updated_by: z.number().nullable(),
  updated_by_name: z.string().nullable(),
  updated_at: z.string().nullable(),
})

/** Baris `GET /api/receipts` — policy menambahkan `can_update`. */
export const receptionRowSchema = receptionHeaderSchema.extend({
  can_update: z.boolean(),
})

export type ReceptionRow = z.infer<typeof receptionRowSchema>

export const receptionListResponseSchema = z.looseObject({
  data: z.array(receptionRowSchema),
})

/** Item di dalam detail penerimaan (`reception_items`). */
export const receptionItemSchema = z.object({
  id: z.number(),
  medicine_id: z.number(),
  medicine_code: z.string().nullable(),
  medicine_name: z.string().nullable(),
  unit: z.string().nullable(),
  batch_no: z.string(),
  expires_on: z.string(),
  quantity: z.number(),
})

export type ReceptionItem = z.infer<typeof receptionItemSchema>

/**
 * Jejak audit. `action` berisi kunci i18n itu sendiri
 * (mis. `Audit.receptions.action.create`) — labelnya dirakit saat render
 * lewat `auditActionLabel()`, jadi nilai dari server dipakai apa adanya.
 */
export const auditLogSchema = z.object({
  id: z.number(),
  actor_id: z.number(),
  actor_name: z.string().nullable(),
  action: z.string(),
  data_before: z.unknown().nullable(),
  data_after: z.unknown().nullable(),
  created_at: z.string(),
})

export type AuditLog = z.infer<typeof auditLogSchema>

/**
 * Detail `GET /api/receipts/:id` — header + items + logs, plus `can_update`
 * yang ditambahkan `ReceptionController::show()`.
 */
export const receptionDetailSchema = receptionRowSchema.extend({
  items: z.array(receptionItemSchema),
  logs: z.array(auditLogSchema),
})

export type ReceptionDetail = z.infer<typeof receptionDetailSchema>

export const receptionDetailResponseSchema = z.looseObject({
  data: receptionDetailSchema,
})

/**
 * Detail dari `create()`/`update()` — tanpa `can_update` karena policy hanya
 * berjalan di `index()`/`show()`. Dipakai juga sebagai `ReceptionDetail`
 * dengan `can_update` opsional agar pemanggil tidak perlu dua tipe.
 */
export const receptionWriteDetailSchema = receptionHeaderSchema.extend({
  items: z.array(receptionItemSchema),
  logs: z.array(auditLogSchema),
})

export type ReceptionWriteDetail = z.infer<typeof receptionWriteDetailSchema>

/** `GET /api/references/suppliers`. */
export const supplierSchema = z.object({
  id: z.number(),
  name: z.string(),
})

export type Supplier = z.infer<typeof supplierSchema>

export const suppliersResponseSchema = z.looseObject({
  data: z.array(supplierSchema),
})

/** `GET /api/references/medicines`. */
export const medicineSchema = z.object({
  id: z.number(),
  name: z.string(),
  unit: z.string(),
})

export type Medicine = z.infer<typeof medicineSchema>

export const medicinesResponseSchema = z.looseObject({
  data: z.array(medicineSchema),
})

/**
 * `GET /api/references/batches` — batch unik per obat dari ledger, saran
 * isian kolom Batch No. `expires_on` null bila ledger tak mencatat tanggal.
 */
export const batchReferenceSchema = z.object({
  medicine_id: z.number(),
  batch_no: z.string(),
  expires_on: z.string().nullable(),
})

export type BatchReference = z.infer<typeof batchReferenceSchema>

export const batchReferencesResponseSchema = z.looseObject({
  data: z.array(batchReferenceSchema),
})

/** Respons sukses `POST`/`PUT /api/receipts`. */
export const receptionWriteResponseSchema = z.looseObject({
  data: receptionWriteDetailSchema,
})

/**
 * Validasi form — satu sumber kebenaran pesan per-field.
 *
 * Hanya memeriksa bentuk dan kewajiban; aturan yang butuh data server
 * (reference_no terpakai, pemasok/obat aktif, konflik batch, urutan tanggal)
 * tetap milik backend dan dirender dari `errors[]`.
 *
 * `quantity` memakai `z.number()` karena `<input type="number">` sudah
 * dikonversi sebelum parse.
 */
export const receptionItemFormSchema = z.object({
  medicine_id: z
    .number({ error: "Pilih obat." })
    .int({ error: "Pilih obat." })
    .positive({ error: "Pilih obat." }),
  batch_no: z
    .string({ error: "Batch wajib diisi." })
    .trim()
    .min(1, { error: "Batch wajib diisi." }),
  expires_on: z
    .string({ error: "Kedaluwarsa wajib diisi." })
    .regex(/^\d{4}-\d{2}-\d{2}$/, { error: "Format tanggal harus YYYY-MM-DD." }),
  quantity: z
    .number({ error: "Jumlah wajib diisi." })
    .int({ error: "Jumlah harus bilangan bulat." })
    .min(1, { error: "Jumlah minimal 1." }),
})

export type ReceptionItemInput = z.infer<typeof receptionItemFormSchema>

export const receptionFormSchema = z.object({
  reference_no: z
    .string({ error: "Reference wajib diisi." })
    .trim()
    .min(1, { error: "Reference wajib diisi." }),
  supplier_id: z
    .number({ error: "Pilih pemasok." })
    .int({ error: "Pilih pemasok." })
    .positive({ error: "Pilih pemasok." }),
  received_at: z
    .string({ error: "Waktu diterima wajib diisi." })
    .min(1, { error: "Waktu diterima wajib diisi." }),
  items: z
    .array(receptionItemFormSchema, { error: "Tambahkan minimal satu item." })
    .min(1, { error: "Tambahkan minimal satu item." }),
})

export type ReceptionFormInput = z.infer<typeof receptionFormSchema>
