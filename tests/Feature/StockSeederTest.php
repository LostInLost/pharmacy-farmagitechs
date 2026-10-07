<?php

use App\Database\Seeds\StockSeeder;
use App\Services\StockService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Lampiran `app/Database/seed_farmasi.sql` yang dimuat StockSeeder harus
 * menghasilkan angka contoh soal pada on_date 2026-10-03.
 *
 * @internal
 */
final class StockSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = null;
    protected $seed      = StockSeeder::class;

    private function actor(): array
    {
        return ['user_id' => 1, 'user_name' => 'Dewi Petugas', 'role' => 'reception'];
    }

    private function medicine(int $id, string $onDate = '2026-10-03'): array
    {
        foreach ((new StockService())->report($onDate)['medicines'] as $medicine) {
            if ($medicine['medicine_id'] === $id) {
                return $medicine;
            }
        }

        return [];
    }

    public function testLampiranIsLoadedCompletely(): void
    {
        $this->assertSame(3, $this->db->table('suppliers')->countAllResults());
        $this->assertSame(25, $this->db->table('medicines')->countAllResults());
        $this->assertSame(10, $this->db->table('seed_batch_stock')->countAllResults());
        $this->assertSame(3, $this->db->table('stock_usage')->countAllResults());

        $usage = $this->db->table('stock_usage')->where('id', 1)->get()->getRowArray();
        $this->assertSame('2026-10-02 09:00:00', $usage['used_at']);
        $this->assertSame('Poliklinik Umum', $usage['unit_name']);
    }

    public function testLampiranAlsoLoadsLedger(): void
    {
        $this->assertSame(10, $this->db->table('stock_movements')->where('movement_type', 'seed')->countAllResults());
        $this->assertSame(3, $this->db->table('stock_movements')->where('movement_type', 'usage')->countAllResults());

        $seed = $this->db->table('stock_movements')
            ->where('movement_type', 'seed')
            ->where('batch_no', 'PCT-2601')
            ->get()
            ->getRowArray();

        $this->assertSame('2026-10-01 00:00:00', $seed['moved_at']);
        $this->assertSame(100, (int) $seed['quantity']);
        $this->assertSame('in', $seed['direction']);
    }

    public function testBaselineNumbersMatchBrief(): void
    {
        $this->assertSame(142, $this->medicine(101)['physical_quantity']);
        $this->assertSame(134, $this->medicine(101)['available_quantity']);
        $this->assertSame(8, $this->medicine(101)['expired_quantity']);

        $this->assertSame(16, $this->medicine(102)['available_quantity']);
        $this->assertSame(15, $this->medicine(103)['available_quantity']);
        $this->assertSame(3, $this->medicine(104)['available_quantity']);
        $this->assertSame(0, $this->medicine(106)['physical_quantity']);

        $this->assertSame(6, $this->medicine(107)['physical_quantity']);
        $this->assertSame(0, $this->medicine(107)['available_quantity']);
        $this->assertSame(6, $this->medicine(107)['expired_quantity']);
    }

    public function testBatchesFromLampiranAreGrouped(): void
    {
        $medicine = $this->medicine(101);
        $batches  = array_column($medicine['batches'], null, 'batch_no');

        $this->assertSame(['PCT-2501', 'PCT-2601', 'PCT-2602'], array_column($medicine['batches'], 'batch_no'));
        $this->assertSame(94, $batches['PCT-2601']['quantity']);
        $this->assertSame(40, $batches['PCT-2602']['quantity']);

        $this->assertFalse($batches['PCT-2601']['is_expired']);
        $this->assertFalse($batches['PCT-2602']['is_expired']);
        $this->assertTrue($batches['PCT-2501']['is_expired']);
    }

    public function testOnlyActiveMedicinesAreReported(): void
    {
        $medicines = (new StockService())->report('2026-10-03')['medicines'];
        $ids       = array_column($medicines, 'medicine_id');

        $this->assertCount(22, $medicines);
        $this->assertNotContains(105, $ids);
        $this->assertNotContains(124, $ids);
        $this->assertNotContains(125, $ids);
    }

    public function testRemainingLampiranBatchesAppear(): void
    {
        $this->assertSame(12, $this->medicine(108)['available_quantity']);
        $this->assertSame(9, $this->medicine(112)['available_quantity']);
        $this->assertSame(7, $this->medicine(118)['available_quantity']);
    }

    public function testRerunUpdatesWithoutDuplicating(): void
    {
        $this->seed(StockSeeder::class);

        $this->assertSame(3, $this->db->table('suppliers')->countAllResults());
        $this->assertSame(25, $this->db->table('medicines')->countAllResults());
        $this->assertSame(10, $this->db->table('seed_batch_stock')->countAllResults());
        $this->assertSame(3, $this->db->table('stock_usage')->countAllResults());
        $this->assertSame(10, $this->db->table('stock_movements')->where('movement_type', 'seed')->countAllResults());
        $this->assertSame(3, $this->db->table('stock_movements')->where('movement_type', 'usage')->countAllResults());
        $this->assertSame(134, $this->medicine(101)['available_quantity']);
    }

    public function testApiReportMatchesBrief(): void
    {
        $result = $this->withSession($this->actor())->get('/api/stocks?on_date=2026-10-03');

        $result->assertStatus(200);

        $body    = json_decode((string) $result->getJSON(), true);
        $byId    = array_column($body['medicines'], null, 'medicine_id');
        $quantities = static fn (int $id): array => [
            $byId[$id]['physical_quantity'],
            $byId[$id]['available_quantity'],
            $byId[$id]['expired_quantity'],
        ];

        $this->assertSame('2026-10-03', $body['on_date']);
        $this->assertCount(22, $body['medicines']);
        $this->assertSame([142, 134, 8], $quantities(101));
        $this->assertSame([16, 16, 0], $quantities(102));
        $this->assertSame([15, 15, 0], $quantities(103));
        $this->assertSame([3, 3, 0], $quantities(104));
        $this->assertSame([0, 0, 0], $quantities(106));
        $this->assertSame([6, 0, 6], $quantities(107));

        $batches = array_column($byId[101]['batches'], null, 'batch_no');
        $this->assertSame(['PCT-2501', 'PCT-2601', 'PCT-2602'], array_column($byId[101]['batches'], 'batch_no'));
        $this->assertFalse($batches['PCT-2601']['is_expired']);
        $this->assertTrue($batches['PCT-2501']['is_expired']);
    }

    public function testApiReferencesMatchLampiran(): void
    {
        $suppliers = $this->withSession($this->actor())->get('/api/references/suppliers');
        $suppliers->assertStatus(200);
        $supplierData = json_decode((string) $suppliers->getJSON(), true)['data'];

        $medicines = $this->withSession($this->actor())->get('/api/references/medicines');
        $medicines->assertStatus(200);
        $medicineData = json_decode((string) $medicines->getJSON(), true)['data'];

        $this->assertSame([
            ['id' => 1, 'name' => 'Farma Nusantara'],
            ['id' => 2, 'name' => 'Medika Sentosa'],
        ], $supplierData);

        $this->assertCount(22, $medicineData);
        $this->assertNotContains(105, array_column($medicineData, 'id'));
        $this->assertNotContains(124, array_column($medicineData, 'id'));
        $this->assertNotContains(125, array_column($medicineData, 'id'));
    }
}
