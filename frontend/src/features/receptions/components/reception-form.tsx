import * as React from "react"
import { z } from "zod"
import { CirclePlusIcon, Trash2Icon } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Feedback } from "@/components/feedback"
import {
  Field,
  FieldError,
  FieldLabel,
} from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { toDatetimeLocal } from "@/foundations/format"
import {
  createReception,
  getReception,
  listMedicines,
  listSuppliers,
  updateReception,
} from "@/features/receptions/api"
import {
  receptionFormSchema,
  type AuditLog,
  type Medicine,
  type ReceptionFormInput,
  type Supplier,
} from "@/features/receptions/schemas"
import { AuditLogTable } from "./audit-log-table"

type Props = {
  /** `null` = mode buat; angka = mode ubah. */
  receptionId: number | null
}

type LoadState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "forbidden"; message: string }
  | { status: "ready"; logs: AuditLog[] }

/** Satu baris item di form; `key` hanya untuk React, bukan bagian payload. */
type ItemRow = ReceptionFormInput["items"][number] & { key: string }

type FieldErrors = Record<string, string | undefined>

let nextKey = 0

function blankRow(): ItemRow {
  nextKey += 1

  return {
    key: `row-${nextKey}`,
    medicine_id: 0,
    batch_no: "",
    expires_on: "",
    quantity: 1,
  }
}

