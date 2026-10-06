<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Data awal sementara dari angka yang disebut pada soal.
 * Dipakai sampai Lampiran/seed_farmasi.sql tersedia, lalu dilewati.
 */
class ProvisionalStockSeeder extends Seeder
{
    public function run()
    {
        if ($this->db->table('seed_batch_stock')->countAllResults() > 0) {
            return;
        }

        $suppliers = [
            ['id' => 1, 'name' => 'PT Sehat Sentosa', 'is_active' => 1],
            ['id' => 2, 'name' => 'PT Farma Nusantara', 'is_active' => 1],
        ];

        $medicines = [
            ['id' => 101, 'code' => 'OBT-001', 'name' => 'Paracetamol 500 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 102, 'code' => 'OBT-002', 'name' => 'Amoxicillin 500 mg kapsul', 'unit' => 'kapsul', 'is_active' => 1],
            ['id' => 103, 'code' => 'OBT-003', 'name' => 'Salbutamol 2 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 104, 'code' => 'OBT-004', 'name' => 'Ibuprofen 400 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 105, 'code' => 'OBT-005', 'name' => 'Vitamin C 500 mg tablet', 'unit' => 'tablet', 'is_active' => 0],
            ['id' => 106, 'code' => 'OBT-006', 'name' => 'Dexamethasone 0.5 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 107, 'code' => 'OBT-007', 'name' => 'Antasida sirup', 'unit' => 'botol', 'is_active' => 1],
        ];

        $seedBatchStock = [
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 100],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31', 'quantity' => 40],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2501', 'expires_on' => '2026-09-30', 'quantity' => 8],
            ['medicine_id' => 102, 'batch_no' => 'AMX-2601', 'expires_on' => '2027-06-30', 'quantity' => 16],
            ['medicine_id' => 103, 'batch_no' => 'SAL-2602', 'expires_on' => '2027-08-31', 'quantity' => 15],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2601', 'expires_on' => '2027-10-31', 'quantity' => 3],
            ['medicine_id' => 107, 'batch_no' => 'ANT-2501', 'expires_on' => '2026-08-31', 'quantity' => 6],
        ];

        $stockUsage = [
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'quantity' => 6],
        ];

        $this->db->table('suppliers')->insertBatch($suppliers);
        $this->db->table('medicines')->insertBatch($medicines);
        $this->db->table('seed_batch_stock')->insertBatch($seedBatchStock);
        $this->db->table('stock_usage')->insertBatch($stockUsage);
    }
}
