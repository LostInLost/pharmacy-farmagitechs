<?php

namespace Tests\Support\Fixtures;

use CodeIgniter\Database\BaseConnection;

class StockFixture
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function seed(): void
    {
        $this->clear();

        $this->db->table('suppliers')->insertBatch([
            ['id' => 1, 'name' => 'PT Sehat Sentosa', 'is_active' => 1],
            ['id' => 2, 'name' => 'PT Nonaktif', 'is_active' => 0],
        ]);

        $this->db->table('medicines')->insertBatch([
            ['id' => 101, 'code' => 'OBT-001', 'name' => 'Paracetamol 500 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 102, 'code' => 'OBT-002', 'name' => 'Amoxicillin 500 mg kapsul', 'unit' => 'kapsul', 'is_active' => 1],
            ['id' => 103, 'code' => 'OBT-003', 'name' => 'Salbutamol 2 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 104, 'code' => 'OBT-004', 'name' => 'Ibuprofen 400 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 105, 'code' => 'OBT-005', 'name' => 'Vitamin C 500 mg tablet', 'unit' => 'tablet', 'is_active' => 0],
            ['id' => 106, 'code' => 'OBT-006', 'name' => 'Dexamethasone 0.5 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 107, 'code' => 'OBT-007', 'name' => 'Antasida sirup', 'unit' => 'botol', 'is_active' => 1],
        ]);

        $this->db->table('seed_batch_stock')->insertBatch([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 100],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31', 'quantity' => 40],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2501', 'expires_on' => '2026-09-30', 'quantity' => 8],
            ['medicine_id' => 102, 'batch_no' => 'AMX-2601', 'expires_on' => '2027-06-30', 'quantity' => 16],
            ['medicine_id' => 103, 'batch_no' => 'SAL-2602', 'expires_on' => '2027-08-31', 'quantity' => 15],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2601', 'expires_on' => '2027-10-31', 'quantity' => 3],
            ['medicine_id' => 107, 'batch_no' => 'ANT-2501', 'expires_on' => '2026-08-31', 'quantity' => 6],
        ]);

        $this->db->table('stock_usage')->insert([
            'medicine_id' => 101,
            'batch_no'    => 'PCT-2601',
            'quantity'    => 6,
        ]);

        $this->db->table('users')->insert([
            'name'          => 'Dewi Petugas',
            'username'      => 'petugas',
            'email'         => 'petugas@test.local',
            'password_hash' => 'x',
            'role'          => 'reception',
            'is_active'     => 1,
        ]);
    }

    public function clear(): void
    {
        foreach (['reception_logs', 'reception_items', 'receptions', 'stock_usage', 'seed_batch_stock', 'medicines', 'suppliers', 'users'] as $table) {
            $this->db->table($table)->emptyTable();
        }
    }
}
