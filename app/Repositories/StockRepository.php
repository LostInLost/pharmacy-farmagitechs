<?php

namespace App\Repositories;

use App\Models\MedicineModel;

/**
 * Baca laporan stok langsung dari ledger `stock_movements` (satu-satunya
 * sumber angka). `quantity` selalu positif, arah dibawa `direction`.
 *
 * `is_expired` dihitung saat SELECT terhadap `on_date`: batch kedaluwarsa
 * bila `MAX(expires_on) < on_date`. Tepat pada tanggal kedaluwarsa batch
 * masih tersedia; `expires_on` NULL berarti tanpa kedaluwarsa.
 */
class StockRepository
{
    public function __construct(
        private readonly MedicineModel $medicines = new MedicineModel(),
    ) {
    }

    /**
     * Batch per obat aktif dengan stok fisik dan penanda kedaluwarsa.
     *
     * @return list<array{medicine_id: int, batch_no: string, expires_on: string|null, quantity: int, is_expired: bool}>
     */
    public function batches(string $onDate): array
    {
        $db    = db_connect();
        $query = $db->table('stock_movements')
            ->select('medicine_id, batch_no', false)
            ->select('MAX(expires_on) AS expires_on', false)
            ->select("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) AS quantity", false)
            ->select("CASE WHEN MAX(expires_on) IS NOT NULL AND MAX(expires_on) < " . $db->escape($onDate) . ' THEN 1 ELSE 0 END AS is_expired', false)
            ->groupBy('medicine_id, batch_no')
            ->orderBy('medicine_id, expires_on');

        $rows = $query->get()->getResultArray();

        return array_map(static fn (array $row): array => [
            'medicine_id' => (int) $row['medicine_id'],
            'batch_no'    => $row['batch_no'],
            'expires_on'  => $row['expires_on'],
            'quantity'    => (int) $row['quantity'],
            'is_expired'  => (int) $row['is_expired'] === 1,
        ], $rows);
    }

    /**
     * Daftar batch unik per obat dari ledger — referensi dropdown form
     * penerimaan. Tanpa jumlah dan klasifikasi kedaluwarsa; obat nonaktif
     * tetap terbawa karena tak pernah cocok dengan obat terpilih yang aktif.
     *
     * @return list<array{medicine_id: int, batch_no: string, expires_on: string|null}>
     */
    public function batchReferences(): array
    {
        $rows = db_connect()
            ->table('stock_movements')
            ->select('medicine_id, batch_no, MAX(expires_on) AS expires_on', false)
            ->groupBy('medicine_id, batch_no')
            ->orderBy('medicine_id, batch_no')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): array => [
            'medicine_id' => (int) $row['medicine_id'],
            'batch_no'    => $row['batch_no'],
            'expires_on'  => $row['expires_on'],
        ], $rows);
    }

    /**
     * Total per obat dihitung database. Batch dinet dulu (in dikurangi out),
     * baru dijumlah per obat, sehingga angka cocok dengan `batches()`.
     *
     * @return array<int, array{physical_quantity: int, available_quantity: int, expired_quantity: int}>
     */
    public function summaries(string $onDate): array
    {
        $db = db_connect();

        $batches = $db->table('stock_movements')
            ->select('medicine_id, batch_no', false)
            ->select('MAX(expires_on) AS expires_on', false)
            ->select("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) AS quantity", false)
            ->groupBy('medicine_id, batch_no');

        $rows = $db->newQuery()
            ->fromSubquery($batches, 'b')
            ->select('b.medicine_id', false)
            ->select('SUM(b.quantity) AS physical_quantity', false)
            ->select('SUM(CASE WHEN b.expires_on IS NULL OR b.expires_on >= ' . $db->escape($onDate) . ' THEN b.quantity ELSE 0 END) AS available_quantity', false)
            ->select('SUM(CASE WHEN b.expires_on IS NOT NULL AND b.expires_on < ' . $db->escape($onDate) . ' THEN b.quantity ELSE 0 END) AS expired_quantity', false)
            ->groupBy('b.medicine_id')
            ->get()
            ->getResultArray();

        $summaries = [];

        foreach ($rows as $row) {
            $summaries[(int) $row['medicine_id']] = [
                'physical_quantity'  => (int) $row['physical_quantity'],
                'available_quantity' => (int) $row['available_quantity'],
                'expired_quantity'   => (int) $row['expired_quantity'],
            ];
        }

        return $summaries;
    }

    /**
     * Riwayat gerak satu batch, terbaru lebih dahulu.
     *
     * @return list<array<string, mixed>>
     */
    public function movementsOf(int $medicineId, string $batchNo, ?string $direction = null): array
    {
        $builder = db_connect()->table('stock_movements')
            ->where('medicine_id', $medicineId)
            ->where('batch_no', $batchNo)
            ->orderBy('moved_at', 'DESC')
            ->orderBy('id', 'DESC');

        if ($direction !== null) {
            $builder->where('direction', $direction);
        }

        return array_map(static fn (array $row): array => [
            'id'            => (int) $row['id'],
            'movement_type' => $row['movement_type'],
            'direction'     => $row['direction'],
            'quantity'      => (int) $row['quantity'],
            'reception_id'  => $row['reception_id'] === null ? null : (int) $row['reception_id'],
            'moved_at'      => $row['moved_at'],
            'unit_name'     => $row['unit_name'],
        ], $builder->get()->getResultArray());
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
