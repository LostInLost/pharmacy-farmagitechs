import { CircleAlertIcon, CircleCheckIcon } from "lucide-react"

import { cn } from "@/foundations/ui/cn"

type Variant = "error" | "success"

/**
 * Alert inline untuk pesan error/sukses di dalam halaman.
 * Padanan `Farmasi.ui.error()/success()` di frontend CI4: menampilkan daftar
 * pesan (mis. `errors[]` dari validasi server) tanpa toast yang mudah terlewat.
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
    <div
      role="alert"
      className={cn(
        "flex gap-2 rounded-lg border p-3 text-sm",
        variant === "error"
          ? "border-destructive/30 bg-destructive/10 text-destructive"
          : "border-primary/30 bg-primary/10 text-foreground",
        className
      )}
    >
      <Icon className="mt-0.5 size-4 shrink-0" />
      <div className="flex flex-col gap-1">
        {messages.map((message, index) => (
          <p key={index}>{message}</p>
        ))}
      </div>
    </div>
  )
}
