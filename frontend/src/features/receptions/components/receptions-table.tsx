import * as React from "react"

import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
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
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty"
import { Feedback } from "@/components/feedback"
import { formatDateTime } from "@/foundations/format"
import { listReceptions } from "@/features/receptions/api"
import type { ReceptionRow } from "@/features/receptions/schemas"
import { CirclePlusIcon, InboxIcon } from "lucide-react"

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; rows: ReceptionRow[] }

const COLUMNS = [
  "Reference",
  "Pemasok",
  "Diterima",
  "Pembuat",
  "Pengubah terakhir",
  "Aksi",
]

export function ReceptionsTable() {
  const [state, setState] = React.useState<State>({ status: "loading" })

  React.useEffect(() => {
    let cancelled = false

    listReceptions().then((result) => {
      if (cancelled) return

      setState(
        result.ok
          ? { status: "ready", rows: result.data }
          : { status: "error", message: result.message }
      )
    })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <div className="flex flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="font-heading text-lg font-medium">Daftar Penerimaan</h2>
          <p className="text-sm text-muted-foreground">
            Barang masuk beserta batch dan masa kedaluwarsanya.
          </p>
        </div>
        <Button asChild>
          <a href="/receptions/new">
            <CirclePlusIcon data-icon="inline-start" />
            Tambah Penerimaan
          </a>
        </Button>
      </div>

      {state.status === "error" && <Feedback variant="error" messages={[state.message]} />}

      <Card className="py-0">
        <CardContent className="px-0">
          <Table>
            <TableHeader>
              <TableRow>
                {COLUMNS.map((column) => (
                  <TableHead key={column}>{column}</TableHead>
                ))}
              </TableRow>
            </TableHeader>
            <TableBody>
              {state.status === "loading" &&
                Array.from({ length: 3 }).map((_, index) => (
                  <TableRow key={index}>
                    <TableCell colSpan={COLUMNS.length}>
                      <Skeleton className="h-5 w-full" />
                    </TableCell>
                  </TableRow>
                ))}

              {state.status === "ready" && state.rows.length === 0 && (
                <TableRow>
                  <TableCell colSpan={COLUMNS.length} className="p-0">
                    <Empty className="rounded-none border-0 py-10">
                      <EmptyHeader>
                        <EmptyMedia variant="icon">
                          <InboxIcon />
                        </EmptyMedia>
                        <EmptyTitle>Belum ada penerimaan.</EmptyTitle>
                        <EmptyDescription>
                          Tambahkan penerimaan pertama lewat tombol di atas.
                        </EmptyDescription>
                      </EmptyHeader>
                    </Empty>
                  </TableCell>
                </TableRow>
              )}

              {state.status === "ready" &&
                state.rows.map((row) => <ReceptionTableRow key={row.id} row={row} />)}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

function ReceptionTableRow({ row }: { row: ReceptionRow }) {
  return (
    <TableRow>
      <TableCell>
        <a
          className="font-medium text-primary underline-offset-4 hover:underline"
          href={`/receptions/${row.id}/edit`}
        >
          {row.reference_no}
        </a>
      </TableCell>
      <TableCell>{row.supplier_name ?? "-"}</TableCell>
      <TableCell>{formatDateTime(row.received_at)}</TableCell>
      <TableCell>
        <PersonCell name={row.created_by_name} at={row.created_at} />
      </TableCell>
      <TableCell>
        {row.updated_by === null ? (
          <span className="text-muted-foreground">belum pernah diubah</span>
        ) : (
          <PersonCell name={row.updated_by_name} at={row.updated_at} />
        )}
      </TableCell>
      <TableCell className="text-right">
        {row.can_update && (
          <Button asChild variant="outline" size="sm">
            <a href={`/receptions/${row.id}/edit`}>Ubah</a>
          </Button>
        )}
      </TableCell>
    </TableRow>
  )
}

function PersonCell({
  name,
  at,
}: {
  name: string | null
  at: string | null
}) {
  return (
    <>
      <div>{name ?? "-"}</div>
      <div className="text-xs text-muted-foreground">
        {at ? formatDateTime(at) : "belum pernah diubah"}
      </div>
    </>
  )
}
