<?php

namespace App\Repositories;

use App\Models\MedicineModel;

/**
 * Katalog obat. Baris nonaktif tetap tersimpan dan tetap dapat dibaca —
 * `is_active` hanya menandai boleh-tidaknya dipakai transaksi baru.
 */
class MedicineRepository
{
    public function __construct(
        private readonly MedicineModel $medicines = new MedicineModel(),
    ) {
    }

    /**
     * @return list<array{id: int, code: string, name: string, unit: string, is_active: bool}>
     */
    public function findAll(?string $q = null, string $status = 'all'): array
    {
        $builder = $this->medicines->select('id, code, name, unit, is_active');

        if ($q !== null && trim($q) !== '') {
            $needle = $this->escapeLike(trim($q));

            $builder->groupStart()
                ->like('code', $needle, 'both', true)
                ->orLike('name', $needle, 'both', true)
                ->groupEnd();
        }

        if ($status === 'active') {
            $builder->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $builder->where('is_active', 0);
        }

        return array_map(
            fn (array $row): array => $this->normalize($row),
            $builder->orderBy('name', 'ASC')->orderBy('id', 'ASC')->findAll(),
        );
    }

    /**
     * @return array{id: int, code: string, name: string, unit: string, is_active: bool}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->medicines->select('id, code, name, unit, is_active')->find($id);

        return $row === null ? null : $this->normalize($row);
    }

    public function codeTaken(string $code, ?int $exceptId = null): bool
    {
        $builder = $this->medicines->like('code', $this->escapeLike($code), 'none', true);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    public function insert(array $data): int
    {
        return (int) $this->medicines->insert($data);
    }

    public function update(int $id, array $data): void
    {
        $this->medicines->update($id, $data);
    }

    /**
     * Kurung liar LIKE dikunci agar pencarian pengguna tidak berubah menjadi
     * wildcard: `%` dan `_` dicari sebagai karakter biasa.
     */
    private function escapeLike(string $value): string
    {
        return db_connect()->escapeLikeString($value);
    }

    private function normalize(array $row): array
    {
        return [
            'id'        => (int) $row['id'],
            'code'      => $row['code'],
            'name'      => $row['name'],
            'unit'      => $row['unit'],
            'is_active' => (int) $row['is_active'] === 1,
        ];
    }
}
