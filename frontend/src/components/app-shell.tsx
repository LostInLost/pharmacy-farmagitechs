import * as React from "react"

import { AppSidebar } from "@/components/app-sidebar"
import { SiteHeader } from "@/components/site-header"
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"
import { Toaster } from "@/components/ui/sonner"
import { TooltipProvider } from "@/components/ui/tooltip"
import { logout } from "@/features/auth/api"
import type { AuthUser } from "@/features/auth/schemas"
import { clearUserSession, ensureSession } from "@/features/auth/session"

type Props = {
  /** Dari `Astro.locals.user` (middleware SSR sudah memanggil `GET /api/me`). */
  user: AuthUser | null
  /** `Astro.url.pathname` — dipakai untuk menandai menu yang aktif. */
  pathname: string
  title: string
  children: React.ReactNode
}

/**
 * Chrome aplikasi (sidebar + header + toaster) untuk semua halaman terproteksi.
 *
 * Sesi sudah diverifikasi middleware saat SSR, jadi `user` biasanya terisi dan
 * tidak ada kedipan "memuat". Bila `user` null (mis. backend sempat mati saat
 * SSR), `ensureSession()` dipakai sebagai jaring pengaman di klien; fallback
 * sintetis `id: 0` tidak dipakai lagi di sini karena middleware sudah menjadi
 * sumber utama.
 */
export function AppShell({ user, pathname, title, children }: Props) {
  const [session, setSession] = React.useState<AuthUser | null>(user)

  React.useEffect(() => {
    if (user !== null) return

    let cancelled = false

    ensureSession().then((resolved) => {
      if (cancelled) return

      if (resolved) {
        setSession(resolved)
      } else {
        window.location.assign("/login")
      }
    })

    return () => {
      cancelled = true
    }
  }, [user])

  const handleLogout = React.useCallback(async () => {
    await logout()
    clearUserSession()
    window.location.assign("/login")
  }, [])

  if (session === null) {
    return (
      <div className="grid min-h-svh place-items-center text-sm text-muted-foreground">
        Mengalihkan ke halaman masuk...
      </div>
    )
  }

  return (
    <TooltipProvider>
      <SidebarProvider
        style={
          {
            "--sidebar-width": "calc(var(--spacing) * 72)",
            "--header-height": "calc(var(--spacing) * 12)",
          } as React.CSSProperties
        }
      >
        <AppSidebar user={session} pathname={pathname} onLogout={handleLogout} />
        <SidebarInset>
          <SiteHeader title={title} />
          <div className="flex flex-1 flex-col">
            <div className="@container/main flex flex-1 flex-col gap-2">
              <div className="flex flex-col gap-4 py-4 md:gap-6 md:py-6">
                {children}
              </div>
            </div>
          </div>
        </SidebarInset>
      </SidebarProvider>
      <Toaster position="top-right" />
    </TooltipProvider>
  )
}
