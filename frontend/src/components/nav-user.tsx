import * as React from "react"

import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"
import type { AuthUser } from "@/features/auth/schemas"
import {
  applyTheme,
  currentDark,
  readTheme,
  subscribeTheme,
  type Theme,
} from "@/foundations/theme"
import {
  CircleUserRoundIcon,
  EllipsisVerticalIcon,
  LogOutIcon,
  MoonIcon,
  SunIcon,
} from "lucide-react"

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)

  if (parts.length === 0) return "?"

  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()

  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const THEME_LABELS: Record<Theme, string> = {
  light: "Terang",
  dark: "Gelap",
  system: "Ikuti sistem",
}

/**
 * Menu pengguna di dasar sidebar — bentuknya mengikuti blok `nav-user`
 * dashboard-01 (tombol user + dropdown berisi grup item, submenu, dan keluar),
 * tapi isinya hanya yang benar-benar berfungsi di aplikasi ini:
 *
 * - ringkasan akun + "Profil Saya" → dialog data sesi `GET /api/me`
 * - submenu "Tema" → terang / gelap / ikuti sistem (palet `.dark` sudah ada)
 * - "Keluar" → logout seperti blok pengguna `layout.php` CI4
 *
 * Item demo template (Account/Billing/Notifications) sengaja tidak dibawa
 * karena tidak ada halaman maupun endpoint-nya.
 */
export function NavUser({
  user,
  onLogout,
}: {
  user: AuthUser
  onLogout?: () => void
}) {
  const { isMobile } = useSidebar()
  const [profileOpen, setProfileOpen] = React.useState(false)
  const fallback = initials(user.name)

  // Tema dibaca dari external store, bukan `setState` di dalam effect: nilai
  // server (`false`) dipakai saat render pertama, lalu klien menyesuaikan.
  const theme = React.useSyncExternalStore(subscribeTheme, readTheme, () => "system")
  const dark = React.useSyncExternalStore(subscribeTheme, currentDark, () => false)

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <Avatar className="h-8 w-8 rounded-lg">
                <AvatarFallback className="rounded-lg">
                  {fallback}
                </AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                <span className="truncate text-xs text-muted-foreground">
                  {user.role}
                </span>
              </div>
              <EllipsisVerticalIcon className="ml-auto size-4" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
            side={isMobile ? "bottom" : "right"}
            align="end"
            sideOffset={4}
          >
            <DropdownMenuLabel className="p-0 font-normal">
              <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <Avatar className="h-8 w-8 rounded-lg">
                  <AvatarFallback className="rounded-lg">
                    {fallback}
                  </AvatarFallback>
                </Avatar>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-medium">{user.name}</span>
                  <span className="truncate text-xs text-muted-foreground">
                    {user.username}
                  </span>
                </div>
                <Badge variant="secondary">{user.role}</Badge>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
              <DropdownMenuItem onSelect={() => setProfileOpen(true)}>
                <CircleUserRoundIcon />
                Profil Saya
              </DropdownMenuItem>
              <DropdownMenuSub>
                <DropdownMenuSubTrigger>
                  {dark ? <MoonIcon /> : <SunIcon />}
                  Tema
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent>
                  <DropdownMenuRadioGroup
                    value={theme}
                    onValueChange={(value) => applyTheme(value as Theme)}
                  >
                    {(Object.keys(THEME_LABELS) as Theme[]).map((option) => (
                      <DropdownMenuRadioItem key={option} value={option}>
                        {THEME_LABELS[option]}
                      </DropdownMenuRadioItem>
                    ))}
                  </DropdownMenuRadioGroup>
                </DropdownMenuSubContent>
              </DropdownMenuSub>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={() => onLogout?.()}>
              <LogOutIcon />
              Keluar
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <ProfileDialog
          user={user}
          open={profileOpen}
          onOpenChange={setProfileOpen}
        />
      </SidebarMenuItem>
    </SidebarMenu>
  )
}

/** Detail akun dari sesi berjalan; hanya menampilkan apa yang dimiliki backend. */
function ProfileDialog({
  user,
  open,
  onOpenChange,
}: {
  user: AuthUser
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const rows: { label: string; value: string }[] = [
    { label: "Nama lengkap", value: user.name },
    { label: "Username", value: user.username },
    { label: "Peran", value: user.role },
    { label: "ID pengguna", value: String(user.id) },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Profil Saya</DialogTitle>
          <DialogDescription>
            Data akun sesuai sesi yang sedang berjalan.
          </DialogDescription>
        </DialogHeader>
        <dl className="grid gap-3">
          {rows.map((row) => (
            <div
              key={row.label}
              className="flex items-center justify-between gap-4"
            >
              <dt className="text-sm text-muted-foreground">{row.label}</dt>
              <dd className="text-sm font-medium">{row.value}</dd>
            </div>
          ))}
        </dl>
      </DialogContent>
    </Dialog>
  )
}
