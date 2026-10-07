<?php

namespace Tests\Support\Fixtures;

use CodeIgniter\Database\BaseConnection;

/**
 * Subset lampiran `app/Database/seed_farmasi.sql` untuk test aplikasi:
 * obat 101-107 dengan batch dan pemakaian aslinya, sehingga angka soal
 * (mis. obat 102 = 20 - 4 = 16) berasal dari agregasi yang sama.
 *
 * Tabel domain diisi seperti alur produksi: setiap baris domain disertai
 * baris ledger `stock_movements` dengan `moved_at` yang sama.
 */
class StockFixture
{
    private const SEED_MOVED_AT = '2026-10-01 00:00:00';

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function seed(): void
    {
        $this->clear();

        $this->db->table('suppliers')->insertBatch([
            ['id' => 1, 'name' => 'Farma Nusantara', 'is_active' => 1],
            ['id' => 2, 'name' => 'Medika Sentosa', 'is_active' => 1],
            ['id' => 3, 'name' => 'Pemasok Arsip', 'is_active' => 0],
        ]);

        $this->db->table('medicines')->insertBatch([
            ['id' => 101, 'code' => 'OBT-001', 'name' => 'Paracetamol 500 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 102, 'code' => 'OBT-002', 'name' => 'Amoxicillin 500 mg kapsul', 'unit' => 'kapsul', 'is_active' => 1],
            ['id' => 103, 'code' => 'OBT-003', 'name' => 'Salbutamol 2 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 104, 'code' => 'OBT-004', 'name' => 'Ibuprofen 400 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 105, 'code' => 'OBT-005', 'name' => 'Obat nonaktif contoh', 'unit' => 'tablet', 'is_active' => 0],
            ['id' => 106, 'code' => 'OBT-006', 'name' => 'Cetirizine 10 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 107, 'code' => 'OBT-007', 'name' => 'Loratadine 10 mg tablet', 'unit' => 'tablet', 'is_active' => 1],
        ]);

        $batches = [
            ['id' => 1, 'medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 100],
            ['id' => 2, 'medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31', 'quantity' => 40],
            ['id' => 3, 'medicine_id' => 102, 'batch_no' => 'AMX-2601', 'expires_on' => '2027-05-31', 'quantity' => 20],
            ['id' => 4, 'medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30', 'quantity' => 15],
            ['id' => 5, 'medicine_id' => 104, 'batch_no' => 'IBU-2601', 'expires_on' => '2027-09-30', 'quantity' => 5],
            ['id' => 6, 'medicine_id' => 101, 'batch_no' => 'PCT-2501', 'expires_on' => '2026-09-30', 'quantity' => 8],
            ['id' => 7, 'medicine_id' => 107, 'batch_no' => 'LOR-2501', 'expires_on' => '2026-09-30', 'quantity' => 6],
        ];

        $this->db->table('seed_batch_stock')->insertBatch($batches);

        $this->db->table('stock_movements')->insertBatch(array_map(static fn (array $batch): array => [
            'medicine_id'   => $batch['medicine_id'],
            'batch_no'      => $batch['batch_no'],
            'expires_on'    => $batch['expires_on'],
            'movement_type' => 'seed',
            'direction'     => 'in',
            'quantity'      => $batch['quantity'],
            'reception_id'  => null,
            'moved_at'      => self::SEED_MOVED_AT,
            'unit_name'     => null,
            'created_at'    => self::SEED_MOVED_AT,
        ], $batches));

        $usages = [
            ['id' => 1, 'medicine_id' => 101, 'batch_no' => 'PCT-2601', 'used_at' => '2026-10-02 09:00:00', 'unit_name' => 'Poliklinik Umum', 'quantity' => 6],
            ['id' => 2, 'medicine_id' => 102, 'batch_no' => 'AMX-2601', 'used_at' => '2026-10-02 11:00:00', 'unit_name' => 'IGD', 'quantity' => 4],
            ['id' => 3, 'medicine_id' => 104, 'batch_no' => 'IBU-2601', 'used_at' => '2026-10-02 14:00:00', 'unit_name' => 'Rawat Inap', 'quantity' => 2],
        ];

        $this->db->table('stock_usage')->insertBatch($usages);

        $this->db->table('stock_movements')->insertBatch(array_map(static fn (array $usage): array => [
            'medicine_id'   => $usage['medicine_id'],
            'batch_no'      => $usage['batch_no'],
            'expires_on'    => null,
            'movement_type' => 'usage',
            'direction'     => 'out',
            'quantity'      => $usage['quantity'],
            'reception_id'  => null,
            'moved_at'      => $usage['used_at'],
            'unit_name'     => $usage['unit_name'],
            'created_at'    => $usage['used_at'],
        ], $usages));

        $this->db->table('users')->insert([
            'name'          => 'Dewi Petugas',
            'username'      => 'petugas',
            'email'         => 'petugas@test.local',
            'password_hash' => 'x',
            'role'          => 'reception',
            'is_active'     => 1,
        ]);
    }

    /**
     * Urutan penting: `stock_movements` mereferensikan `medicines` dan
     * `receptions`, jadi harus dihapus lebih dulu.
     */
    public function clear(): void
    {
        foreach (['stock_movements', 'audit_logs', 'reception_items', 'receptions', 'stock_usage', 'seed_batch_stock', 'medicines', 'suppliers', 'users'] as $table) {
            $this->db->table($table)->emptyTable();
        }
    }
}
