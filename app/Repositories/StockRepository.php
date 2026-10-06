<?php

namespace App\Repositories;

use App\Models\MedicineModel;

class StockRepository
{
    public function __construct(
        private readonly MedicineModel $medicines = new MedicineModel(),
    ) {
    }

    /**
     * Batch dengan stok fisik per obat aktif, hasil agregasi tiga sumber.
     *
     * @return list<array{medicine_id: int, batch_no: string, expires_on: string|null, quantity: int}>
     */
    public function batches(): array
    {
        $db = db_connect();

        $seed = $db->table('seed_batch_stock')
            ->select('medicine_id, batch_no, expires_on, quantity AS seed_qty, 0 AS received_qty, 0 AS used_qty', false);

        $received = $db->table('reception_items')
            ->select('medicine_id, batch_no, expires_on, 0 AS seed_qty, quantity AS received_qty, 0 AS used_qty', false);

        $used = $db->table('stock_usage')
            ->select('medicine_id, batch_no, NULL AS expires_on, 0 AS seed_qty, 0 AS received_qty, quantity AS used_qty', false);

        $rows = $db->newQuery()
            ->fromSubquery($seed->unionAll($received)->unionAll($used), 't')
            ->select('t.medicine_id, t.batch_no, MAX(t.expires_on) AS expires_on, SUM(t.seed_qty + t.received_qty - t.used_qty) AS quantity', false)
            ->groupBy('t.medicine_id, t.batch_no')
            ->orderBy('t.medicine_id, expires_on')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): array => [
            'medicine_id' => (int) $row['medicine_id'],
            'batch_no'    => $row['batch_no'],
            'expires_on'  => $row['expires_on'],
            'quantity'    => (int) $row['quantity'],
        ], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeMedicines(): array
    {
        $rows = $this->medicines
            ->select('id, code, name, unit')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->findAll();

        return array_map(static fn (array $row): array => [
            'id'   => (int) $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'unit' => $row['unit'],
        ], $rows);
    }
}
