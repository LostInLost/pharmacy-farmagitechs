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
import { getSupplier } from "@/features/suppliers/api"
import type { SupplierRow } from "@/features/suppliers/schemas"

type Props = {
  supplierId: number
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
  | { status: "ready"; supplier: SupplierRow }

/**
 * Detail pemasok mode baca.
 *
 * Riwayat aksi sengaja tidak dirender di sini — audit menjadi menu tersendiri,
 * sama seperti detail obat dan penerimaan. Tombol "Ubah" hanya muncul bila
 * server menyatakan `can_write`; penegakan tetap di policy backend.
 */
export function SupplierViewSheet({
  supplierId,
  open,
  onOpenChange,
  canWrite,
  onEdit,
}: Props) {
  const [state, setState] = React.useState<State>({ status: "loading" })

  // Tanpa `setState` di badan efek (status awal sudah "loading"); pemanggil
  // memberi `key` per id sehingga berpindah pemasok me-remount komponen.
  React.useEffect(() => {
    let cancelled = false

    getSupplier(supplierId).then((result) => {
      if (cancelled) return

      setState(
        result.ok
          ? { status: "ready", supplier: result.data.supplier }
          : { status: "error", message: result.message }
      )
    })

    return () => {
      cancelled = true
    }
  }, [supplierId])

  const supplier = state.status === "ready" ? state.supplier : null

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader className="pr-10">
          <SheetTitle>
            {supplier === null ? "Detail Pemasok" : supplier.name}
          </SheetTitle>
          <SheetDescription>
            {supplier === null
              ? "Memuat detail pemasok..."
              : `Pemasok ${
                  supplier.is_active ? "aktif" : "nonaktif"
                } — dipakai pada transaksi penerimaan.`}
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

          {supplier !== null && (
            <dl className="grid gap-4 sm:grid-cols-2">
              <DetailField label="Nama Pemasok" value={supplier.name} />
              <div className="flex flex-col gap-1">
                <dt className="text-xs text-muted-foreground">Status</dt>
                <dd>
                  <Badge
                    variant={supplier.is_active ? "secondary" : "outline"}
                  >
                    {supplier.is_active ? "aktif" : "nonaktif"}
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
          {supplier !== null && canWrite && (
            <Button type="button" onClick={() => onEdit(supplier.id)}>
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