export function ReceptionForm({ receptionId }: Props) {
  const [load, setLoad] = React.useState<LoadState>({ status: "loading" })
  const [suppliers, setSuppliers] = React.useState<Supplier[]>([])
  const [medicines, setMedicines] = React.useState<Medicine[]>([])
  const [referenceNo, setReferenceNo] = React.useState("")
  const [supplierId, setSupplierId] = React.useState("")
  const [receivedAt, setReceivedAt] = React.useState("")
  const [rows, setRows] = React.useState<ItemRow[]>([blankRow()])
  const [fieldErrors, setFieldErrors] = React.useState<FieldErrors>({})
  const [formErrors, setFormErrors] = React.useState<string[]>([])
  const [saving, setSaving] = React.useState(false)
  const [saved, setSaved] = React.useState(false)

  React.useEffect(() => {
    let cancelled = false

    async function loadData() {
      const [supplierResult, medicineResult] = await Promise.all([
        listSuppliers(),
        listMedicines(),
      ])

      if (cancelled) return

      if (!supplierResult.ok) {
        setLoad({ status: "error", message: supplierResult.message })
        return
      }

      if (!medicineResult.ok) {
        setLoad({ status: "error", message: medicineResult.message })
        return
      }

      setSuppliers(supplierResult.data)
      setMedicines(medicineResult.data)

      if (receptionId === null) {
        setLoad({ status: "ready", logs: [] })
        return
      }

      const detail = await getReception(receptionId)

      if (cancelled) return

      if (!detail.ok) {
        setLoad({ status: "error", message: detail.message })
        return
      }

      // Policy backend menentukan hak ubah; form hanya mengikuti.
      if (!detail.data.can_update) {
        setLoad({
          status: "forbidden",
          message: "Anda tidak berhak mengubah penerimaan ini.",
        })
        return
      }

      setReferenceNo(detail.data.reference_no)
      setSupplierId(String(detail.data.supplier_id))
      setReceivedAt(toDatetimeLocal(detail.data.received_at))
      setRows(
        detail.data.items.map((item) => ({
          key: `row-${item.id}`,
          medicine_id: item.medicine_id,
          batch_no: item.batch_no,
          expires_on: item.expires_on,
          quantity: item.quantity,
        }))
      )
      setLoad({ status: "ready", logs: detail.data.logs })
    }

    loadData()

    return () => {
      cancelled = true
    }
  }, [receptionId])

  function updateRow(key: string, patch: Partial<ItemRow>) {
    setRows((current) =>
      current.map((row) => (row.key === key ? { ...row, ...patch } : row))
    )
  }

  function removeRow(key: string) {
    setRows((current) => current.filter((row) => row.key !== key))
  }

  async function onSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (saving) return

    // Satu sumber kebenaran pesan validasi: skema. Aturan yang butuh data
    // server tetap dirender dari `errors[]` backend.
    const parsed = receptionFormSchema.safeParse({
      reference_no: referenceNo,
      supplier_id: Number(supplierId),
      received_at: receivedAt,
      // `key` hanya identitas React, bukan bagian payload.
      items: rows.map((row) => ({
        medicine_id: row.medicine_id,
        batch_no: row.batch_no,
        expires_on: row.expires_on,
        quantity: row.quantity,
      })),
    })

    if (!parsed.success) {
      const flattened = z.flattenError(parsed.error)

      setFieldErrors({
        reference_no: flattened.fieldErrors.reference_no?.[0],
        supplier_id: flattened.fieldErrors.supplier_id?.[0],
        received_at: flattened.fieldErrors.received_at?.[0],
        items: flattened.fieldErrors.items?.[0],
      })
      setFormErrors([])
      return
    }

    setFieldErrors({})
    setFormErrors([])
    setSaving(true)

    const result =
      receptionId === null
        ? await createReception(parsed.data)
        : await updateReception(receptionId, parsed.data)

    if (!result.ok) {
      // 422 → rincian per aturan server; lainnya → pesan ringkas.
      setFormErrors(
        result.errors.length > 0 ? result.errors : [result.message]
      )
      setSaving(false)
      return
    }

    setSaved(true)
    window.setTimeout(() => {
      window.location.assign("/receptions")
    }, 800)
  }

  if (load.status === "loading") {
    return (
      <div className="flex flex-col gap-4 px-4 lg:px-6">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (load.status === "error" || load.status === "forbidden") {
    return (
      <div className="flex flex-col gap-4 px-4 lg:px-6">
        <Feedback variant="error" messages={[load.message]} />
        <div>
          <Button asChild variant="outline">
            <a href="/receptions">Kembali ke daftar</a>
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4 px-4 lg:px-6">
      <div>
        <h2 className="font-heading text-lg font-medium">
          {receptionId === null ? "Penerimaan Baru" : "Detail & Ubah Penerimaan"}
        </h2>
        <p className="text-sm text-muted-foreground">
          Isi header, lalu tambahkan item beserta batch dan kedaluwarsanya.
        </p>
      </div>

      {saved && (
        <Feedback
          variant="success"
          messages={["Tersimpan. Mengalihkan ke daftar penerimaan..."]}
        />
      )}

      {formErrors.length > 0 && (
        <Feedback variant="error" messages={formErrors} />
      )}

      <Card>
        <CardContent>
          <form onSubmit={onSubmit} noValidate className="flex flex-col gap-6">
            <div className="grid gap-4 md:grid-cols-3">
              <Field>
                <FieldLabel htmlFor="reference_no">Reference No</FieldLabel>
                <Input
                  id="reference_no"
                  value={referenceNo}
                  onChange={(event) => setReferenceNo(event.target.value)}
                  aria-invalid={fieldErrors.reference_no !== undefined}
                />
                <FieldError>{fieldErrors.reference_no}</FieldError>
              </Field>

              <Field>
                <FieldLabel htmlFor="supplier_id">Pemasok</FieldLabel>
                <Select value={supplierId} onValueChange={setSupplierId}>
                  <SelectTrigger
                    id="supplier_id"
                    className="w-full"
                    aria-invalid={fieldErrors.supplier_id !== undefined}
                  >
                    <SelectValue placeholder="- pilih -" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      {suppliers.map((supplier) => (
                        <SelectItem
                          key={supplier.id}
                          value={String(supplier.id)}
                        >
                          {supplier.name}
                        </SelectItem>
                      ))}
                    </SelectGroup>
                  </SelectContent>
                </Select>
                <FieldError>{fieldErrors.supplier_id}</FieldError>
              </Field>

              <Field>
                <FieldLabel htmlFor="received_at">Diterima pada</FieldLabel>
                <Input
                  id="received_at"
                  type="datetime-local"
                  value={receivedAt}
                  onChange={(event) => setReceivedAt(event.target.value)}
                  aria-invalid={fieldErrors.received_at !== undefined}
                />
                <FieldError>{fieldErrors.received_at}</FieldError>
              </Field>
            </div>

            <div className="flex flex-col gap-2">
              <h3 className="font-heading text-base font-medium">Item</h3>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="min-w-56">Obat</TableHead>
                    <TableHead className="min-w-32">Batch No</TableHead>
                    <TableHead className="min-w-40">Kedaluwarsa</TableHead>
                    <TableHead className="w-28 text-right">Jumlah</TableHead>
                    <TableHead className="w-16" />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.key}>
                      <TableCell>
                        <Select
                          value={
                            row.medicine_id > 0 ? String(row.medicine_id) : ""
                          }
                          onValueChange={(value) =>
                            updateRow(row.key, { medicine_id: Number(value) })
                          }
                        >
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder="- pilih -" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectGroup>
                              {medicines.map((medicine) => (
                                <SelectItem
                                  key={medicine.id}
                                  value={String(medicine.id)}
                                >
                                  {medicine.name}
                                </SelectItem>
                              ))}
                            </SelectGroup>
                          </SelectContent>
                        </Select>
                      </TableCell>
                      <TableCell>
                        <Input
                          value={row.batch_no}
                          onChange={(event) =>
                            updateRow(row.key, {
                              batch_no: event.target.value,
                            })
                          }
                        />
                      </TableCell>
                      <TableCell>
                        <Input
                          type="date"
                          value={row.expires_on}
                          onChange={(event) =>
                            updateRow(row.key, {
                              expires_on: event.target.value,
                            })
                          }
                        />
                      </TableCell>
                      <TableCell>
                        <Input
                          type="number"
                          min={1}
                          step={1}
                          className="text-right"
                          value={String(row.quantity)}
                          onChange={(event) =>
                            updateRow(row.key, {
                              quantity: Number(event.target.value),
                            })
                          }
                        />
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          onClick={() => removeRow(row.key)}
                          aria-label="Hapus baris"
                          disabled={rows.length === 1}
                        >
                          <Trash2Icon className="text-destructive" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              <FieldError>{fieldErrors.items}</FieldError>

              <div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setRows((current) => [...current, blankRow()])}
                >
                  <CirclePlusIcon data-icon="inline-start" />
                  Tambah baris
                </Button>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <Button type="submit" disabled={saving || saved}>
                {saving ? "Menyimpan..." : "Simpan"}
              </Button>
              <Button asChild variant="link">
                <a href="/receptions">Kembali</a>
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <AuditLogTable logs={load.logs} />
    </div>
  )
}
