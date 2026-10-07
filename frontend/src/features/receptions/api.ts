import {
  requestJson,
  networkFailure,
  readFailure,
  unrecognizedFailure,
  type ApiResult,
} from "@/foundations/api/request"

import {
  medicinesResponseSchema,
  receptionDetailResponseSchema,
  receptionListResponseSchema,
  receptionWriteResponseSchema,
  suppliersResponseSchema,
  type Medicine,
  type ReceptionDetail,
  type ReceptionFormInput,
  type ReceptionRow,
  type Supplier,
} from "./schemas"

/** Pesan cadangan bila backend tidak menyertakan `message`. */
const FALLBACK = {
  list: "Gagal memuat daftar penerimaan.",
  detail: "Gagal memuat detail penerimaan.",
  references: "Gagal memuat data referensi.",
  save: "Gagal menyimpan penerimaan.",
}

function parseList(body: unknown, status: number): ApiResult<ReceptionRow[]> {
  const parsed = receptionListResponseSchema.safeParse(body)

  return parsed.success
    ? { ok: true, data: parsed.data.data }
    : unrecognizedFailure(status)
}

function parseDetail(
  body: unknown,
  status: number
): ApiResult<ReceptionDetail> {
  const parsed = receptionDetailResponseSchema.safeParse(body)

  return parsed.success
    ? { ok: true, data: parsed.data.data }
    : unrecognizedFailure(status)
}

/** `GET /api/receipts` */
export async function listReceptions(): Promise<ApiResult<ReceptionRow[]>> {
  try {
    const result = await requestJson("GET", "/api/receipts")

    return result.ok
      ? parseList(result.body, result.status)
      : readFailure(result.status, result.body, FALLBACK.list)
  } catch {
    return networkFailure()
  }
}

/** `GET /api/receipts/:id` */
export async function getReception(
  id: number
): Promise<ApiResult<ReceptionDetail>> {
  try {
    const result = await requestJson("GET", `/api/receipts/${id}`)

    return result.ok
      ? parseDetail(result.body, result.status)
      : readFailure(result.status, result.body, FALLBACK.detail)
  } catch {
    return networkFailure()
  }
}

async function save(
  method: "POST" | "PUT",
  path: string,
  input: ReceptionFormInput
): Promise<ApiResult<ReceptionDetail>> {
  try {
    const result = await requestJson(method, path, input)

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.save)
    }

    const parsed = receptionWriteResponseSchema.safeParse(result.body)

    return parsed.success
      ? { ok: true, data: parsed.data.data }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}

/** `POST /api/receipts` */
export async function createReception(
  input: ReceptionFormInput
): Promise<ApiResult<ReceptionDetail>> {
  return save("POST", "/api/receipts", input)
}

/** `PUT /api/receipts/:id` */
export async function updateReception(
  id: number,
  input: ReceptionFormInput
): Promise<ApiResult<ReceptionDetail>> {
  return save("PUT", `/api/receipts/${id}`, input)
}

/** `GET /api/references/suppliers` */
export async function listSuppliers(): Promise<ApiResult<Supplier[]>> {
  try {
    const result = await requestJson("GET", "/api/references/suppliers")

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.references)
    }

    const parsed = suppliersResponseSchema.safeParse(result.body)

    return parsed.success
      ? { ok: true, data: parsed.data.data }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}

/** `GET /api/references/medicines` */
export async function listMedicines(): Promise<ApiResult<Medicine[]>> {
  try {
    const result = await requestJson("GET", "/api/references/medicines")

    if (!result.ok) {
      return readFailure(result.status, result.body, FALLBACK.references)
    }

    const parsed = medicinesResponseSchema.safeParse(result.body)

    return parsed.success
      ? { ok: true, data: parsed.data.data }
      : unrecognizedFailure(result.status)
  } catch {
    return networkFailure()
  }
}
