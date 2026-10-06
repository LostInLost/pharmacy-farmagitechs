<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Throwable;

/**
 * Data awal dari lampiran `app/Database/seed_farmasi.sql`.
 *
 * `suppliers` dan `medicines` disinkronkan per id karena keduanya direferensikan
 * foreign key sehingga barisnya tidak boleh dihapus ulang. `seed_batch_stock`
 * dan `stock_usage` dimuat ulang seluruhnya supaya angka laporan persis
 * lampiran. Seeder aman dijalankan berulang.
 */
class StockSeeder extends Seeder
{
    public function run()
    {
        $this->db->transBegin();

        try {
            $this->sync('suppliers', $this->suppliers());
            $this->sync('medicines', $this->medicines());
            $this->reload('seed_batch_stock', $this->seedBatchStock());
            $this->reload('stock_usage', $this->stockUsage());

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function sync(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $data = $row;
            unset($data['id']);

            if ($this->db->table($table)->where('id', $row['id'])->countAllResults() > 0) {
                $this->db->table($table)->where('id', $row['id'])->update($data);

                continue;
            }

            $this->db->table($table)->insert($row);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function reload(string $table, array $rows): void
    {
        $this->db->table($table)->emptyTable();
        $this->db->table($table)->insertBatch($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suppliers(): array
    {
        return [
            ['id' => 1, 'name' => 'Farma Nusantara', 'is_active' => 1],
            ['id' => 2, 'name' => 'Medika Sentosa', 'is_active' => 1],
            ['id' => 3, 'name' => 'Pemasok Arsip', 'is_active' => 0],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function medicines(): array
    {
        return [
            ['id' => 101, 'code' => 'OBT-001', 'name' => 'Paracetamol 500 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 102, 'code' => 'OBT-002', 'name' => 'Amoxicillin 500 mg kapsul', 'unit' => 'kapsul', 'is_active' => 1],
            ['id' => 103, 'code' => 'OBT-003', 'name' => 'Salbutamol 2 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 104, 'code' => 'OBT-004', 'name' => 'Ibuprofen 400 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 105, 'code' => 'OBT-005', 'name' => 'Obat nonaktif contoh', 'unit' => 'tablet', 'is_active' => 0],
            ['id' => 106, 'code' => 'OBT-006', 'name' => 'Cetirizine 10 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 107, 'code' => 'OBT-007', 'name' => 'Loratadine 10 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 108, 'code' => 'OBT-008', 'name' => 'Metformin 500 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 109, 'code' => 'OBT-009', 'name' => 'Amlodipine 5 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 110, 'code' => 'OBT-010', 'name' => 'Omeprazole 20 mg kapsul', 'unit' => 'kapsul', 'is_active' => 1],
            ['id' => 111, 'code' => 'OBT-011', 'name' => 'Oralit sachet', 'unit' => 'sachet', 'is_active' => 1],
            ['id' => 112, 'code' => 'OBT-012', 'name' => 'Asam folat 1 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 113, 'code' => 'OBT-013', 'name' => 'Zinc 20 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 114, 'code' => 'OBT-014', 'name' => 'Simvastatin 10 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 115, 'code' => 'OBT-015', 'name' => 'Losartan 50 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 116, 'code' => 'OBT-016', 'name' => 'Vitamin B kompleks tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 117, 'code' => 'OBT-017', 'name' => 'Chlorpheniramine 4 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 118, 'code' => 'OBT-018', 'name' => 'Antasida DOEN tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 119, 'code' => 'OBT-019', 'name' => 'Domperidone 10 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 120, 'code' => 'OBT-020', 'name' => 'Ondansetron 4 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 121, 'code' => 'OBT-021', 'name' => 'Nystatin suspensi oral', 'unit' => 'botol', 'is_active' => 1],
            ['id' => 122, 'code' => 'OBT-022', 'name' => 'Hydrocortisone 1 persen krim', 'unit' => 'tube', 'is_active' => 1],
            ['id' => 123, 'code' => 'OBT-023', 'name' => 'Natrium klorida 0,9 persen infus', 'unit' => 'botol', 'is_active' => 1],
            ['id' => 124, 'code' => 'OBT-024', 'name' => 'Ketoprofen 50 mg kapsul', 'unit' => 'kapsul', 'is_active' => 0],
            ['id' => 125, 'code' => 'OBT-025', 'name' => 'Cefadroxil 500 mg kapsul', 'unit' => 'kapsul', 'is_active' => 0],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function seedBatchStock(): array
    {
        return [
            ['id' => 1, 'medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 100],
            ['id' => 2, 'medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31', 'quantity' => 40],
            ['id' => 3, 'medicine_id' => 102, 'batch_no' => 'AMX-2601', 'expires_on' => '2027-05-31', 'quantity' => 20],
            ['id' => 4, 'medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30', 'quantity' => 15],
            ['id' => 5, 'medicine_id' => 104, 'batch_no' => 'IBU-2601', 'expires_on' => '2027-09-30', 'quantity' => 5],
            ['id' => 6, 'medicine_id' => 101, 'batch_no' => 'PCT-2501', 'expires_on' => '2026-09-30', 'quantity' => 8],
            ['id' => 7, 'medicine_id' => 107, 'batch_no' => 'LOR-2501', 'expires_on' => '2026-09-30', 'quantity' => 6],
            ['id' => 8, 'medicine_id' => 108, 'batch_no' => 'MET-2601', 'expires_on' => '2028-02-28', 'quantity' => 12],
            ['id' => 9, 'medicine_id' => 112, 'batch_no' => 'FOL-2601', 'expires_on' => '2027-08-31', 'quantity' => 9],
            ['id' => 10, 'medicine_id' => 118, 'batch_no' => 'ANT-2601', 'expires_on' => '2027-06-30', 'quantity' => 7],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockUsage(): array
    {
        return [
            ['id' => 1, 'medicine_id' => 101, 'batch_no' => 'PCT-2601', 'used_at' => '2026-10-02 09:00:00', 'unit_name' => 'Poliklinik Umum', 'quantity' => 6],
            ['id' => 2, 'medicine_id' => 102, 'batch_no' => 'AMX-2601', 'used_at' => '2026-10-02 11:00:00', 'unit_name' => 'IGD', 'quantity' => 4],
            ['id' => 3, 'medicine_id' => 104, 'batch_no' => 'IBU-2601', 'used_at' => '2026-10-02 14:00:00', 'unit_name' => 'Rawat Inap', 'quantity' => 2],
        ];
    }
}
