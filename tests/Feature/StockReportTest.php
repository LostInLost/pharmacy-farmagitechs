<?php

use App\Services\StockService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Angka contoh soal pada on_date=2026-10-03.
 *
 * @internal
 */
final class StockReportTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();
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

    /**
     * Baris ledger untuk stok awal, meniru alur produksi.
     */
    private function seedMovement(int $medicineId, string $batchNo, string $expiresOn, int $quantity): void
    {
        $this->db->table('stock_movements')->insert([
            'medicine_id'   => $medicineId,
            'batch_no'      => $batchNo,
            'expires_on'    => $expiresOn,
            'movement_type' => 'seed',
            'direction'     => 'in',
            'quantity'      => $quantity,
            'moved_at'      => '2026-10-01 00:00:00',
            'created_at'    => '2026-10-01 00:00:00',
        ]);
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
        $this->assertSame(0, $this->medicine(106)['available_quantity']);
    }

    public function testMedicineWithOnlyExpiredBatchHasZeroAvailable(): void
    {
        $medicine = $this->medicine(107);

        $this->assertSame(6, $medicine['physical_quantity']);
        $this->assertSame(0, $medicine['available_quantity']);
        $this->assertSame(6, $medicine['expired_quantity']);
    }

    public function testBatchesAreGroupedAndOrdered(): void
    {
        $medicine = $this->medicine(101);

        $this->assertSame(['PCT-2601', 'PCT-2602'], array_column($medicine['available_batches'], 'batch_no'));
        $this->assertSame(['PCT-2501'], array_column($medicine['expired_batches'], 'batch_no'));
        $this->assertSame(94, $medicine['available_batches'][0]['quantity']);
        $this->assertSame(40, $medicine['available_batches'][1]['quantity']);
    }

    public function testExpiryFlagIsReportedPerBatch(): void
    {
        $medicine = $this->medicine(101);

        $this->assertFalse($medicine['available_batches'][0]['is_expired']);
        $this->assertFalse($medicine['available_batches'][1]['is_expired']);
        $this->assertTrue($medicine['expired_batches'][0]['is_expired']);
    }

    public function testExpiryFlagFollowsOnDate(): void
    {
        // Sehari sebelum kedaluwarsa: masih tersedia.
        $before = $this->medicine(107, '2026-09-29');
        $this->assertSame(['LOR-2501'], array_column($before['available_batches'], 'batch_no'));
        $this->assertSame([], $before['expired_batches']);

        // Tepat pada tanggal kedaluwarsa: masih tersedia.
        $onDate = $this->medicine(107, '2026-09-30');
        $this->assertSame(['LOR-2501'], array_column($onDate['available_batches'], 'batch_no'));
        $this->assertFalse($onDate['available_batches'][0]['is_expired']);
        $this->assertSame([], $onDate['expired_batches']);

        // Sehari setelah kedaluwarsa: pindah ke daftar kedaluwarsa.
        $after = $this->medicine(107, '2026-10-01');
        $this->assertSame([], $after['available_batches']);
        $this->assertSame(['LOR-2501'], array_column($after['expired_batches'], 'batch_no'));
        $this->assertTrue($after['expired_batches'][0]['is_expired']);
    }

    public function testMedicineWithoutBatchStillAppears(): void
    {
        $medicine = $this->medicine(106);

        $this->assertNotSame([], $medicine);
        $this->assertSame(0, $medicine['physical_quantity']);
        $this->assertSame([], $medicine['available_batches']);
        $this->assertSame([], $medicine['expired_batches']);
    }

    public function testInactiveMedicineIsExcluded(): void
    {
        $ids = array_column((new StockService())->report('2026-10-03')['medicines'], 'medicine_id');

        $this->assertNotContains(105, $ids);
    }

    public function testBatchExpiringExactlyOnDateIsAvailable(): void
    {
        $this->db->table('seed_batch_stock')->insert([
            'medicine_id' => 102,
            'batch_no'    => 'AMX-EDGE',
            'expires_on'  => '2026-10-03',
            'quantity'    => 4,
        ]);
        $this->seedMovement(102, 'AMX-EDGE', '2026-10-03', 4);

        $this->assertSame(20, $this->medicine(102, '2026-10-03')['available_quantity']);
        $this->assertSame(0, $this->medicine(102, '2026-10-03')['expired_quantity']);

        $this->assertSame(16, $this->medicine(102, '2026-10-04')['available_quantity']);
        $this->assertSame(4, $this->medicine(102, '2026-10-04')['expired_quantity']);
    }

    public function testOnDateDefaultsToToday(): void
    {
        $report = (new StockService())->report();

        $this->assertSame(date('Y-m-d'), $report['on_date']);
    }

    public function testExplicitOnDateIsEchoed(): void
    {
        $this->assertSame('2026-10-03', (new StockService())->report('2026-10-03')['on_date']);
    }

    public function testReceivedBatchWithoutSeedAppears(): void
    {
        $this->db->table('receptions')->insert([
            'reference_no' => 'PB-TEST',
            'supplier_id'  => 1,
            'received_at'  => '2026-10-03 10:00:00',
            'created_by'   => 1,
            'created_at'   => '2026-10-03 10:00:00',
        ]);
        $receptionId = (int) $this->db->insertID();

        $this->db->table('reception_items')->insert([
            'reception_id' => $receptionId,
            'medicine_id'  => 102,
            'batch_no'     => 'AMX-BARU',
            'expires_on'   => '2027-05-31',
            'quantity'     => 9,
        ]);

        $this->db->table('stock_movements')->insert([
            'medicine_id'   => 102,
            'batch_no'      => 'AMX-BARU',
            'expires_on'    => '2027-05-31',
            'movement_type' => 'receipt',
            'direction'     => 'in',
            'quantity'      => 9,
            'reception_id'  => $receptionId,
            'moved_at'      => '2026-10-03 10:00:00',
            'created_at'    => '2026-10-03 10:00:00',
        ]);

        $medicine = $this->medicine(102);

        $this->assertSame(25, $medicine['available_quantity']);
        $this->assertContains('AMX-BARU', array_column($medicine['available_batches'], 'batch_no'));
    }
}
