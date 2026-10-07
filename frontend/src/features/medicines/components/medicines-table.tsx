import * as React from "react"
import { PencilIcon, PillIcon, PlusIcon } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty"
import { Field, FieldLabel } from "@/components/ui/field"
import { Feedback } from "@/components/feedback"
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
import { hasPermission, PERMISSIONS } from "@/features/auth"
import { listMedicines, type MedicineList } from "@/features/medicines/api"
import type {
  MedicineRow,
  MedicineStatus,
} from "@/features/medicines/schemas"
import { MedicineFormSheet } from "./medicine-form-sheet"
import { MedicineViewSheet } from "./medicine-view-sheet"

/**
 * Sheet yang sedang terbuka. Hanya satu sheet hidup pada satu waktu; berpindah
 * mode cukup mengganti nilai ini (`view` → `edit` dari dalam sheet detail).
 */
export type MedicineSheetState =
  | { mode: "create" }
  | { mode: "view"; id: number }
  | { mode: "edit"; id: number }

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; data: MedicineList }

const COLUMNS = ["Kode", "Nama Obat", "Satuan", "Status", "Aksi"]

/** Filter yang sudah "dipakai" — berbeda dari nilai input yang sedang diketik. */
type Filter = { q: string; status: MedicineStatus }

type Props = {
  /** Sheet awal dari query string (`?new=1` / `?view=<id>` / `?edit=<id>`). */
  initialSheet?: MedicineSheetState | null
  /** Permission user dari `Astro.locals.user` (`GET /api/me`). */
  permissions?: string[]
}

/**
 * Halaman daftar master obat sekaligus orkestrator sheet tambah/detail/ubah.
 *
 * Polanya sama dengan penerimaan: membuka sheet menyinkronkan URL lewat
 * `history.replaceState` (tanpa entri baru), sehingga tautan `?view=<id>`
 * tetap bisa dibagikan dan dimuat langsung lewat SSR. Konsekuensi yang
 * disadari: tombol Back browser keluar dari halaman, bukan menutup sheet.
 *
 * Penutupan sheet memakai dua state terpisah (`sheet` + `sheetOpen`) supaya
 * Radix sempat memainkan animasi keluar: komponennya tetap ter-mount saat
 * `open` menjadi `false`, dan Presence yang melepasnya setelah animasi usai.
 * `sheetSeq` menggantikan peran `key` per-id agar pembukaan berikutnya tetap
 * me-remount komponen — tanpa itu, komponen yang sempat tertahan akan
 * mewariskan isian percobaan sebelumnya.
 */
