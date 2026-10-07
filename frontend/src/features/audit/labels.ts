/**
 * Label aksi audit untuk sisi Astro.
 *
 * Nilai `audit_logs.action` adalah kunci i18n itu sendiri
 * (`Audit.receptions.action.create`), jadi label tinggal dipetakan dari kunci
 * yang dikirim API — tidak ada token perantara yang perlu diterjemahkan.
 *
 * Kunci sengaja ditulis lengkap (bukan `create`/`update` saja) supaya
 * sama persis dengan nilai di database dan dengan berkas
 * `app/Language/{id,en}/Audit.php` di backend; berkas ini adalah padanan
 * TypeScript-nya karena Astro belum memakai sistem terjemahan.
 */
const ACTION_LABELS: Record<string, string> = {
  "Audit.receptions.action.create": "Menambahkan data penerimaan",
  "Audit.receptions.action.update": "Mengubah data penerimaan",
  "Audit.receptions.action.delete": "Menghapus data penerimaan",
}

/**
 * Kunci tanpa label dikembalikan apa adanya, sehingga entitas yang labelnya
 * belum dibuat tetap terbaca (mis. `Audit.medicines.action.create` atau baris
 * lama bertoken) alih-alih hilang dari tampilan.
 */
export function auditActionLabel(action: string): string {
  return ACTION_LABELS[action] ?? action
}
