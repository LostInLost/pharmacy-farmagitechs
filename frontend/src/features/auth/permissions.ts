/**
 * Permission sisi klien — cerminan `Config\Permissions` di backend.
 *
 * Backend menghitung daftar ini per role dan mengirimkannya lewat
 * `GET /api/me` (juga `POST /api/login`) sebagai array datar string. Frontend
 * hanya memakainya untuk **menggating tampilan**: menyembunyikan aksi yang
 * tak mungkin diizinkan sehingga pengguna tidak menabrak 403.
 *
 * Penegakan tetap sepenuhnya di server (policy per request) — nilai di sini
 * tidak boleh dianggap sebagai otorisasi.
 */
export const PERMISSIONS = {
  receiptCreate: "receipt.create",
  receiptView: "receipt.view",
  receiptUpdateOwn: "receipt.update-own",
  receiptUpdateAny: "receipt.update-any",
  medicineView: "medicine.view",
  medicineWrite: "medicine.write",
  supplierView: "supplier.view",
  supplierWrite: "supplier.write",
} as const

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS]

/**
 * Cek satu permission pada daftar milik user. Daftar kosong → `false`
 * (fail-closed): bila backend belum mengirim `permissions` (versi lama),
 * tombol aksi hilang, bukan muncul tanpa hak.
 *
 * Catatan: untuk aksi per baris (ubah penerimaan), keputusan tetap memakai
 * `can_update` dari server karena `receipt.update-own` butuh data pemilik
 * baris — permission saja tidak cukup.
 */
export function hasPermission(
  permissions: readonly string[],
  permission: Permission
): boolean {
  return permissions.includes(permission)
}
