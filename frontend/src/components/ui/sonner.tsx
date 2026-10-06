import { Toaster as Sonner, type ToasterProps } from "sonner"
import { CircleCheckIcon, InfoIcon, TriangleAlertIcon, OctagonXIcon, Loader2Icon } from "lucide-react"

/**
 * Versi Astro: block shadcn asli memakai `next-themes` untuk membaca tema.
 * Di Astro tidak ada provider Next, jadi tema dibaca dari kelas `dark`
 * pada <html> dengan fallback "system".
 */
function currentTheme(): ToasterProps["theme"] {
  if (typeof document === "undefined") return "system"
  return document.documentElement.classList.contains("dark") ? "dark" : "light"
}

const Toaster = ({ ...props }: ToasterProps) => {
  return (
    <Sonner
      theme={currentTheme()}
      className="toaster group"
      icons={{
        success: (
          <CircleCheckIcon className="size-4" />
        ),
        info: (
          <InfoIcon className="size-4" />
        ),
        warning: (
          <TriangleAlertIcon className="size-4" />
        ),
        error: (
          <OctagonXIcon className="size-4" />
        ),
        loading: (
          <Loader2Icon className="size-4 animate-spin" />
        ),
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
        } as React.CSSProperties
      }
      toastOptions={{
        classNames: {
          toast: "cn-toast",
        },
      }}
      {...props}
    />
  )
}

export { Toaster }
