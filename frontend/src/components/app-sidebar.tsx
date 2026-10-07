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
  SidebarRail,
} from "@/components/ui/sidebar"
import {
  CommandIcon,
  LayoutDashboardIcon,
  PackageIcon,
  PillIcon,
  TruckIcon,
} from "lucide-react"

import type { AuthUser } from "@/features/auth/schemas"

/**
 * Menu sidebar aplikasi.
 *
 * Urutannya mengikuti sidebar CI4 (`app/Views/layout.php`) — Penerimaan lalu
 * Stok — dengan Dashboard sebagai entri pertama karena versi Astro memakai
 * Dashboard sebagai halaman arahan setelah masuk, dan Master Obat sebagai
 * katalog penunjang di grup yang sama. Grup "Utama"/"Operasional" hanya
 * pemisah tampilan, bukan hak akses: menu Master Obat tetap tampil untuk
 * petugas karena halamannya sendiri hanya membaca; tombol tambah/ubah baru
 * muncul bila server menyatakan `can_write`.
 *
 * `icon` disimpan sebagai referensi komponen (bukan elemen JSX) karena
 * `collapsible="icon"` merender ulang ikonnya saat sidebar menyusut.
 */
const navGroups = [
  {
    label: "Utama",
    items: [
      { title: "Dashboard", url: "/dashboard", icon: LayoutDashboardIcon },
    ],
  },
  {
    label: "Operasional",
    items: [
      { title: "Penerimaan", url: "/receptions", icon: TruckIcon },
      { title: "Laporan Stok", url: "/stocks", icon: PackageIcon },
      { title: "Master Obat", url: "/medicines", icon: PillIcon },
    ],
  },
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
  const groups = navGroups.map((group) => ({
    ...group,
    items: group.items.map((item) => ({
      ...item,
      isActive: pathname === item.url || pathname.startsWith(`${item.url}/`),
    })),
  }))

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              asChild
              tooltip="Farmagitechs"
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
        <NavMain groups={groups} />
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={user} onLogout={onLogout} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
