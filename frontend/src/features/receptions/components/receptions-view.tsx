import * as React from "react"
import { toast } from "sonner"

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
 *
 * Penutupan sheet memakai dua state terpisah (`sheet` + `sheetOpen`) supaya
 * Radix sempat memainkan animasi keluar: komponennya tetap ter-mount saat
 * `open` menjadi `false`, dan Presence yang melepasnya setelah animasi usai.
 * `sheetSeq` menggantikan peran `key` per-id agar pembukaan berikutnya tetap
 * me-remount komponen — tanpa itu, komponen yang sempat tertahan akan
 * mewariskan isian percobaan sebelumnya.
 */
export function ReceptionsView({
  initialSheet = null,
  permissions = [],
}: Props) {
  const [state, setState] = React.useState<State>({ status: "loading" })
  const [sheet, setSheet] = React.useState<ReceptionSheetState | null>(
    initialSheet
  )
  const [sheetOpen, setSheetOpen] = React.useState(initialSheet !== null)
  const [sheetSeq, setSheetSeq] = React.useState(0)
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
  // membuka/menutup sheet tidak menumpuk riwayat. Kuncinya `sheetOpen`, bukan
  // `sheet`: saat menutup, `sheet` sengaja masih terisi (lihat `closeSheet`)
  // sehingga URL harus ikut bersih begitu animasi keluar dimulai.
  React.useEffect(() => {
    const url = new URL(window.location.href)

    url.searchParams.delete("new")
    url.searchParams.delete("view")
    url.searchParams.delete("edit")

    if (sheet !== null && sheetOpen) {
      if (sheet.mode === "create") url.searchParams.set("new", "1")
      if (sheet.mode === "view") url.searchParams.set("view", String(sheet.id))
      if (sheet.mode === "edit") url.searchParams.set("edit", String(sheet.id))
    }

    window.history.replaceState(null, "", url)
  }, [sheet, sheetOpen])

  function closeSheet() {
    // `sheet` sengaja tidak dikosongkan di sini: Radix `Presence` butuh
    // komponennya tetap ter-mount untuk memainkan animasi keluar, lalu ia
    // sendiri yang melepas isi portal setelah animasi usai.
    setSheetOpen(false)
  }

  function reloadRows() {
    setReloadToken((token) => token + 1)
  }

  /** Buka sheet: remount (`sheetSeq`) + tandai terbuka. */
  function openSheet(next: ReceptionSheetState) {
    setSheet(next)
    setSheetOpen(true)
    setSheetSeq((seq) => seq + 1)
  }

  function openCreate() {
    openSheet({ mode: "create" })
  }

  function openView(id: number) {
    openSheet({ mode: "view", id })
  }

  function openEdit(id: number) {
    openSheet({ mode: "edit", id })
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

      {state.status === "error" && (
        <Feedback messages={[state.message]} />
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

      {/*
        Sheet sengaja dibiarkan ter-mount saat ditutup: `open` yang menjadi
        `false` memicu animasi keluar Radix, dan `sheet` baru dikosongkan
        lewat `key`/nilai baru saat sheet berikutnya dibuka.
      */}
      {sheet !== null && sheet.mode === "create" && (
        <ReceptionFormSheet
          key={`create-${sheetSeq}`}
          receptionId={null}
          open={sheetOpen}
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          onSaved={() => {
            reloadRows()
            toast.success("Penerimaan dibuat.")
          }}
        />
      )}

      {sheet !== null && sheet.mode === "edit" && (
        <ReceptionFormSheet
          // `key` per pembukaan: form di-remount dengan state segar, jadi
          // isian percobaan sebelumnya tidak terbawa.
          key={`edit-${sheet.id}-${sheetSeq}`}
          receptionId={sheet.id}
          open={sheetOpen}
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          onSaved={() => {
            reloadRows()
            toast.success("Penerimaan diperbarui.")
          }}
          onViewDetail={openView}
        />
      )}

      {sheet !== null && sheet.mode === "view" && (
        <ReceptionViewSheet
          key={`view-${sheet.id}-${sheetSeq}`}
          receptionId={sheet.id}
          open={sheetOpen}
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
