import { Button } from "@/components/ui/button"
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { CirclePlusIcon, type LucideIcon } from "lucide-react"

type NavItem = {
  title: string
  url: string
  icon: LucideIcon
  isActive?: boolean
}

/**
 * Menu sidebar bergrup: satu aksi cepat di atas, lalu grup-grup berlabel.
 *
 * Bentuk ini mengikuti blok `dashboard-01` (aksi cepat) dan `sidebar-07`
 * (SidebarGroup + SidebarGroupLabel). Karena `Sidebar` memakai
 * `collapsible="icon"`, label grup ikut menyusut dan tiap tombol menyisakan
 * ikonnya saja — jadi ikon wajib ada di setiap entri menu.
 *
 * Jarak antar item diatur di sini (`gap-1`), bukan di `ui/sidebar.tsx`:
 * `SidebarMenu` bawaan memakai `gap-0` (tombol menempel tanpa jeda) dan
 * komponen `ui/` sengaja dibiarkan sebisa mungkin sama dengan upstream.
 */
export function NavMain({
  groups,
}: {
  groups: {
    label: string
    items: NavItem[]
  }[]
}) {
  return (
    <>
      {/*
        Aksi cepat sengaja tidak diberi label grup: ia perintah, bukan
        navigasi, jadi tidak masuk hitungan "Utama" maupun "Operasional".
      */}
      <SidebarGroup>
        <SidebarGroupContent>
          <SidebarMenu className="gap-1">
            <SidebarMenuItem>
              <SidebarMenuButton asChild tooltip="Penerimaan baru">
                <Button
                  asChild
                  className="w-full justify-start bg-primary text-primary-foreground duration-200 ease-linear hover:bg-primary/90 hover:text-primary-foreground active:bg-primary/90 active:text-primary-foreground"
                >
                  <a href="/receptions/new">
                    <CirclePlusIcon />
                    <span>Tambah Penerimaan</span>
                  </a>
                </Button>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroupContent>
      </SidebarGroup>

      {groups.map((group) => (
        <SidebarGroup key={group.label}>
          <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
          <SidebarMenu className="gap-1">
            {group.items.map((item) => (
              <SidebarMenuItem key={item.title}>
                <SidebarMenuButton
                  asChild
                  tooltip={item.title}
                  isActive={item.isActive}
                >
                  <a href={item.url}>
                    <item.icon />
                    <span>{item.title}</span>
                  </a>
                </SidebarMenuButton>
              </SidebarMenuItem>
            ))}
          </SidebarMenu>
        </SidebarGroup>
      ))}
    </>
  )
}
