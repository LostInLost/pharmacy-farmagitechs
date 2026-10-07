import * as React from "react"
import { PencilIcon } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Feedback } from "@/components/feedback"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { getMedicine } from "@/features/medicines/api"
import type { MedicineRow } from "@/features/medicines/schemas"

type Props = {
  medicineId: number
  open: boolean
  onOpenChange: (open: boolean) => void
  /**
   * Tombol Ubah hanya dirender bila induk memutuskan pengguna berhak.
   * `undefined` = daftar masih dimuat; tombol disembunyikan sampai pasti.
   */
  canWrite: boolean | undefined
  onEdit: (id: number) => void
}

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; medicine: MedicineRow }

/**
 * Detail obat mode baca.
 *
 * Riwayat aksi sengaja tidak dirender di sini — audit menjadi menu tersendiri,
 * sama seperti detail penerimaan. Tombol "Ubah" hanya muncul bila server
 * menyatakan `can_write`; penegakan tetap di policy backend.
 */
export function MedicineViewSheet({
  medicineId,
  open,
  onOpenChange,
  canWrite,
  onEdit,
}: Props) {
  const [state, setState] = React.useState<State>({ status: "loading" })

  // Tanpa `setState` di badan efek (status awal sudah "loading"); pemanggil
  // memberi `key` per id sehingga berpindah obat me-remount komponen.
  React.useEffect(() => {
    let cancelled = false

    getMedicine(medicineId).then((result) => {
      if (cancelled) return

      setState(
        result.ok
          ? { status: "ready", medicine: result.data.medicine }
          : { status: "error", message: result.message }
      )
    })

    return () => {
      cancelled = true
    }
  }, [medicineId])

  const medicine = state.status === "ready" ? state.medicine : null

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader className="pr-10">
          <SheetTitle>
            {medicine === null ? "Detail Obat" : medicine.name}
          </SheetTitle>
          <SheetDescription>
            {medicine === null
              ? "Memuat detail obat..."
              : `Kode ${medicine.code}, satuan ${medicine.unit}.`}
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4">
          {state.status === "loading" && (
            <div className="flex flex-col gap-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-16 w-full" />
            </div>
          )}

          {state.status === "error" && (
            <Feedback messages={[state.message]} />
          )}

          {medicine !== null && (
            <dl className="grid gap-4 sm:grid-cols-2">
              <DetailField label="Kode" value={medicine.code} />
              <DetailField label="Nama Obat" value={medicine.name} />
              <DetailField label="Satuan" value={medicine.unit} />
              <div className="flex flex-col gap-1">
                <dt className="text-xs text-muted-foreground">Status</dt>
                <dd>
                  <Badge
                    variant={medicine.is_active ? "secondary" : "outline"}
                  >
                    {medicine.is_active ? "aktif" : "nonaktif"}
                  </Badge>
                </dd>
              </div>
            </dl>
          )}
        </div>

        <SheetFooter className="flex-row justify-end">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            Tutup
          </Button>
          {medicine !== null && canWrite && (
            <Button type="button" onClick={() => onEdit(medicine.id)}>
              <PencilIcon data-icon="inline-start" />
              Ubah
            </Button>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}

function DetailField({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd>{value}</dd>
    </div>
  )
}
