import {
  requestJson,
  networkFailure,
  readFailure,
  unrecognizedFailure,
  type ApiResult,
} from "@/foundations/api/request"

import { stockReportSchema, type StockReport } from "./schemas"

const FALLBACK = "Gagal memuat laporan stok."

/**
 * `GET /api/stocks` — tanpa `onDate`, backend memakai tanggal hari ini
 * (waktu server) dan mengembalikannya di `on_date`.
 */
export async function getStockReport(
  onDate?: string
): Promise<ApiResult<StockReport>> {
  const path =
    onDate && onDate !== ""
      ? `/api/stocks?on_date=${encodeURIComponent(onDate)}`
      : "/api/stocks"

  try {
    const result = await requestJson("GET", path)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK)
    }

    const parsed = stockReportSchema.safeParse(result.body)

    return parsed.success
      ? { ok: true, data: parsed.data }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}
