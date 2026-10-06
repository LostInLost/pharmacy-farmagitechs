import * as React from "react"

import { AppSidebar } from "@/components/app-sidebar"
import { ChartAreaInteractive } from "@/components/chart-area-interactive"
import { DataTable } from "@/components/data-table"
import { SectionCards } from "@/components/section-cards"
import { SiteHeader } from "@/components/site-header"
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"
import { Toaster } from "@/components/ui/sonner"
import { TooltipProvider } from "@/components/ui/tooltip"
import { logout } from "@/lib/auth"
import { clearUserSession, ensureSession, type SessionUser } from "@/lib/session"
import dashboardData from "@/data/dashboard.json"

type Status = "loading" | "ready" | "anonymous"

export function DashboardShell() {
  const [status, setStatus] = React.useState<Status>("loading")
  const [user, setUser] = React.useState<SessionUser | null>(null)

  React.useEffect(() => {
    let cancelled = false

    ensureSession().then((session) => {
      if (cancelled) return

      if (session) {
        setUser(session)
        setStatus("ready")
      } else {
        setStatus("anonymous")
        window.location.assign("/login")
      }
    })

    return () => {
      cancelled = true
    }
  }, [])

  const handleLogout = React.useCallback(async () => {
    await logout()
    clearUserSession()
    window.location.assign("/login")
  }, [])

  if (status === "loading") {
    return (
      <div className="grid min-h-svh place-items-center text-sm text-muted-foreground">
        Memuat dashboard...
      </div>
    )
  }

  if (status === "anonymous" || !user) {
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
        <AppSidebar user={user} onLogout={handleLogout} />
        <SidebarInset>
          <SiteHeader title="Dashboard" />
          <div className="flex flex-1 flex-col">
            <div className="@container/main flex flex-1 flex-col gap-2">
              <div className="flex flex-col gap-4 py-4 md:gap-6 md:py-6">
                <SectionCards />
                <div className="px-4 lg:px-6">
                  <ChartAreaInteractive />
                </div>
                <DataTable data={dashboardData} />
              </div>
            </div>
          </div>
        </SidebarInset>
      </SidebarProvider>
      <Toaster position="top-right" />
    </TooltipProvider>
  )
}
