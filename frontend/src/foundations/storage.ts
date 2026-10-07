/**
 * Helper `sessionStorage` generik: semua operasi menelan error
 * (private mode / storage penuh / JSON rusak) dan tidak pernah throw.
 */

export function readStorageRaw(key: string): string | null {
  try {
    return sessionStorage.getItem(key)
  } catch {
    return null
  }
}

export function writeStorageRaw(key: string, value: string): void {
  try {
    sessionStorage.setItem(key, value)
  } catch {
    // Private mode / storage penuh: abaikan, UI punya fallback.
  }
}

export function removeStorageKey(key: string): void {
  try {
    sessionStorage.removeItem(key)
  } catch {
    // abaikan
  }
}
