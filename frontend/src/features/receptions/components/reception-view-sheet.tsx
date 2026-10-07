import * as React from "react"
import { PencilIcon } from "lucide-react"

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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { formatDate, formatDateTime } from "@/foundations/format"
import { getReception } from "@/features/receptions/api"
import type { ReceptionDetail } from "@/features/receptions/schemas"

type Props = {
  receptionId: number
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Tombol Ubah hanya dirender bila induk memutuskan `can_update`. */
  canUpdate: boolean
  onEdit: (id: number) => void
}

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; detail: ReceptionDetail }

/**
 * Detail penerimaan mode baca — dipakai petugas untuk melihat dokumen
 * siapa pun (termasuk milik orang lain) tanpa membuka form yang bisa
 * disimpan.
 *
 * Riwayat aksi sengaja tidak dirender di sini: audit akan menjadi menu
 * tersendiri. Tombol "Ubah" hanya muncul bila server menyatakan
 * `can_update`; penegakan tetap di policy backend.
 */
export function ReceptionViewSheet({
  receptionId,
  open,
  onOpenChange,
  canUpdate,
  onEdit,
}: Props) {
  const [state, setState] = React.useState<State>({ status: "loading" })

  // Tanpa `setState` di badan efek (status awal sudah "loading"); pemanggil
  // memberi `key` per id sehingga berpindah penerimaan me-remount komponen.
  React.useEffect(() => {
    let cancelled = false

    getReception(receptionId).then((result) => {
      if (cancelled) return

      setState(
        result.ok
          ? { status: "ready", detail: result.data }
          : { status: "error", message: result.message }
      )
    })

    return () => {
      cancelled = true
    }
  }, [receptionId])

  const detail = state.status === "ready" ? state.detail : null

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="data-[side=right]:w-full data-[side=right]:sm:max-w-3xl">
        <SheetHeader className="pr-10">
          <SheetTitle>
            {detail === null ? "Detail Penerimaan" : detail.reference_no}
          </SheetTitle>
          <SheetDescription>
            {detail === null
              ? "Memuat detail penerimaan..."
              : `Diterima ${formatDateTime(detail.received_at)}${
                  detail.supplier_name === null
                    ? ""
                    : ` dari ${detail.supplier_name}`
                }.`}
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4">
          {state.status === "loading" && (
            <div className="flex flex-col gap-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-40 w-full" />
            </div>
          )}

          {state.status === "error" && (
            <Feedback messages={[state.message]} />
          )}

          {detail !== null && (
            <div className="flex flex-col gap-6">
              <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <DetailField label="Reference No" value={detail.reference_no} />
                <DetailField
                  label="Pemasok"
                  value={detail.supplier_name ?? "-"}
                />
                <DetailField
                  label="Diterima pada"
                  value={formatDateTime(detail.received_at)}
                />
                <DetailField
                  label="Dibuat oleh"
                  value={detail.created_by_name ?? "-"}
                  hint={formatDateTime(detail.created_at)}
                />
                <DetailField
                  label="Pengubah terakhir"
                  value={detail.updated_by_name ?? "belum pernah diubah"}
                  hint={
                    detail.updated_at === null
                      ? undefined
                      : formatDateTime(detail.updated_at)
                  }
                />
              </dl>

              <div className="flex flex-col gap-2">
                <h3 className="font-heading text-base font-medium">Item</h3>

                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Obat</TableHead>
                      <TableHead>Batch No</TableHead>
                      <TableHead>Kedaluwarsa</TableHead>
                      <TableHead className="text-right">Jumlah</TableHead>
                      <TableHead>Satuan</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {detail.items.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell>
                          <div>{item.medicine_name ?? "-"}</div>
                          <div className="text-xs text-muted-foreground">
                            {item.medicine_code ?? "-"}
                          </div>
                        </TableCell>
                        <TableCell>{item.batch_no}</TableCell>
                        <TableCell>{formatDate(item.expires_on)}</TableCell>
                        <TableCell className="text-right">
                          {item.quantity}
                        </TableCell>
                        <TableCell>{item.unit ?? "-"}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              {!canUpdate && (
                <p className="text-sm text-muted-foreground">
                  Anda hanya dapat melihat penerimaan ini.
                </p>
              )}
            </div>
          )}
        </div>

        <SheetFooter className="flex-row justify-end">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Tutup
          </Button>
          {detail !== null && canUpdate && (
            <Button type="button" onClick={() => onEdit(detail.id)}>
              <PencilIcon data-icon="inline-start" />
              Ubah
            </Button>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}

function DetailField({
  label,
  value,
  hint,
}: {
  label: string
  value: string
  hint?: string
}) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd>{value}</dd>
      {hint !== undefined && (
        <dd className="text-xs text-muted-foreground">{hint}</dd>
      )}
    </div>
  )
}