export function MedicinesTable({ initialSheet = null, permissions = [] }: Props) {
  const [state, setState] = React.useState<State>({ status: "loading" })
  const [search, setSearch] = React.useState("")
  const [status, setStatus] = React.useState<MedicineStatus>("all")
  const [filter, setFilter] = React.useState<Filter>({ q: "", status: "all" })
  const [sheet, setSheet] = React.useState<MedicineSheetState | null>(
    initialSheet
  )
  const [sheetOpen, setSheetOpen] = React.useState(initialSheet !== null)
  const [sheetSeq, setSheetSeq] = React.useState(0)
  const [notice, setNotice] = React.useState<string | null>(null)
  const [reloadToken, setReloadToken] = React.useState(0)

  // Pemuatan awal mengikuti pola tabel penerimaan/laporan stok: status awal
  // sudah "loading", dan setState hanya terjadi di dalam callback request —
  // bukan di badan efek, yang memicu cascading render. Efek ikut berjalan
  // ulang saat filter berubah atau daftar perlu disegarkan setelah simpan.
  React.useEffect(() => {
    let cancelled = false

    listMedicines(filter.q, filter.status).then((result) => {
      if (cancelled) return

      setState(
        result.ok
          ? { status: "ready", data: result.data }
          : { status: "error", message: result.message }
      )
    })

    return () => {
      cancelled = true
    }
  }, [filter, reloadToken])

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

  function onFilterSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setFilter({ q: search, status })
  }

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
  function openSheet(next: MedicineSheetState) {
    setNotice(null)
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

  const rows = state.status === "ready" ? state.data.rows : []
  const canWrite = state.status === "ready" && state.data.canWrite
  const canCreate =
    canWrite && hasPermission(permissions, PERMISSIONS.medicineWrite)

  return (
    <div className="flex flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="font-heading text-lg font-medium">Master Obat</h2>
          <p className="text-sm text-muted-foreground">
            Katalog obat beserta satuannya. Obat nonaktif tetap terdaftar agar
            riwayat stok dan penerimaannya utuh. Klik kode untuk melihat
            detailnya.
          </p>
        </div>
        {canCreate && (
          <Button type="button" onClick={openCreate}>
            <PlusIcon data-icon="inline-start" />
            Tambah Obat
          </Button>
        )}
      </div>

      {notice !== null && <Feedback variant="success" messages={[notice]} />}

      <Card>
        <CardContent>
          <form
            onSubmit={onFilterSubmit}
            className="flex flex-wrap items-end gap-3"
          >
            <Field className="w-64">
              <FieldLabel htmlFor="medicine_search">Cari</FieldLabel>
              <Input
                id="medicine_search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Kode atau nama obat"
              />
            </Field>

            <Field className="w-48">
              <FieldLabel htmlFor="medicine_filter">Status</FieldLabel>
              <Select
                value={status}
                onValueChange={(value) => setStatus(value as MedicineStatus)}
              >
                <SelectTrigger id="medicine_filter" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="all">Semua status</SelectItem>
                    <SelectItem value="active">Hanya aktif</SelectItem>
                    <SelectItem value="inactive">Hanya nonaktif</SelectItem>
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
                    key={column}
                    className={column === "Aksi" ? "text-right" : undefined}
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
                  <TableCell colSpan={COLUMNS.length} className="p-0">
                    <Empty className="rounded-none border-0 py-10">
                      <EmptyHeader>
                        <EmptyMedia variant="icon">
                          <PillIcon />
                        </EmptyMedia>
                        <EmptyTitle>Belum ada obat yang cocok.</EmptyTitle>
                        <EmptyDescription>
                          Ubah kata kunci atau status, lalu tampilkan lagi.
                        </EmptyDescription>
                      </EmptyHeader>
                    </Empty>
                  </TableCell>
                </TableRow>
              )}

              {state.status === "ready" &&
                rows.map((row) => (
                  <MedicineTableRow
                    key={row.id}
                    row={row}
                    canWrite={canWrite}
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
        <MedicineFormSheet
          key={`create-${sheetSeq}`}
          medicineId={null}
          open={sheetOpen}
          canWrite={canWrite}
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          onSaved={() => {
            reloadRows()
            setNotice("Obat dibuat.")
          }}
        />
      )}

      {sheet !== null && sheet.mode === "edit" && (
        <MedicineFormSheet
          // `key` per pembukaan: form di-remount dengan state segar, jadi
          // isian percobaan sebelumnya tidak terbawa.
          key={`edit-${sheet.id}-${sheetSeq}`}
          medicineId={sheet.id}
          open={sheetOpen}
          canWrite={canWrite}
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          onSaved={() => {
            reloadRows()
            setNotice("Obat diperbarui.")
          }}
        />
      )}

      {sheet !== null && sheet.mode === "view" && (
        <MedicineViewSheet
          key={`view-${sheet.id}-${sheetSeq}`}
          medicineId={sheet.id}
          open={sheetOpen}
          canWrite={canWrite}
          onOpenChange={(open) => {
            if (!open) closeSheet()
          }}
          onEdit={openEdit}
        />
      )}
    </div>
  )
}

function MedicineTableRow({
  row,
  canWrite,
  onView,
  onEdit,
}: {
  row: MedicineRow
  canWrite: boolean
  onView: (id: number) => void
  onEdit: (id: number) => void
}) {
  return (
    <TableRow>
      <TableCell className="font-medium">
        {/*
          Anchor asli (bukan button) supaya bisa dibuka di tab baru dan tetap
          bekerja tanpa JavaScript; klik biasa dicegat jadi sheet.
        */}
        <a
          className="text-primary underline-offset-4 hover:underline"
          href={`/medicines?view=${row.id}`}
          onClick={(event) => {
            event.preventDefault()
            onView(row.id)
          }}
        >
          {row.code}
        </a>
      </TableCell>
      <TableCell className="whitespace-normal">{row.name}</TableCell>
      <TableCell>{row.unit}</TableCell>
      <TableCell>
        <Badge variant={row.is_active ? "secondary" : "outline"}>
          {row.is_active ? "aktif" : "nonaktif"}
        </Badge>
      </TableCell>
      <TableCell className="text-right">
        {canWrite && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => onEdit(row.id)}
          >
            <PencilIcon data-icon="inline-start" />
            Ubah
          </Button>
        )}
      </TableCell>
    </TableRow>
  )
}
