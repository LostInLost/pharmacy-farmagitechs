/**
 * Backend mengirim datetime sebagai string `YYYY-MM-DD HH:MM:SS` (waktu
 * lokal `Asia/Jakarta`, tanpa zona). Menampilkannya apa adanya membuat
 * nilainya konsisten dengan halaman CI4 dan menghindari pergeseran jam
 * akibat `new Date()` yang menafsirkannya sebagai waktu lokal browser.
 */

/** `2026-10-07 09:30:00` → `07 Okt 2026 09:30` */
export function formatDateTime(value: string | null): string {
  if (!value) return ""

  const match = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value)

  if (!match) return value

  const [, year, month, day, hour, minute] = match

  return `${day} ${MONTHS[Number(month) - 1] ?? month} ${year} ${hour}:${minute}`
}

/** `2029-01-31` → `31 Jan 2029` */
export function formatDate(value: string | null): string {
  if (!value) return ""

  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)

  if (!match) return value

  const [, year, month, day] = match

  return `${day} ${MONTHS[Number(month) - 1] ?? month} ${year}`
}

/** `2026-10-07 09:30:00` → `2026-10-07T09:30` untuk `<input type="datetime-local">`. */
export function toDatetimeLocal(value: string | null): string {
  if (!value) return ""

  return value.slice(0, 16).replace(" ", "T")
}

/** Tanggal hari ini menurut jam browser, format `YYYY-MM-DD`. */
export function todayIso(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, "0")
  const day = String(now.getDate()).padStart(2, "0")

  return `${now.getFullYear()}-${month}-${day}`
}

const MONTHS = [
  "Jan",
  "Feb",
  "Mar",
  "Apr",
  "Mei",
  "Jun",
  "Jul",
  "Agu",
  "Sep",
  "Okt",
  "Nov",
  "Des",
]
