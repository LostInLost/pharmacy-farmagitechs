<?php

namespace App\Repositories;

use App\Models\SupplierModel;

/**
 * Katalog pemasok. Baris nonaktif tetap tersimpan dan tetap dapat dibaca —
 * `is_active` hanya menandai boleh-tidaknya dipakai penerimaan baru.
 */
class SupplierRepository
{
    public function __construct(
        private readonly SupplierModel $suppliers = new SupplierModel(),
    ) {
    }

    /**
     * @return list<array{id: int, name: string, is_active: bool}>
     */
    public function findAll(?string $q = null, string $status = 'all'): array
    {
        $builder = $this->suppliers->select('id, name, is_active');

        if ($q !== null && trim($q) !== '') {
            $builder->like('name', $this->escapeLike(trim($q)), 'both', true);
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
     * @return array{id: int, name: string, is_active: bool}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->suppliers->select('id, name, is_active')->find($id);

        return $row === null ? null : $this->normalize($row);
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        $builder = $this->suppliers->like('name', $this->escapeLike($name), 'none', true);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    public function insert(array $data): int
    {
        return (int) $this->suppliers->insert($data);
    }

    public function update(int $id, array $data): void
    {
        $this->suppliers->update($id, $data);
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
            'name'      => $row['name'],
            'is_active' => (int) $row['is_active'] === 1,
        ];
    }
}
