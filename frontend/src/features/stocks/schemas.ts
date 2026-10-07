import { z } from "zod"

/**
 * Skema laporan stok — cerminan `StockService::report()`.
 *
 * Angka selalu dihitung server dari ledger `stock_movements`; klien tidak
 * pernah menjumlahkan sendiri. `on_date` adalah tanggal efektif yang dipakai
 * server (hari ini bila permintaan tidak menyertakan tanggal).
 *
 * Batch dikirim sebagai satu daftar `batches` ber-flag `is_expired`, bukan dua
 * daftar terpisah; klasifikasi tersedia/kedaluwarsa disaring dari flag itu.
 */

export const stockBatchSchema = z.object({
  batch_no: z.string(),
  expires_on: z.string().nullable(),
  quantity: z.number(),
  is_expired: z.boolean(),
})

export type StockBatch = z.infer<typeof stockBatchSchema>

export const stockMedicineSchema = z.object({
  medicine_id: z.number(),
  code: z.string(),
  name: z.string(),
  unit: z.string(),
  physical_quantity: z.number(),
  available_quantity: z.number(),
  expired_quantity: z.number(),
  batches: z.array(stockBatchSchema),
})

export type StockMedicine = z.infer<typeof stockMedicineSchema>

export const stockReportSchema = z.object({
  on_date: z.string(),
  medicines: z.array(stockMedicineSchema),
})

export type StockReport = z.infer<typeof stockReportSchema>
