import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { formatDateTime } from "@/foundations/format"
import type { AuditLog } from "@/features/receptions/schemas"

/**
 * Token kanonik di database → label tampilan. Token tak dikenal
 * dikembalikan apa adanya agar log lama tidak hilang dari tampilan.
 */
const ACTION_LABELS: Record<string, string> = {
  CREATE: "Menambah data penerimaan",
  UPDATE: "Mengubah data penerimaan",
  DELETE: "Menghapus data penerimaan",
}

function actionLabel(action: string): string {
  return ACTION_LABELS[action.toUpperCase()] ?? action
}

function changeSummary(log: AuditLog): string {
  if (log.action === "CREATE") return "Penerimaan dibuat"

  const before = JSON.stringify(log.data_before)
  const after = JSON.stringify(log.data_after)

  return before === after ? "Tidak ada perubahan" : "Isi penerimaan berubah"
}

export function AuditLogTable({ logs }: { logs: AuditLog[] }) {
  if (logs.length === 0) return null

  return (
    <Card>
      <CardHeader>
        <CardTitle>Riwayat Aksi</CardTitle>
      </CardHeader>
      <CardContent className="px-0">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Waktu</TableHead>
              <TableHead>Aksi</TableHead>
              <TableHead>Petugas</TableHead>
              <TableHead>Perubahan</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {logs.map((log) => (
              <TableRow key={log.id}>
                <TableCell className="whitespace-nowrap">
                  {formatDateTime(log.created_at)}
                </TableCell>
                <TableCell>{actionLabel(log.action)}</TableCell>
                <TableCell>{log.actor_name ?? "-"}</TableCell>
                <TableCell>
                  <ChangeDetails log={log} />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}

function ChangeDetails({ log }: { log: AuditLog }) {
  if (log.data_before === null && log.data_after === null) {
    return <span className="text-muted-foreground">-</span>
  }

  return (
    <Collapsible>
      <CollapsibleTrigger className="cursor-pointer text-left">
        {changeSummary(log)}
      </CollapsibleTrigger>
      <CollapsibleContent>
        <pre className="mt-2 max-w-md overflow-x-auto rounded-md bg-muted p-2 text-xs">
          Sebelum: {JSON.stringify(log.data_before, null, 2)}
          {"\n\n"}
          Sesudah: {JSON.stringify(log.data_after, null, 2)}
        </pre>
      </CollapsibleContent>
    </Collapsible>
  )
}
