<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseBuilder;

/**
 * Baca laporan stok langsung dari ledger `stock_movements` (satu-satunya
 * sumber angka). `quantity` selalu positif, arah dibawa `direction`.
 *
 * `is_expired` dihitung saat SELECT terhadap `on_date` pada baris batch yang
 * sudah dinet, sehingga total per obat dan penanda tiap batch selalu memakai
 * definisi yang sama: kedaluwarsa bila `expires_on IS NOT NULL` dan
 * `expires_on < on_date`. Tepat pada tanggal kedaluwarsa batch masih tersedia;
 * `expires_on` NULL berarti tanpa kedaluwarsa.
 */
class StockRepository
{
    /**
     * Satu baris per obat aktif x batch; batch NULL untuk obat yang belum
     * punya gerak sama sekali. Diurutkan `medicine_id, expires_on, batch_no`
     * supaya pemanggil dapat menjumlah total per obat dalam satu lintasan.
     *
     * @return list<array<string, mixed>>
     */
    public function reportRows(string $onDate): array
    {
        $batches = $this->batchAggregate($onDate);

        return db_connect()->newQuery()
            ->from('medicines AS m')
            ->join('(' . str_replace("\n", ' ', $batches->getCompiledSelect(false)) . ') AS b', 'b.medicine_id = m.id', 'left')
            ->select('m.id AS medicine_id, m.code, m.name, m.unit', false)
            ->select('b.batch_no, b.expires_on, b.quantity, b.is_expired', false)
            ->where('m.is_active', 1)
            ->orderBy('m.id', 'ASC')
            ->orderBy('b.expires_on', 'ASC')
            ->orderBy('b.batch_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Batch dinet lebih dulu (in dikurangi out), baru diklasifikasikan.
     * Netting per batch wajib: menjumlah langsung per baris membuat baris
     * `out` tidak terikat batch sehingga stok tersedia membengkak.
     */
    private function batchAggregate(string $onDate): BaseBuilder
    {
        $db = db_connect();

        return $db->table('stock_movements')
            ->select('medicine_id, batch_no', false)
            ->select('MAX(expires_on) AS expires_on', false)
            ->select("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) AS quantity", false)
            ->select('CASE WHEN MAX(expires_on) IS NOT NULL AND MAX(expires_on) < ' . $db->escape($onDate) . ' THEN 1 ELSE 0 END AS is_expired', false)
            ->groupBy('medicine_id, batch_no');
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
     * Kedaluwarsa yang sudah tercatat untuk sekumpulan batch, satu query per
     * sumber. Stok awal (`seed_batch_stock`) menang atas item penerimaan,
     * mengikuti urutan pemeriksaan lama.
     *
     * @param list<array{medicine_id: int, batch_no: string}> $batches
     *
     * @return array<string, string> kunci "medicine_id|batch_no" => expires_on
     */
    public function knownExpiries(array $batches): array
    {
        if ($batches === []) {
            return [];
        }

        $expiries = [];

        foreach (['seed_batch_stock', 'reception_items'] as $table) {
            $rows = db_connect()
                ->table($table)
                ->select('medicine_id, batch_no, expires_on')
                ->whereIn('medicine_id', array_column($batches, 'medicine_id'))
                ->whereIn('batch_no', array_column($batches, 'batch_no'))
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $expiries[(int) $row['medicine_id'] . '|' . $row['batch_no']] ??= $row['expires_on'];
            }
        }

        return $expiries;
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
}
