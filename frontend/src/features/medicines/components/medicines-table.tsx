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
import { listMedicines, type MedicineList } from "@/features/medicines/api"
import type {
  MedicineRow,
  MedicineStatus,
} from "@/features/medicines/schemas"
import { MedicineFormDialog } from "./medicine-form-dialog"

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; data: MedicineList }

const COLUMNS = ["Kode", "Nama Obat", "Satuan", "Status", "Aksi"]

export function MedicinesTable() {
  const [state, setState] = React.useState<State>({ status: "loading" })
  const [search, setSearch] = React.useState("")
  const [status, setStatus] = React.useState<MedicineStatus>("all")

  // Dialog: `null` = tertutup, `{ medicine }` = terbuka (medicine null = tambah).
  const [dialog, setDialog] = React.useState<
    { medicine: MedicineRow | null } | null
  >(null)

  /**
   * `q` dan `status` adalah keadaan filter yang sudah "dipakai", berbeda dari
   * nilai input: form boleh diketik tanpa langsung memicu request.
   */
  const load = React.useCallback((q: string, nextStatus: MedicineStatus) => {
    setState({ status: "loading" })

    listMedicines(q, nextStatus).then((result) => {
      setState(
        result.ok
          ? { status: "ready", data: result.data }
          : { status: "error", message: result.message }
      )
    })
  }, [])

  // Pemuatan awal mengikuti pola tabel penerimaan/laporan stok: status awal
  // sudah "loading", dan setState hanya terjadi di dalam callback request —
  // bukan di badan efek, yang memicu cascading render.
  React.useEffect(() => {
    let cancelled = false

    listMedicines("", "all").then((result) => {
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
  }, [])

  function onFilterSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    load(search, status)
  }

  const rows = state.status === "ready" ? state.data.rows : []
  const canWrite = state.status === "ready" && state.data.canWrite

  return (
    <div className="flex flex-col gap-4 px-4 lg:px-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="font-heading text-lg font-medium">Master Obat</h2>
          <p className="text-sm text-muted-foreground">
            Katalog obat beserta satuannya. Obat nonaktif tetap terdaftar agar
            riwayat stok dan penerimaannya utuh.
          </p>
        </div>
        {canWrite && (
          <Button onClick={() => setDialog({ medicine: null })}>
            <PlusIcon data-icon="inline-start" />
            Tambah Obat
          </Button>
        )}
      </div>

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
                  <TableRow key={row.id}>
                    <TableCell className="font-medium">{row.code}</TableCell>
                    <TableCell className="whitespace-normal">
                      {row.name}
                    </TableCell>
                    <TableCell>{row.unit}</TableCell>
                    <TableCell>
                      <Badge variant={row.is_active ? "secondary" : "outline"}>
                        {row.is_active ? "aktif" : "nonaktif"}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      {canWrite && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setDialog({ medicine: row })}
                        >
                          <PencilIcon data-icon="inline-start" />
                          Ubah
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {dialog !== null && (
        <MedicineFormDialog
          // `key` membuat form ter-remount saat berpindah baris, jadi isian
          // percobaan sebelumnya tidak terbawa tanpa reset lewat efek.
          key={dialog.medicine?.id ?? "new"}
          medicine={dialog.medicine}
          open
          onOpenChange={(open) => {
            if (!open) setDialog(null)
          }}
          onSaved={() => {
            // Muat ulang memakai filter yang sedang aktif agar baris baru
            // langsung tampak pada posisi yang benar.
            load(search, status)
          }}
        />
      )}
    </div>
  )
}
