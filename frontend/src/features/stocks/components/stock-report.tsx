import * as React from "react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Feedback } from "@/components/feedback"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
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
import { formatDate } from "@/foundations/format"
import type { ApiResult } from "@/foundations/api/request"
import { getStockReport } from "@/features/stocks/api"
import type {
  StockBatch,
  StockMedicine,
  StockReport,
} from "@/features/stocks/schemas"

type StatusFilter = "all" | "available" | "expired"

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; medicines: StockMedicine[] }

const COLUMNS = [
  "Kode",
  "Obat",
  "Satuan",
  "Fisik",
  "Tersedia",
  "Kedaluwarsa",
  "Batch",
]

export function StockReport() {
  const [state, setState] = React.useState<State>({ status: "loading" })
  const [onDate, setOnDate] = React.useState("")
  const [statusFilter, setStatusFilter] = React.useState<StatusFilter>("all")

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

  function onSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setState({ status: "loading" })
    getStockReport(onDate).then(applyResult)
  }

  // Filter status hanya menyaring tampilan; angka tetap dari server.
  const rows =
    state.status === "ready"
      ? state.medicines.filter(
          (medicine) => batchesFor(medicine, statusFilter).length > 0
        )
      : []

  return (
    <div className="flex flex-col gap-4 px-4 lg:px-6">
      <div>
        <h2 className="font-heading text-lg font-medium">Laporan Stok</h2>
        <p className="text-sm text-muted-foreground">
          Jumlah stok selalu dihitung dari seluruh transaksi tersimpan; tanggal
          hanya menentukan status kedaluwarsa.
        </p>
      </div>

      <Card>
        <CardContent>
          <form onSubmit={onSubmit} className="flex flex-wrap items-end gap-3">
            <div className="grid gap-2">
              <Label htmlFor="on_date">Tanggal pemeriksaan kedaluwarsa</Label>
              <Input
                id="on_date"
                type="date"
                className="w-48"
                value={onDate}
                onChange={(event) => setOnDate(event.target.value)}
              />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="status_filter">Status batch</Label>
              <Select
                value={statusFilter}
                onValueChange={(value) =>
                  setStatusFilter(value as StatusFilter)
                }
              >
                <SelectTrigger id="status_filter" className="w-48">
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
            </div>

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
                {COLUMNS.map((column, index) => (
                  <TableHead
                    key={column}
                    className={index >= 3 && index <= 5 ? "text-right" : undefined}
                  >
                    {column}
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
                  <TableCell
                    colSpan={COLUMNS.length}
                    className="h-24 text-center text-muted-foreground"
                  >
                    {statusFilter === "all"
                      ? "Belum ada data stok."
                      : "Tidak ada obat yang cocok dengan status ini."}
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
                    <TableCell>
                      <BatchDetails
                        medicine={medicine}
                        statusFilter={statusFilter}
                      />
                    </TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

function batchesFor(
  medicine: StockMedicine,
  statusFilter: StatusFilter
): StockBatch[] {
  if (statusFilter === "available") return medicine.available_batches
  if (statusFilter === "expired") return medicine.expired_batches

  return [...medicine.available_batches, ...medicine.expired_batches]
}

function BatchDetails({
  medicine,
  statusFilter,
}: {
  medicine: StockMedicine
  statusFilter: StatusFilter
}) {
  const batches = batchesFor(medicine, statusFilter)

  if (batches.length === 0) {
    return (
      <span className="text-muted-foreground">
        {statusFilter === "all" ? "belum ada batch" : "tidak ada batch"}
      </span>
    )
  }

  return (
    <details>
      <summary className="cursor-pointer whitespace-nowrap">
        {batches.length} batch
      </summary>
      <div className="mt-2 overflow-x-auto">
        <table className="w-full caption-bottom text-xs">
          <thead className="border-b [&_th]:h-8 [&_th]:px-2 [&_th]:text-left [&_th]:font-medium">
            <tr>
              <th>Batch</th>
              <th>Kedaluwarsa</th>
              <th className="text-right">Jumlah</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {batches.map((batch) => (
              <tr key={batch.batch_no} className="border-b last:border-0">
                <td className="p-2">{batch.batch_no}</td>
                <td className="p-2">
                  {batch.expires_on ? formatDate(batch.expires_on) : "-"}
                </td>
                <td className="p-2 text-right">{batch.quantity}</td>
                <td className="p-2">
                  <Badge
                    variant={batch.is_expired ? "destructive" : "secondary"}
                  >
                    {batch.is_expired ? "kedaluwarsa" : "tersedia"}
                  </Badge>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </details>
  )
}
