import { Button } from "@/components/ui/button"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { formatDate } from "@/foundations/format"
import type { StockBatch, StockMedicine } from "@/features/stocks/schemas"

type Props = {
  medicine: StockMedicine
  /** Tanggal efektif laporan dari server (`on_date`). */
  onDate: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Detail batch satu obat — mode baca.
 *
 * Batch dipisah per kelompok (tersedia/kedaluwarsa) supaya status tidak perlu
 * dibaca ulang per baris. Seluruh angka tetap berasal dari server; sheet tidak
 * menghitung apa pun sendiri.
 */
export function StockBatchSheet({
  medicine,
  onDate,
  open,
  onOpenChange,
}: Props) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="data-[side=right]:w-full data-[side=right]:sm:max-w-lg">
        <SheetHeader className="pr-10">
          <SheetTitle>{medicine.name}</SheetTitle>
          <SheetDescription>
            Kode {medicine.code}, satuan {medicine.unit}. Status kedaluwarsa
            dihitung per {formatDate(onDate)}.
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4">
          <div className="flex flex-col gap-6">
            <dl className="grid grid-cols-3 gap-4">
              <Stat label="Stok fisik" value={medicine.physical_quantity} />
              <Stat label="Tersedia" value={medicine.available_quantity} />
              <Stat label="Kedaluwarsa" value={medicine.expired_quantity} />
            </dl>

            {medicine.available_batches.length > 0 && (
              <BatchGroup
                title="Batch tersedia"
                batches={medicine.available_batches}
              />
            )}

            {medicine.expired_batches.length > 0 && (
              <BatchGroup
                title="Batch kedaluwarsa"
                batches={medicine.expired_batches}
              />
            )}
          </div>
        </div>

        <SheetFooter className="flex-row justify-end">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            Tutup
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}

function BatchGroup({
  title,
  batches,
}: {
  title: string
  batches: StockBatch[]
}) {
  return (
    <div className="flex flex-col gap-2">
      <h3 className="font-heading text-base font-medium">
        {title}{" "}
        <span className="text-muted-foreground">({batches.length})</span>
      </h3>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Batch</TableHead>
            <TableHead>Kedaluwarsa</TableHead>
            <TableHead className="text-right">Jumlah</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {batches.map((batch) => (
            <TableRow key={batch.batch_no}>
              <TableCell>{batch.batch_no}</TableCell>
              <TableCell>
                {batch.expires_on ? formatDate(batch.expires_on) : "-"}
              </TableCell>
              <TableCell className="text-right">{batch.quantity}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="font-heading text-lg">{value}</dd>
    </div>
  )
}
