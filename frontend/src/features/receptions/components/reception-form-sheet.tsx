import * as React from "react"
import { z } from "zod"
import { CirclePlusIcon, Trash2Icon } from "lucide-react"

import { Button } from "@/components/ui/button"
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
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
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
  type Medicine,
  type ReceptionFormInput,
  type ReceptionWriteDetail,
  type Supplier,
} from "@/features/receptions/schemas"

type Props = {
  /** `null` = mode buat; angka = mode ubah. */
  receptionId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Dipanggil setelah server menyimpan; induk memuat ulang daftarnya. */
  onSaved: (detail: ReceptionWriteDetail) => void
  /**
   * Pindah ke sheet detail. Dipakai saat `can_update = false`: alih-alih
   * menutup sheet begitu saja, pengguna ditawari melihat isinya.
   */
  onViewDetail?: (id: number) => void
}

type LoadState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "forbidden"; message: string }
  | { status: "ready" }

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

/**
 * Form tambah/ubah penerimaan di dalam sheet.
 *
 * Bentuknya sama dengan halaman lama (header + tabel item) tetapi tanpa
 * riwayat aksi — audit kini tidak ditampilkan di form mana pun. Setelah
 * tersimpan sheet ditutup lewat `onSaved`, dan induk yang memuat ulang daftar.
 *
 * Isian tidak direset lewat efek: pemanggil memberi `key` per mode/id,
 * sehingga berpindah penerimaan me-remount komponen dengan state segar.
 */
export function ReceptionFormSheet({
  receptionId,
  open,
  onOpenChange,
  onSaved,
  onViewDetail,
}: Props) {
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
        setLoad({ status: "ready" })
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
      setLoad({ status: "ready" })
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

    setSaving(false)
    onSaved(result.data)
    onOpenChange(false)
  }

  const title = receptionId === null ? "Penerimaan Baru" : "Ubah Penerimaan"

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        // Sheet bawaan hanya selebar `sm:max-w-sm`; form item butuh ruang
        // tabel, jadi lebarnya dinaikkan (cn menimpa varian yang sama).
        className="data-[side=right]:w-full data-[side=right]:sm:max-w-3xl"
      >
        <SheetHeader className="pr-10">
          <SheetTitle>{title}</SheetTitle>
          <SheetDescription>
            Isi header, lalu tambahkan item beserta batch dan kedaluwarsanya.
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4">
          {load.status === "loading" && (
            <div className="flex flex-col gap-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-40 w-full" />
            </div>
          )}

          {load.status === "error" && (
            <Feedback variant="error" messages={[load.message]} />
          )}

          {load.status === "forbidden" && (
            <div className="flex flex-col gap-4">
              <Feedback variant="error" messages={[load.message]} />
              <p className="text-sm text-muted-foreground">
                Penerimaan milik petugas lain tetap dapat dibuka dalam mode
                lihat.
              </p>
              {onViewDetail !== undefined && receptionId !== null && (
                <div>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => onViewDetail(receptionId)}
                  >
                    Lihat detail
                  </Button>
                </div>
              )}
            </div>
          )}

          {load.status === "ready" && (
            <form
              id="reception-form"
              onSubmit={onSubmit}
              noValidate
              className="flex flex-col gap-6"
            >
              {formErrors.length > 0 && (
                <Feedback variant="error" messages={formErrors} />
              )}

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
                    onClick={() =>
                      setRows((current) => [...current, blankRow()])
                    }
                  >
                    <CirclePlusIcon data-icon="inline-start" />
                    Tambah baris
                  </Button>
                </div>
              </div>
            </form>
          )}
        </div>

        <SheetFooter className="flex-row justify-end">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={saving}
          >
            {load.status === "forbidden" ? "Tutup" : "Batal"}
          </Button>
          {load.status === "ready" && (
            <Button type="submit" form="reception-form" disabled={saving}>
              {saving ? "Menyimpan..." : "Simpan"}
            </Button>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
