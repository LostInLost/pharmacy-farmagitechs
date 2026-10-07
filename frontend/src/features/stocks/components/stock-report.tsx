import * as React from "react"

import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty"
import { Feedback } from "@/components/feedback"
import { Field, FieldLabel } from "@/components/ui/field"
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
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import type { ApiResult } from "@/foundations/api/request"
import { getStockReport } from "@/features/stocks/api"
import type {
  StockBatch,
  StockMedicine,
  StockReport,
} from "@/features/stocks/schemas"
import { BoxesIcon, PackageXIcon } from "lucide-react"
import { StockBatchSheet } from "./stock-batch-sheet"

type StatusFilter = "all" | "available" | "expired"

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; medicines: StockMedicine[] }

const COLUMNS: { label: string; align?: "right" }[] = [
  { label: "Kode" },
  { label: "Obat" },
  { label: "Satuan" },
  { label: "Fisik", align: "right" },
  { label: "Tersedia", align: "right" },
  { label: "Kedaluwarsa", align: "right" },
  { label: "Aksi", align: "right" },
]

type Props = {
  /** Obat yang detail batch-nya dibuka dari query string (`?view=<id>`). */
  initialViewId?: number | null
}

/**
 * Laporan stok sekaligus orkestrator sheet detail batch.
 *
 * Detail batch dulu berupa collapsible di dalam baris; kini menjadi sheet agar
 * tabel tetap ringkas dan satu batch tidak mendorong tinggi baris. Membuka
 * sheet menyinkronkan URL lewat `history.replaceState`, sehingga `?view=<id>`
 * tetap bisa dibagikan dan dimuat langsung lewat SSR.
 */
