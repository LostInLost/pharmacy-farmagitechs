<?php

namespace App\Validation;

use App\Repositories\SupplierRepository;

/**
 * Aturan master pemasok. `name` diperiksa keunikannya secara case-insensitive
 * karena unique index MySQL memakai collation `_ci`: tanpa pemeriksaan yang
 * sama, bentrok `Farma Nusantara` vs `farma nusantara` akan lolos validasi
 * lalu gagal di database sebagai error 500, bukan pesan 422 yang bisa dibaca
 * pengguna.
 */
class SupplierValidator
{
    public function __construct(
        private readonly SupplierRepository $suppliers = new SupplierRepository(),
    ) {
    }

    /**
     * @return list<string> daftar pesan kesalahan; kosong berarti valid
     */
    public function validate(array $payload, ?int $supplierId = null): array
    {
        $errors = [];

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $errors[] = lang('Supplier.validation.name_required');
        } elseif (mb_strlen($name) > 150) {
            $errors[] = lang('Supplier.validation.name_too_long');
        } elseif ($this->suppliers->nameTaken($name, $supplierId)) {
            $errors[] = lang('Supplier.validation.name_taken', [$name]);
        }

        if (array_key_exists('is_active', $payload) && ! is_bool($payload['is_active'])) {
            $errors[] = lang('Supplier.validation.is_active_invalid');
        }

        return $errors;
    }
}
