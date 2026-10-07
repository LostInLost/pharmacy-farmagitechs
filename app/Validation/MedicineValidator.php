<?php

namespace App\Validation;

use App\Repositories\MedicineRepository;

/**
 * Aturan master obat. `code` diperiksa keunikannya secara case-insensitive
 * karena unique index MySQL memakai collation `_ci`: tanpa pemeriksaan yang
 * sama, bentrok `OBT-001` vs `obt-001` akan lolos validasi lalu gagal di
 * database sebagai error 500, bukan pesan 422 yang bisa dibaca pengguna.
 */
class MedicineValidator
{
    public function __construct(
        private readonly MedicineRepository $medicines = new MedicineRepository(),
    ) {
    }

    /**
     * @return list<string> daftar pesan kesalahan; kosong berarti valid
     */
    public function validate(array $payload, ?int $medicineId = null): array
    {
        $errors = [];

        $code = trim((string) ($payload['code'] ?? ''));

        if ($code === '') {
            $errors[] = lang('Medicine.validation.code_required');
        } elseif (mb_strlen($code) > 50) {
            $errors[] = lang('Medicine.validation.code_too_long');
        } elseif ($this->medicines->codeTaken($code, $medicineId)) {
            $errors[] = lang('Medicine.validation.code_taken', [$code]);
        }

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $errors[] = lang('Medicine.validation.name_required');
        } elseif (mb_strlen($name) > 200) {
            $errors[] = lang('Medicine.validation.name_too_long');
        }

        $unit = trim((string) ($payload['unit'] ?? ''));

        if ($unit === '') {
            $errors[] = lang('Medicine.validation.unit_required');
        } elseif (mb_strlen($unit) > 50) {
            $errors[] = lang('Medicine.validation.unit_too_long');
        }

        if (array_key_exists('is_active', $payload) && ! is_bool($payload['is_active'])) {
            $errors[] = lang('Medicine.validation.is_active_invalid');
        }

        return $errors;
    }
}
