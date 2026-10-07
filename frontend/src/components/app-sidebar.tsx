import * as React from "react"

import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import {
  LayoutDashboardIcon,
  PackageIcon,
  TruckIcon,
  CommandIcon,
} from "lucide-react"

import type { AuthUser } from "@/features/auth/schemas"

const navMain = [
  { title: "Dashboard", url: "/dashboard", icon: <LayoutDashboardIcon /> },
  { title: "Penerimaan", url: "/receptions", icon: <TruckIcon /> },
  { title: "Laporan Stok", url: "/stocks", icon: <PackageIcon /> },
]

export function AppSidebar({
  user,
  pathname,
  onLogout,
  ...props
}: React.ComponentProps<typeof Sidebar> & {
  user: AuthUser
  pathname: string
  onLogout: () => void
}) {
  // Halaman anak ikut menyalakan menu induknya, mis. /receptions/new.
  const items = navMain.map((item) => ({
    ...item,
    isActive: pathname === item.url || pathname.startsWith(`${item.url}/`),
  }))

  return (
    <Sidebar collapsible="offcanvas" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              asChild
              className="data-[slot=sidebar-menu-button]:p-1.5!"
            >
              <a href="/dashboard">
                <CommandIcon className="size-5!" />
                <span className="text-base font-semibold">Farmagitechs</span>
              </a>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <NavMain items={items} />
      </SidebarContent>
      <SidebarFooter>
        <NavUser
          user={{
            name: user.name,
            email: user.username || user.role,
            avatar: "",
          }}
          onLogout={onLogout}
        />
      </SidebarFooter>
    </Sidebar>
  )
}
