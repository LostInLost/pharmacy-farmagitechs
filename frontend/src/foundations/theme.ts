/**
 * Tema tampilan (terang/gelap/sistem) sebagai external store kecil.
 *
 * Palet gelap sudah tersedia di `global.css` lewat kelas `.dark`, jadi modul ini
 * hanya perlu menyalakan kelas tersebut, mengingat pilihannya, dan memberi tahu
 * pelanggan lewat `subscribeTheme` supaya komponen bisa membacanya dengan
 * `useSyncExternalStore` (bukan `setState` di dalam effect, dan tanpa mismatch
 * hidrasi karena ada `getServerSnapshot`).
 *
 * Semua akses storage menelan error (private mode / storage penuh) dan jatuh
 * kembali ke `system`.
 */

export type Theme = "light" | "dark" | "system"

/** Kunci yang sama dipakai skrip anti-kedip di `base-layout.astro`. */
export const THEME_STORAGE_KEY = "farmagitechs.theme"

const listeners = new Set<() => void>()

function notify(): void {
  listeners.forEach((listener) => listener())
}

/** Langganan perubahan tema; juga mengikuti perubahan preferensi sistem. */
export function subscribeTheme(listener: () => void): () => void {
  listeners.add(listener)

  let media: MediaQueryList | null = null

  try {
    media = window.matchMedia("(prefers-color-scheme: dark)")
    media.addEventListener("change", listener)
  } catch {
    // `matchMedia` tidak tersedia: cukup andalkan listener internal.
  }

  return () => {
    listeners.delete(listener)

    try {
      media?.removeEventListener("change", listener)
    } catch {
      // abaikan
    }
  }
}

export function readTheme(): Theme {
  try {
    const value = localStorage.getItem(THEME_STORAGE_KEY)

    if (value === "light" || value === "dark" || value === "system") {
      return value
    }
  } catch {
    // abaikan: private mode / storage diblokir
  }

  return "system"
}

/** Tema efektif: `system` mengikuti preferensi sistem operasi. */
export function resolveTheme(theme: Theme): "light" | "dark" {
  if (theme !== "system") return theme

  try {
    return window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light"
  } catch {
    return "light"
  }
}

/** Tema efektif dari pilihan tersimpan — dipakai sebagai snapshot store. */
export function currentDark(): boolean {
  return resolveTheme(readTheme()) === "dark"
}

/** Terapkan tema ke `<html>`, simpan pilihannya, lalu beri tahu pelanggan. */
export function applyTheme(theme: Theme): void {
  try {
    localStorage.setItem(THEME_STORAGE_KEY, theme)
  } catch {
    // abaikan: pilihan tetap berlaku untuk sesi halaman ini
  }

  document.documentElement.classList.toggle("dark", resolveTheme(theme) === "dark")
  notify()
}
