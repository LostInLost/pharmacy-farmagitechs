import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"

/**
 * Judul halaman per rute. Form tambah/ubah penerimaan kini berupa sheet di
 * `/receptions`, jadi tidak ada lagi remah untuk rute form terpisah.
 */
const ROUTE_TITLES: Record<string, string> = {
  "/dashboard": "Dashboard",
  "/receptions": "Penerimaan",
  "/stocks": "Laporan Stok",
  "/medicines": "Master Obat",
}

function crumbsFor(pathname: string, title: string) {
  const exact = ROUTE_TITLES[pathname]

  if (exact !== undefined) {
    return { parent: null, current: title }
  }

  // Rute anak apa pun di bawah /receptions memakai remah induknya.
  if (pathname.startsWith("/receptions/")) {
    return {
      parent: { label: ROUTE_TITLES["/receptions"], href: "/receptions" },
      current: title,
    }
  }

  return { parent: null, current: title }
}

export function SiteHeader({
  title = "Dashboard",
  pathname = "",
}: {
  title?: string
  pathname?: string
}) {
  const { parent, current } = crumbsFor(pathname, title)

  return (
    <header className="flex h-(--header-height) shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)">
      <div className="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
        <SidebarTrigger className="-ml-1" />
        <Separator
          orientation="vertical"
          className="mx-2 data-[orientation=vertical]:h-4"
        />
        <Breadcrumb>
          <BreadcrumbList>
            {parent !== null && (
              <>
                <BreadcrumbItem className="hidden md:block">
                  <BreadcrumbLink href={parent.href}>
                    {parent.label}
                  </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator className="hidden md:block" />
              </>
            )}
            <BreadcrumbItem>
              <BreadcrumbPage>{current}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      </div>
    </header>
  )
}
