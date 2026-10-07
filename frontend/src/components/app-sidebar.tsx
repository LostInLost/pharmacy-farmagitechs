import * as React from "react"

import { NavDocuments } from "@/components/nav-documents"
import { NavMain } from "@/components/nav-main"
import { NavSecondary } from "@/components/nav-secondary"
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
  Settings2Icon,
  CircleHelpIcon,
  SearchIcon,
  DatabaseIcon,
  FileChartColumnIcon,
  CommandIcon,
} from "lucide-react"

import type { AuthUser } from "@/features/auth/schemas"

type SessionUser = AuthUser

const data = {
  navMain: [
    {
      title: "Dashboard",
      url: "#",
      icon: <LayoutDashboardIcon />,
    },
    {
      title: "Penerimaan",
      url: "#",
      icon: <TruckIcon />,
    },
    {
      title: "Stok",
      url: "#",
      icon: <PackageIcon />,
    },
  ],
  navSecondary: [
    {
      title: "Pengaturan",
      url: "#",
      icon: <Settings2Icon />,
    },
    {
      title: "Bantuan",
      url: "#",
      icon: <CircleHelpIcon />,
    },
    {
      title: "Cari",
      url: "#",
      icon: <SearchIcon />,
    },
  ],
  documents: [
    {
      name: "Data Master",
      url: "#",
      icon: <DatabaseIcon />,
    },
    {
      name: "Laporan",
      url: "#",
      icon: <FileChartColumnIcon />,
    },
  ],
}

export function AppSidebar({
  user,
  onLogout,
  ...props
}: React.ComponentProps<typeof Sidebar> & {
  user: SessionUser
  onLogout: () => void
}) {
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
        <NavMain items={data.navMain} />
        <NavDocuments items={data.documents} />
        <NavSecondary items={data.navSecondary} className="mt-auto" />
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
