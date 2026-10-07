import {
  requestJson,
  networkFailure,
  readFailure,
  unrecognizedFailure,
  type ApiResult,
} from "@/foundations/api/request"

import {
  supplierDetailResponseSchema,
  supplierListResponseSchema,
  supplierWriteResponseSchema,
  type SupplierFormInput,
  type SupplierRow,
  type SupplierStatus,
} from "./schemas"

/** Pesan cadangan bila backend tidak menyertakan `message`. */
const FALLBACK = {
  list: "Gagal memuat data pemasok.",
  detail: "Gagal memuat data pemasok.",
  save: "Gagal menyimpan pemasok.",
}

/** Hasil baca daftar: barisnya plus hak tulis yang dihitung server. */
export type SupplierList = {
  rows: SupplierRow[]
  canWrite: boolean
}

export type SupplierDetail = {
  supplier: SupplierRow
  canWrite: boolean
}

/**
 * `GET /api/suppliers` — `q` dan `status` hanya dikirim bila bermakna,
 * sehingga URL tetap bersih saat filter dibiarkan pada nilai bawaan.
 */
export async function listSuppliers(
  q?: string,
  status?: SupplierStatus
): Promise<ApiResult<SupplierList>> {
  const params = new URLSearchParams()

  if (q && q.trim() !== "") params.set("q", q.trim())
  if (status && status !== "all") params.set("status", status)

  const query = params.toString()
  const path = query === "" ? "/api/suppliers" : `/api/suppliers?${query}`

  try {
    const result = await requestJson("GET", path)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.list)
    }

    const parsed = supplierListResponseSchema.safeParse(result.body)

    return parsed.success
      ? {
          ok: true,
          data: { rows: parsed.data.data, canWrite: parsed.data.can_write },
        }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}

/** `GET /api/suppliers/:id` */
export async function getSupplier(
  id: number
): Promise<ApiResult<SupplierDetail>> {
  try {
    const result = await requestJson("GET", `/api/suppliers/${id}`)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.detail)
    }

    const parsed = supplierDetailResponseSchema.safeParse(result.body)

    return parsed.success
      ? {
          ok: true,
          data: { supplier: parsed.data.data, canWrite: parsed.data.can_write },
        }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}

async function save(
  method: "POST" | "PUT",
  path: string,
  input: SupplierFormInput
): Promise<ApiResult<SupplierRow>> {
  try {
    const result = await requestJson(method, path, input)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.save)
    }

    const parsed = supplierWriteResponseSchema.safeParse(result.body)

    return parsed.success
      ? { ok: true, data: parsed.data.data }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}

/** `POST /api/suppliers` — hanya supervisor (ditegakkan server). */
export async function createSupplier(
  input: SupplierFormInput
): Promise<ApiResult<SupplierRow>> {
  return save("POST", "/api/suppliers", input)
}

/** `PUT /api/suppliers/:id` — hanya supervisor (ditegakkan server). */
export async function updateSupplier(
  id: number,
  input: SupplierFormInput
): Promise<ApiResult<SupplierRow>> {
  return save("PUT", `/api/suppliers/${id}`, input)
}
