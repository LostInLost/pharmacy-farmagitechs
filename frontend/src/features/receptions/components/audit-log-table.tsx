import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
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
        <div className="overflow-x-auto">
          <table className="w-full caption-bottom text-sm">
            <thead className="border-b [&_th]:h-10 [&_th]:px-4 [&_th]:text-left [&_th]:align-middle [&_th]:font-medium">
              <tr>
                <th>Waktu</th>
                <th>Aksi</th>
                <th>Petugas</th>
                <th>Perubahan</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id} className="border-b last:border-0">
                  <td className="p-4 align-middle whitespace-nowrap">
                    {formatDateTime(log.created_at)}
                  </td>
                  <td className="p-4 align-middle">{actionLabel(log.action)}</td>
                  <td className="p-4 align-middle">{log.actor_name ?? "-"}</td>
                  <td className="p-4 align-middle">
                    <ChangeDetails log={log} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  )
}

function ChangeDetails({ log }: { log: AuditLog }) {
  if (log.data_before === null && log.data_after === null) {
    return <span className="text-muted-foreground">-</span>
  }

  return (
    <details>
      <summary className="cursor-pointer">{changeSummary(log)}</summary>
      <pre className="mt-2 max-w-md overflow-x-auto rounded-md bg-muted p-2 text-xs">
        Sebelum: {JSON.stringify(log.data_before, null, 2)}
        {"\n\n"}
        Sesudah: {JSON.stringify(log.data_after, null, 2)}
      </pre>
    </details>
  )
}
