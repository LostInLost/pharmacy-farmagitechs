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
import { hasPermission, PERMISSIONS } from "@/features/auth"
import { listReceptions } from "@/features/receptions/api"
import type { ReceptionRow } from "@/features/receptions/schemas"
import { CirclePlusIcon, InboxIcon } from "lucide-react"
import { ReceptionFormSheet } from "./reception-form-sheet"
import { ReceptionViewSheet } from "./reception-view-sheet"

/**
 * Sheet yang sedang terbuka. Hanya satu sheet hidup pada satu waktu; berpindah
 * mode cukup mengganti nilai ini (`view` → `edit` dari dalam sheet detail).
 */
export type ReceptionSheetState =
  | { mode: "create" }
  | { mode: "view"; id: number }
  | { mode: "edit"; id: number }

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

type Props = {
  /** Sheet awal dari query string (`?new=1` / `?view=<id>` / `?edit=<id>`). */
  initialSheet?: ReceptionSheetState | null
  /** Permission user dari `Astro.locals.user` (`GET /api/me`). */
  permissions?: string[]
}

/**
 * Halaman daftar penerimaan sekaligus orkestrator sheet tambah/detail/ubah.
 *
 * Semua alur dokumen terjadi di atas daftar ini: membuka sheet menyinkronkan
 * URL lewat `history.replaceState` (tanpa entri baru), sehingga tautan
 * `?view=<id>` tetap bisa dibagikan dan dimuat langsung lewat SSR. Konsekuensi
 * yang disadari: tombol Back browser keluar dari halaman, bukan menutup sheet.
 */
export function ReceptionsView({
  initialSheet = null,
  permissions = [],
}: Props) {
  const [state, setState] = React.useState<State>({ status: "loading" })
  const [sheet, setSheet] = React.useState<ReceptionSheetState | null>(
    initialSheet
  )
  const [notice, setNotice] = React.useState<string | null>(null)
  const [reloadToken, setReloadToken] = React.useState(0)

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
  }, [reloadToken])

  // Sinkronkan URL dengan sheet yang terbuka. `replaceState` dipilih agar
  // membuka/menutup sheet tidak menumpuk riwayat.
  React.useEffect(() => {
    const url = new URL(window.location.href)

    url.searchParams.delete("new")
    url.searchParams.delete("view")
    url.searchParams.delete("edit")

    if (sheet !== null) {
      if (sheet.mode === "create") url.searchParams.set("new", "1")
      if (sheet.mode === "view") url.searchParams.set("view", String(sheet.id))
      if (sheet.mode === "edit") url.searchParams.set("edit", String(sheet.id))
    }

    window.history.replaceState(null, "", url)
  }, [sheet])

  function closeSheet() {
    setSheet(null)
  }

  function reloadRows() {
    setReloadToken((token) => token + 1)
  }

  function openCreate() {
    setNotice(null)
    setSheet({ mode: "create" })
  }

  function openView(id: number) {
    setNotice(null)
    setSheet({ mode: "view", id })
  }

  function openEdit(id: number) {
    setNotice(null)
    setSheet({ mode: "edit", id })
  }

  const rows = state.status === "ready" ? state.rows : []
  const canCreate = hasPermission(permissions, PERMISSIONS.receiptCreate)

  return (
    <div className="flex flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="font-heading text-lg font-medium">Daftar Penerimaan</h2>
          <p className="text-sm text-muted-foreground">
            Barang masuk beserta batch dan masa kedaluwarsanya. Klik reference
            untuk melihat detailnya.
          </p>
        </div>
        {canCreate && (
          <Button type="button" onClick={openCreate}>
            <CirclePlusIcon data-icon="inline-start" />
            Tambah Penerimaan
          </Button>
        )}
      </div>

      {notice !== null && <Feedback variant="success" messages={[notice]} />}

      {state.status === "error" && (
        <Feedback variant="error" messages={[state.message]} />
      )}

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

              {state.status === "ready" && rows.length === 0 && (
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
                rows.map((row) => (
                  <ReceptionTableRow
                    key={row.id}
                    row={row}
                    onView={openView}
                    onEdit={openEdit}
                  />
                ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {sheet !== null && sheet.mode === "create" && (
        <ReceptionFormSheet
          key="create"
          receptionId={null}
          open
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          onSaved={() => {
            reloadRows()
            setNotice("Penerimaan dibuat.")
          }}
        />
      )}

      {sheet !== null && sheet.mode === "edit" && (
        <ReceptionFormSheet
          // `key` per id: berpindah penerimaan me-remount form dengan state
          // segar, jadi isian percobaan sebelumnya tidak terbawa.
          key={`edit-${sheet.id}`}
          receptionId={sheet.id}
          open
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          onSaved={() => {
            reloadRows()
            setNotice("Penerimaan diperbarui.")
          }}
          onViewDetail={openView}
        />
      )}

      {sheet !== null && sheet.mode === "view" && (
        <ReceptionViewSheet
          key={`view-${sheet.id}`}
          receptionId={sheet.id}
          open
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          canUpdate={
            rows.find((row) => row.id === sheet.id)?.can_update ?? false
          }
          onEdit={openEdit}
        />
      )}
    </div>
  )
}

function ReceptionTableRow({
  row,
  onView,
  onEdit,
}: {
  row: ReceptionRow
  onView: (id: number) => void
  onEdit: (id: number) => void
}) {
  return (
    <TableRow>
      <TableCell>
        {/*
          Anchor asli (bukan button) supaya bisa dibuka di tab baru dan tetap
          bekerja tanpa JavaScript; klik biasa dicegat jadi sheet.
        */}
        <a
          className="font-medium text-primary underline-offset-4 hover:underline"
          href={`/receptions?view=${row.id}`}
          onClick={(event) => {
            event.preventDefault()
            onView(row.id)
          }}
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
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => onEdit(row.id)}
          >
            Ubah
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
