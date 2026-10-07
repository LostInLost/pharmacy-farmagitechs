import * as React from "react"
import { PackageIcon, TriangleAlertIcon, TruckIcon } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Feedback } from "@/components/feedback"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { formatDateTime } from "@/foundations/format"
import { hasPermission, PERMISSIONS } from "@/features/auth"
import { listReceptions } from "@/features/receptions/api"
import type { ReceptionRow } from "@/features/receptions/schemas"
import { getStockReport } from "@/features/stocks/api"

type Summary = {
  receptionCount: number
  medicineCount: number
  available: number
  expired: number
  lowStock: number
}

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; summary: Summary; latest: ReceptionRow[] }

/** Ringkasan stok dianggap perlu perhatian bila sisa tersedia di bawah ini. */
const LOW_STOCK_THRESHOLD = 10

const RECENT_LIMIT = 5

/**
 * Ringkasan + pintasan. Tombol "Tambah Penerimaan" digating permission
 * `receipt.create` (dari `GET /api/me` lewat `Astro.locals.user`), sedangkan
 * tautan baris selalu boleh: detail penerimaan dapat dilihat semua role.
 */
export function DashboardOverview({
  permissions = [],
}: {
  permissions?: string[]
}) {
  const [state, setState] = React.useState<State>({ status: "loading" })

  React.useEffect(() => {
    let cancelled = false

    async function load() {
      const [receptionResult, stockResult] = await Promise.all([
        listReceptions(),
        getStockReport(),
      ])

      if (cancelled) return

      if (!receptionResult.ok) {
        setState({ status: "error", message: receptionResult.message })
        return
      }

      if (!stockResult.ok) {
        setState({ status: "error", message: stockResult.message })
        return
      }

      const medicines = stockResult.data.medicines

      setState({
        status: "ready",
        summary: {
          receptionCount: receptionResult.data.length,
          medicineCount: medicines.length,
          available: medicines.reduce(
            (total, medicine) => total + medicine.available_quantity,
            0
          ),
          expired: medicines.reduce(
            (total, medicine) => total + medicine.expired_quantity,
            0
          ),
          lowStock: medicines.filter(
            (medicine) => medicine.available_quantity < LOW_STOCK_THRESHOLD
          ).length,
        },
        latest: receptionResult.data.slice(0, RECENT_LIMIT),
      })
    }

    load()

    return () => {
      cancelled = true
    }
  }, [])

  if (state.status === "error") {
    return (
      <div className="px-4 lg:px-6">
        <Feedback variant="error" messages={[state.message]} />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 px-4 md:grid-cols-3 lg:px-6">
        <SummaryCard
          title="Total Penerimaan"
          icon={<TruckIcon className="size-4 text-muted-foreground" />}
          value={state.status === "ready" ? state.summary.receptionCount : null}
          hint="Dokumen penerimaan tercatat"
        />
        <SummaryCard
          title="Stok Tersedia"
          icon={<PackageIcon className="size-4 text-muted-foreground" />}
          value={state.status === "ready" ? state.summary.available : null}
          hint={
            state.status === "ready"
              ? `${state.summary.medicineCount} obat aktif`
              : undefined
          }
        />
        <SummaryCard
          title="Perlu Perhatian"
          icon={<TriangleAlertIcon className="size-4 text-muted-foreground" />}
          value={state.status === "ready" ? state.summary.expired : null}
          hint={
            state.status === "ready"
              ? `unit kedaluwarsa · ${state.summary.lowStock} obat stok menipis`
              : undefined
          }
        />
      </div>

      <div className="px-4 lg:px-6">
        <Card className="py-0">
          <CardHeader className="flex flex-row items-center justify-between pt-4">
            <div>
              <CardTitle>Penerimaan Terbaru</CardTitle>
              <CardDescription>
                {RECENT_LIMIT} dokumen terakhir yang tercatat.
              </CardDescription>
            </div>
            <div className="flex gap-2">
              <Button asChild variant="outline" size="sm">
                <a href="/stocks">Lihat stok</a>
              </Button>
              {hasPermission(permissions, PERMISSIONS.receiptCreate) && (
                <Button asChild size="sm">
                  <a href="/receptions?new=1">Tambah Penerimaan</a>
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent className="px-0 pb-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Reference</TableHead>
                  <TableHead>Pemasok</TableHead>
                  <TableHead>Diterima</TableHead>
                  <TableHead>Pembuat</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {state.status === "loading" &&
                  Array.from({ length: 3 }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell colSpan={4}>
                        <Skeleton className="h-5 w-full" />
                      </TableCell>
                    </TableRow>
                  ))}

                {state.status === "ready" && state.latest.length === 0 && (
                  <TableRow>
                    <TableCell
                      colSpan={4}
                      className="h-24 text-center text-muted-foreground"
                    >
                      Belum ada penerimaan.
                    </TableCell>
                  </TableRow>
                )}

                {state.status === "ready" &&
                  state.latest.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>
                        <a
                          className="font-medium text-primary underline-offset-4 hover:underline"
                          href={`/receptions?view=${row.id}`}
                        >
                          {row.reference_no}
                        </a>
                      </TableCell>
                      <TableCell>{row.supplier_name ?? "-"}</TableCell>
                      <TableCell>{formatDateTime(row.received_at)}</TableCell>
                      <TableCell>{row.created_by_name ?? "-"}</TableCell>
                    </TableRow>
                  ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

function SummaryCard({
  title,
  icon,
  value,
  hint,
}: {
  title: string
  icon: React.ReactNode
  value: number | null
  hint?: string
}) {
  return (
    <Card>
      <CardHeader>
        <CardDescription className="flex items-center gap-2">
          {icon}
          {title}
        </CardDescription>
        <CardTitle className="font-heading text-2xl font-semibold tabular-nums">
          {value === null ? <Skeleton className="h-7 w-16" /> : value}
        </CardTitle>
      </CardHeader>
      {hint !== undefined && value !== null && (
        <CardContent className="text-xs text-muted-foreground">
          {hint}
        </CardContent>
      )}
    </Card>
  )
}