export function StockReport({ initialViewId = null }: Props) {
  const [state, setState] = React.useState<State>({ status: "loading" })
  const [onDate, setOnDate] = React.useState("")
  const [statusFilter, setStatusFilter] = React.useState<StatusFilter>("all")
  const [viewId, setViewId] = React.useState<number | null>(initialViewId)
  const [sheetOpen, setSheetOpen] = React.useState(initialViewId !== null)
  const [sheetSeq, setSheetSeq] = React.useState(0)

  /** Petakan hasil request ke state; dipanggil dari callback, bukan efek. */
  const applyResult = React.useCallback((result: ApiResult<StockReport>) => {
    if (result.ok) {
      setState({ status: "ready", medicines: result.data.medicines })
      // Server memutuskan tanggal efektif; input diselaraskan agar cocok.
      setOnDate(result.data.on_date)
    } else {
      setState({ status: "error", message: result.message })
    }
  }, [])

  React.useEffect(() => {
    let cancelled = false

    // Status awal sudah "loading"; setState hanya di dalam callback.
    getStockReport("").then((result) => {
      if (!cancelled) applyResult(result)
    })

    return () => {
      cancelled = true
    }
  }, [applyResult])

  // Sinkronkan URL dengan sheet yang terbuka. Kuncinya `sheetOpen`, bukan
  // `viewId`: saat menutup, `viewId` sengaja masih terisi (lihat `closeSheet`)
  // supaya Radix sempat memainkan animasi keluar.
  React.useEffect(() => {
    const url = new URL(window.location.href)

    url.searchParams.delete("view")

    if (viewId !== null && sheetOpen) {
      url.searchParams.set("view", String(viewId))
    }

    window.history.replaceState(null, "", url)
  }, [viewId, sheetOpen])

  function onSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setState({ status: "loading" })
    getStockReport(onDate).then(applyResult)
  }

  function openView(medicineId: number) {
    setViewId(medicineId)
    setSheetOpen(true)
    setSheetSeq((seq) => seq + 1)
  }

  function closeSheet() {
    setSheetOpen(false)
  }

  /**
   * Filter status hanya menyaring tampilan; angka tetap dari server.
   *
   * "Semua status" menampilkan setiap obat — termasuk yang belum punya batch
   * sama sekali — agar cocok dengan halaman CI4. Status lain hanya menyisakan
   * obat yang punya batch pada kelompok tersebut.
   */
  const medicines = state.status === "ready" ? state.medicines : []
  const rows = medicines.filter((medicine) =>
    statusFilter === "all"
      ? true
      : batchesFor(medicine, statusFilter).length > 0
  )

  // Dicari di seluruh obat, bukan hanya baris yang lolos filter: mengganti
  // filter saat sheet terbuka tidak boleh membuat sheet kehilangan datanya.
  const viewed =
    viewId === null
      ? null
      : (medicines.find((medicine) => medicine.medicine_id === viewId) ?? null)

  return (
    // Provider lokal, bukan mengandalkan milik `AppShell`: island Astro punya
    // React root sendiri, sehingga konteks dari island tetangga tidak sampai.
    <TooltipProvider>
      <div className="flex flex-col gap-4 px-4 lg:px-6">
        <div>
          <h2 className="font-heading text-lg font-medium">Laporan Stok</h2>
          <p className="text-sm text-muted-foreground">
            Jumlah stok selalu dihitung dari seluruh transaksi tersimpan;
            tanggal hanya menentukan status kedaluwarsa.
          </p>
        </div>

        <Card>
          <CardContent>
            <form
              onSubmit={onSubmit}
              className="flex flex-wrap items-end gap-3"
            >
              <Field className="w-48">
                <FieldLabel htmlFor="on_date">
                  Tanggal pemeriksaan kedaluwarsa
                </FieldLabel>
                <Input
                  id="on_date"
                  type="date"
                  value={onDate}
                  onChange={(event) => setOnDate(event.target.value)}
                />
              </Field>

              <Field className="w-48">
                <FieldLabel htmlFor="status_filter">Status batch</FieldLabel>
                <Select
                  value={statusFilter}
                  onValueChange={(value) =>
                    setStatusFilter(value as StatusFilter)
                  }
                >
                  <SelectTrigger id="status_filter" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      <SelectItem value="all">Semua status</SelectItem>
                      <SelectItem value="available">Hanya tersedia</SelectItem>
                      <SelectItem value="expired">Hanya kedaluwarsa</SelectItem>
                    </SelectGroup>
                  </SelectContent>
                </Select>
              </Field>

              <Button type="submit" disabled={state.status === "loading"}>
                {state.status === "loading" ? "Memuat..." : "Tampilkan"}
              </Button>
            </form>
          </CardContent>
        </Card>

        {state.status === "error" && (
          <Feedback variant="error" messages={[state.message]} />
        )}

        <Card className="py-0">
          <CardContent className="px-0">
            <Table>
              <TableHeader>
                <TableRow>
                  {COLUMNS.map((column) => (
                    <TableHead
                      key={column.label}
                      className={alignClass(column)}
                    >
                      {column.label}
                    </TableHead>
                  ))}
                </TableRow>
              </TableHeader>
              <TableBody>
                {state.status === "loading" &&
                  Array.from({ length: 4 }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell colSpan={COLUMNS.length}>
                        <Skeleton className="h-5 w-full" />
                      </TableCell>
                    </TableRow>
                  ))}

                {state.status === "ready" && rows.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={COLUMNS.length} className="p-0">
                      <Empty className="rounded-none border-0 py-10">
                        <EmptyHeader>
                          <EmptyMedia variant="icon">
                            <PackageXIcon />
                          </EmptyMedia>
                          <EmptyTitle>
                            {statusFilter === "all"
                              ? "Belum ada data stok."
                              : "Tidak ada obat yang cocok dengan status ini."}
                          </EmptyTitle>
                          <EmptyDescription>
                            Coba ubah tanggal pemeriksaan atau status batch.
                          </EmptyDescription>
                        </EmptyHeader>
                      </Empty>
                    </TableCell>
                  </TableRow>
                )}

                {state.status === "ready" &&
                  rows.map((medicine) => (
                    <TableRow key={medicine.medicine_id}>
                      <TableCell>{medicine.code}</TableCell>
                      <TableCell className="whitespace-normal">
                        {medicine.name}
                      </TableCell>
                      <TableCell>{medicine.unit}</TableCell>
                      <TableCell className="text-right">
                        {medicine.physical_quantity}
                      </TableCell>
                      <TableCell className="text-right">
                        {medicine.available_quantity}
                      </TableCell>
                      <TableCell className="text-right">
                        {medicine.expired_quantity}
                      </TableCell>
                      <TableCell className="text-right">
                        <BatchAction medicine={medicine} onView={openView} />
                      </TableCell>
                    </TableRow>
                  ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {viewed !== null && (
          <StockBatchSheet
            key={`batch-${viewed.medicine_id}-${sheetSeq}`}
            medicine={viewed}
            onDate={onDate}
            open={sheetOpen}
            onOpenChange={(open) => {
              if (!open) closeSheet()
            }}
          />
        )}
      </div>
    </TooltipProvider>
  )
}

function alignClass(column: { align?: "right" }): string | undefined {
  return column.align === "right" ? "text-right" : undefined
}

function batchesFor(
  medicine: StockMedicine,
  statusFilter: StatusFilter
): StockBatch[] {
  if (statusFilter === "available") return medicine.available_batches
  if (statusFilter === "expired") return medicine.expired_batches

  return [...medicine.available_batches, ...medicine.expired_batches]
}

/**
 * Aksi baris berupa ikon: membuka sheet detail batch. Hitungannya seluruh
 * batch (tersedia + kedaluwarsa) karena sheet menampilkan keduanya — filter
 * status hanya menyaring baris, bukan isi sheet. Obat tanpa batch sama sekali
 * tidak punya apa pun untuk ditampilkan, jadi diberi tanda "-".
 */
function BatchAction({
  medicine,
  onView,
}: {
  medicine: StockMedicine
  onView: (medicineId: number) => void
}) {
  const total =
    medicine.available_batches.length + medicine.expired_batches.length

  if (total === 0) {
    return <span className="text-muted-foreground">-</span>
  }

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon-sm"
          onClick={() => onView(medicine.medicine_id)}
          aria-label={`Lihat ${total} batch ${medicine.name}`}
        >
          <BoxesIcon />
        </Button>
      </TooltipTrigger>
      <TooltipContent side="top">Lihat {total} batch</TooltipContent>
    </Tooltip>
  )
}
