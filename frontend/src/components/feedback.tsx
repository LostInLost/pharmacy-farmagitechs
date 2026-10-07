import { CircleAlertIcon } from "lucide-react"

import { Alert, AlertDescription } from "@/components/ui/alert"

/**
 * Alert inline untuk pesan error di dalam halaman atau form.
 *
 * Pesan sukses tidak lewat komponen ini — pakai `toast.success` dari sonner
 * (host `Toaster` ada di `app-shell.tsx`). Error tetap inline karena daftar
 * pesan (mis. `errors[]` dari validasi server) harus menempel pada form yang
 * bermasalah, bukan notifikasi yang mudah terlewat.
 *
 * Dibangun di atas block shadcn `Alert` — yang sudah membawa `role="alert"`
 * sendiri, sehingga pembaca `[role="alert"]` di skrip verifikasi tetap
 * menemukan pesan tanpa atribut manual.
 */
export function Feedback({
  messages,
  className,
}: {
  messages: string[]
  className?: string
}) {
  if (messages.length === 0) return null

  return (
    <Alert variant="destructive" className={className}>
      <CircleAlertIcon />
      <AlertDescription className="flex flex-col gap-1">
        {messages.map((message, index) => (
          <p key={index}>{message}</p>
        ))}
      </AlertDescription>
    </Alert>
  )
}
