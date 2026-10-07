import * as React from "react"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
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
  createMedicine,
  updateMedicine,
} from "@/features/medicines/api"
import {
  medicineFormSchema,
  type MedicineFormInput,
  type MedicineRow,
} from "@/features/medicines/schemas"

type Props = {
  /** `null` = mode tambah; baris = mode ubah. */
  medicine: MedicineRow | null
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Dipanggil setelah server menyimpan; induk memuat ulang daftarnya. */
  onSaved: (medicine: MedicineRow) => void
}

type FieldErrors = Record<string, string | undefined>

/** Nilai awal form, dipakai mode tambah maupun ubah. */
function initialInput(medicine: MedicineRow | null): MedicineFormInput {
  return {
    code: medicine?.code ?? "",
    name: medicine?.name ?? "",
    unit: medicine?.unit ?? "",
    is_active: medicine?.is_active ?? true,
  }
}

/**
 * Form tambah/ubah obat dalam dialog.
 *
 * Validasi bentuk dijalankan skema Zod; aturan yang butuh data server
 * (kode sudah dipakai obat lain) dirender apa adanya dari `errors[]`
 * backend, sama seperti form penerimaan.
 *
 * Form tidak mereset lewat efek: pemanggil memberi `key` per baris, sehingga
 * berpindah baris me-remount komponen dan state-nya kembali segar — tanpa
 * setState di dalam efek.
 */
export function MedicineFormDialog({
  medicine,
  open,
  onOpenChange,
  onSaved,
}: Props) {
  const [input, setInput] = React.useState<MedicineFormInput>(() =>
    initialInput(medicine)
  )
  const [fieldErrors, setFieldErrors] = React.useState<FieldErrors>({})
  const [formErrors, setFormErrors] = React.useState<string[]>([])
  const [saving, setSaving] = React.useState(false)

  function patch(values: Partial<MedicineFormInput>) {
    setInput((current) => ({ ...current, ...values }))
  }

  async function onSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (saving) return

    const parsed = medicineFormSchema.safeParse(input)

    if (!parsed.success) {
      const flattened = z.flattenError(parsed.error)

      setFieldErrors({
        code: flattened.fieldErrors.code?.[0],
        name: flattened.fieldErrors.name?.[0],
        unit: flattened.fieldErrors.unit?.[0],
      })
      setFormErrors([])
      return
    }

    setFieldErrors({})
    setFormErrors([])
    setSaving(true)

    const result =
      medicine === null
        ? await createMedicine(parsed.data)
        : await updateMedicine(medicine.id, parsed.data)

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

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {medicine === null ? "Tambah Obat" : "Ubah Obat"}
          </DialogTitle>
          <DialogDescription>
            Kode wajib unik; satuan mengikuti satuan obat tanpa konversi.
          </DialogDescription>
        </DialogHeader>

        {formErrors.length > 0 && (
          <Feedback variant="error" messages={formErrors} />
        )}

        <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
          <Field>
            <FieldLabel htmlFor="medicine_code">Kode</FieldLabel>
            <Input
              id="medicine_code"
              value={input.code}
              onChange={(event) => patch({ code: event.target.value })}
              aria-invalid={fieldErrors.code !== undefined}
            />
            <FieldError>{fieldErrors.code}</FieldError>
          </Field>

          <Field>
            <FieldLabel htmlFor="medicine_name">Nama Obat</FieldLabel>
            <Input
              id="medicine_name"
              value={input.name}
              onChange={(event) => patch({ name: event.target.value })}
              aria-invalid={fieldErrors.name !== undefined}
            />
            <FieldError>{fieldErrors.name}</FieldError>
          </Field>

          <Field>
            <FieldLabel htmlFor="medicine_unit">Satuan</FieldLabel>
            <Input
              id="medicine_unit"
              value={input.unit}
              onChange={(event) => patch({ unit: event.target.value })}
              aria-invalid={fieldErrors.unit !== undefined}
            />
            <FieldError>{fieldErrors.unit}</FieldError>
          </Field>

          <Field>
            <FieldLabel htmlFor="medicine_status">Status</FieldLabel>
            <Select
              value={input.is_active ? "active" : "inactive"}
              onValueChange={(value) =>
                patch({ is_active: value === "active" })
              }
            >
              <SelectTrigger id="medicine_status" className="w-full">
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
              Obat nonaktif tidak muncul di pilihan form penerimaan, tetapi
              tetap tampil di daftar ini.
            </p>
          </Field>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={saving}
            >
              Batal
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? "Menyimpan..." : "Simpan"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
