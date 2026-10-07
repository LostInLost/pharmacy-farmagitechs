import * as React from "react"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import { Feedback } from "@/components/feedback"
import { Field, FieldError, FieldLabel } from "@/components/ui/field"
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
import { createSupplier, getSupplier, updateSupplier } from "@/features/suppliers/api"
import {
  supplierFormSchema,
  type SupplierFormInput,
  type SupplierRow,
} from "@/features/suppliers/schemas"

type Props = {
  /** `null` = mode buat; angka = mode ubah. */
  supplierId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Dipanggil setelah server menyimpan; induk memuat ulang daftarnya. */
  onSaved: (supplier: SupplierRow) => void
  /**
   * Hak tulis menurut induk. `undefined` = induk belum tahu (daftar masih
   * dimuat saat deep link `?edit=`): jangan anggap sebagai penolakan, biarkan
   * cek `can_write` dari detail yang menentukan.
   */
  canWrite: boolean | undefined
}

type LoadState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "forbidden"; message: string }
  | { status: "ready" }

type FieldErrors = Record<string, string | undefined>

/** Nilai awal form, dipakai mode tambah maupun ubah. */
function initialInput(supplier: SupplierRow | null): SupplierFormInput {
  return {
    name: supplier?.name ?? "",
    is_active: supplier?.is_active ?? true,
  }
}

/** Penolakan yang sama untuk jalur induk (`canWrite === false`) dan detail (`can_write === false`). */
const FORBIDDEN_MESSAGE = "Anda tidak berhak mengubah pemasok ini."

/**
 * Form tambah/ubah pemasok di dalam sheet.
 *
 * Bentuk dan alur mengikuti `MedicineFormSheet`: header + isi yang bisa
 * digulir + footer aksi. Validasi bentuk dijalankan skema Zod; aturan yang
 * butuh data server (nama sudah dipakai pemasok lain) dirender apa adanya
 * dari `errors[]` backend.
 *
 * Isian tidak direset lewat efek: pemanggil memberi `key` per mode/id,
 * sehingga berpindah pemasok me-remount komponen dengan state segar.
 */
export function SupplierFormSheet({
  supplierId,
  open,
  onOpenChange,
  onSaved,
  canWrite,
}: Props) {
  const [loadState, setLoadState] = React.useState<LoadState>({
    status: supplierId === null ? "ready" : "loading",
  })
  const [input, setInput] = React.useState<SupplierFormInput>(() =>
    initialInput(null)
  )
  const [fieldErrors, setFieldErrors] = React.useState<FieldErrors>({})
  const [formErrors, setFormErrors] = React.useState<string[]>([])
  const [saving, setSaving] = React.useState(false)

  // Penolakan yang sudah pasti dari induk diturunkan saat render, bukan lewat
  // setState di dalam efek: status `forbidden` sudah benar sejak render
  // pertama dan efek pemuatan tidak perlu dijalankan sama sekali.
  const load: LoadState =
    supplierId !== null && canWrite === false
      ? { status: "forbidden", message: FORBIDDEN_MESSAGE }
      : loadState

  React.useEffect(() => {
    // Mode tambah tidak memanggil server: status awal sudah "ready".
    if (supplierId === null) return

    // Penolakan yang sudah pasti (induk selesai memuat daftar) menghentikan
    // pemuatan; detail sendiri juga memeriksa `can_write`.
    if (canWrite === false) return

    let cancelled = false

    getSupplier(supplierId).then((result) => {
      if (cancelled) return

      if (!result.ok) {
        setLoadState({ status: "error", message: result.message })
        return
      }

      // Policy backend menentukan hak ubah; form hanya mengikuti.
      if (!result.data.canWrite) {
        setLoadState({
          status: "forbidden",
          message: FORBIDDEN_MESSAGE,
        })
        return
      }

      setInput(initialInput(result.data.supplier))
      setLoadState({ status: "ready" })
    })

    return () => {
      cancelled = true
    }
  }, [supplierId, canWrite])

  function patch(values: Partial<SupplierFormInput>) {
    setInput((current) => ({ ...current, ...values }))
  }

  async function onSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (saving) return

    // Satu sumber kebenaran pesan validasi: skema. Aturan yang butuh data
    // server tetap dirender dari `errors[]` backend.
    const parsed = supplierFormSchema.safeParse(input)

    if (!parsed.success) {
      const flattened = z.flattenError(parsed.error)

      setFieldErrors({
        name: flattened.fieldErrors.name?.[0],
      })
      setFormErrors([])
      return
    }

    setFieldErrors({})
    setFormErrors([])
    setSaving(true)

    const result =
      supplierId === null
        ? await createSupplier(parsed.data)
        : await updateSupplier(supplierId, parsed.data)

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

  const title = supplierId === null ? "Tambah Pemasok" : "Ubah Pemasok"

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader className="pr-10">
          <SheetTitle>{title}</SheetTitle>
          <SheetDescription>
            Nama pemasok wajib unik; pemasok tidak dihapus, cukup
            dinonaktifkan agar riwayat penerimaannya tetap utuh.
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4">
          {load.status === "loading" && (
            <div className="flex flex-col gap-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          )}

          {load.status === "error" && (
            <Feedback messages={[load.message]} />
          )}

          {load.status === "forbidden" && (
            <Feedback messages={[load.message]} />
          )}

          {load.status === "ready" && (
            <form
              id="supplier-form"
              onSubmit={onSubmit}
              noValidate
              className="flex flex-col gap-4"
            >
              {formErrors.length > 0 && (
                <Feedback messages={formErrors} />
              )}

              <Field>
                <FieldLabel htmlFor="supplier_name">Nama Pemasok</FieldLabel>
                <Input
                  id="supplier_name"
                  value={input.name}
                  onChange={(event) => patch({ name: event.target.value })}
                  aria-invalid={fieldErrors.name !== undefined}
                />
                <FieldError>{fieldErrors.name}</FieldError>
              </Field>

              <Field>
                <FieldLabel htmlFor="supplier_status">Status</FieldLabel>
                <Select
                  value={input.is_active ? "active" : "inactive"}
                  onValueChange={(value) =>
                    patch({ is_active: value === "active" })
                  }
                >
                  <SelectTrigger id="supplier_status" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      <SelectItem value="active">aktif</SelectItem>
                      <SelectItem value="inactive">nonaktif</SelectItem>
                    </SelectGroup>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  Pemasok nonaktif tidak muncul di pilihan form penerimaan,
                  tetapi tetap tampil di daftar ini.
                </p>
              </Field>
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
            <Button type="submit" form="supplier-form" disabled={saving}>
              {saving ? "Menyimpan..." : "Simpan"}
            </Button>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
