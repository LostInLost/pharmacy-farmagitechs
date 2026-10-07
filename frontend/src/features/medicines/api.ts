import {
  requestJson,
  networkFailure,
  readFailure,
  unrecognizedFailure,
  type ApiResult,
} from "@/foundations/api/request"

import {
  medicineDetailResponseSchema,
  medicineListResponseSchema,
  medicineWriteResponseSchema,
  type MedicineFormInput,
  type MedicineRow,
  type MedicineStatus,
} from "./schemas"

/** Pesan cadangan bila backend tidak menyertakan `message`. */
const FALLBACK = {
  list: "Gagal memuat data obat.",
  detail: "Gagal memuat data obat.",
  save: "Gagal menyimpan obat.",
}

/** Hasil baca daftar: barisnya plus hak tulis yang dihitung server. */
export type MedicineList = {
  rows: MedicineRow[]
  canWrite: boolean
}

export type MedicineDetail = {
  medicine: MedicineRow
  canWrite: boolean
}

/**
 * `GET /api/medicines` — `q` dan `status` hanya dikirim bila bermakna,
 * sehingga URL tetap bersih saat filter dibiarkan pada nilai bawaan.
 */
export async function listMedicines(
  q?: string,
  status?: MedicineStatus
): Promise<ApiResult<MedicineList>> {
  const params = new URLSearchParams()

  if (q && q.trim() !== "") params.set("q", q.trim())
  if (status && status !== "all") params.set("status", status)

  const query = params.toString()
  const path = query === "" ? "/api/medicines" : `/api/medicines?${query}`

  try {
    const result = await requestJson("GET", path)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.list)
    }

    const parsed = medicineListResponseSchema.safeParse(result.body)

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

/** `GET /api/medicines/:id` */
export async function getMedicine(
  id: number
): Promise<ApiResult<MedicineDetail>> {
  try {
    const result = await requestJson("GET", `/api/medicines/${id}`)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.detail)
    }

    const parsed = medicineDetailResponseSchema.safeParse(result.body)

    return parsed.success
      ? {
          ok: true,
          data: { medicine: parsed.data.data, canWrite: parsed.data.can_write },
        }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}

async function save(
  method: "POST" | "PUT",
  path: string,
  input: MedicineFormInput
): Promise<ApiResult<MedicineRow>> {
  try {
    const result = await requestJson(method, path, input)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.save)
    }

    const parsed = medicineWriteResponseSchema.safeParse(result.body)

    return parsed.success
      ? { ok: true, data: parsed.data.data }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}

/** `POST /api/medicines` — hanya supervisor (ditegakkan server). */
export async function createMedicine(
  input: MedicineFormInput
): Promise<ApiResult<MedicineRow>> {
  return save("POST", "/api/medicines", input)
}

/** `PUT /api/medicines/:id` — hanya supervisor (ditegakkan server). */
export async function updateMedicine(
  id: number,
  input: MedicineFormInput
): Promise<ApiResult<MedicineRow>> {
  return save("PUT", `/api/medicines/${id}`, input)
}
