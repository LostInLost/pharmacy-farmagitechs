import { CircleAlertIcon, CircleCheckIcon } from "lucide-react"

import { Alert, AlertDescription } from "@/components/ui/alert"
import { cn } from "@/foundations/ui/cn"

type Variant = "error" | "success"

/**
 * Alert inline untuk pesan error/sukses di dalam halaman.
 * Padanan `Farmasi.ui.error()/success()` di frontend CI4: menampilkan daftar
 * pesan (mis. `errors[]` dari validasi server) tanpa toast yang mudah terlewat.
 *
 * Sekarang dibangun di atas block shadcn `Alert` — yang sudah membawa
 * `role="alert"` sendiri, sehingga pembaca `[role="alert"]` di skrip verifikasi
 * tetap menemukan pesan tanpa atribut manual.
 */
export function Feedback({
  variant,
  messages,
  className,
}: {
  variant: Variant
  messages: string[]
  className?: string
}) {
  if (messages.length === 0) return null

  const Icon = variant === "error" ? CircleAlertIcon : CircleCheckIcon

  return (
    <Alert
      variant={variant === "error" ? "destructive" : "default"}
      className={cn(variant === "success" && "border-primary/30 bg-primary/10", className)}
    >
      <Icon />
      <AlertDescription className="flex flex-col gap-1">
        {messages.map((message, index) => (
          <p key={index}>{message}</p>
        ))}
      </AlertDescription>
    </Alert>
  )
}
