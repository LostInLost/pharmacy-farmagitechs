<?php

use App\Models\StockMovementModel;
use App\Repositories\StockRepository;
use App\Services\ReceptionService;
use App\Services\StockService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Ledger `stock_movements` sebagai sumber tunggal angka stok: arah gerak
 * konsisten, write-through dari penerimaan, dan laporan hanya membaca ledger.
 *
 * @internal
 */
final class StockMovementLedgerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private int $petugasId;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();

        $this->petugasId = (int) $this->db->table('users')->where('username', 'petugas')->get()->getRowArray()['id'];
    }

    private function payload(array $items): array
    {
        return [
            'reference_no' => 'PB-LEDGER',
            'supplier_id'  => 1,
            'received_at'  => '2026-10-03T10:00:00+07:00',
            'items'        => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movementsOfReception(int $receptionId): array
    {
        return $this->db->table('stock_movements')
            ->where('reception_id', $receptionId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
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

    public function testFixtureWritesLedgerWithConsistentDirections(): void
    {
        $this->assertSame(7, $this->db->table('stock_movements')->where('movement_type', 'seed')->countAllResults());
        $this->assertSame(3, $this->db->table('stock_movements')->where('movement_type', 'usage')->countAllResults());

        $this->assertSame(0, $this->db->table('stock_movements')->where('movement_type', 'seed')->where('direction !=', 'in')->countAllResults());
        $this->assertSame(0, $this->db->table('stock_movements')->where('movement_type', 'usage')->where('direction !=', 'out')->countAllResults());
    }

    public function testReceptionCreateWritesReceiptMovement(): void
    {
        $result = (new ReceptionService())->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $this->assertTrue($result['ok']);

        $rows = $this->movementsOfReception($result['id']);
        $this->assertCount(1, $rows);
        $this->assertSame('receipt', $rows[0]['movement_type']);
        $this->assertSame('in', $rows[0]['direction']);
        $this->assertSame(10, (int) $rows[0]['quantity']);
        $this->assertSame('2027-12-31', $rows[0]['expires_on']);
        $this->assertSame('2026-10-03 10:00:00', $rows[0]['moved_at']);
    }

    public function testReceptionUpdateReplacesReceiptMovements(): void
    {
        $service = new ReceptionService();
        $created = $service->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
        ]), $this->petugasId);

        $service->update($created['id'], $this->payload([
            ['medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30', 'quantity' => 3],
        ]), ['id' => $this->petugasId, 'role' => 'reception']);

        $rows = $this->movementsOfReception($created['id']);
        $this->assertCount(1, $rows);
        $this->assertSame(103, (int) $rows[0]['medicine_id']);
        $this->assertSame('SAL-2601', $rows[0]['batch_no']);
        $this->assertSame(3, (int) $rows[0]['quantity']);
    }

    public function testDeletingReceptionRemovesItsMovements(): void
    {
        $result = (new ReceptionService())->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $this->assertCount(1, $this->movementsOfReception($result['id']));

        $this->db->table('reception_items')->where('reception_id', $result['id'])->delete();
        $this->db->table('receptions')->where('id', $result['id'])->delete();

        $this->assertSame(0, $this->db->table('stock_movements')->where('reception_id', $result['id'])->countAllResults());
    }

    public function testReportReadsOnlyLedger(): void
    {
        // Baris domain tanpa pasangan ledger tidak boleh mengubah laporan.
        $this->db->table('seed_batch_stock')->insert([
            'medicine_id' => 106,
            'batch_no'    => 'CTZ-2601',
            'expires_on'  => '2027-01-31',
            'quantity'    => 50,
        ]);

        $this->assertSame(0, $this->medicine(106)['physical_quantity']);

        // Baris ledger langsung justru mengubah laporan.
        $this->db->table('stock_movements')->insert([
            'medicine_id'   => 106,
            'batch_no'      => 'CTZ-2601',
            'expires_on'    => '2027-01-31',
            'movement_type' => 'seed',
            'direction'     => 'in',
            'quantity'      => 50,
            'moved_at'      => '2026-10-01 00:00:00',
            'created_at'    => '2026-10-01 00:00:00',
        ]);

        $this->assertSame(50, $this->medicine(106)['physical_quantity']);
        $this->assertSame(50, $this->medicine(106)['available_quantity']);
    }

    /**
     * `reportRows()` mengirim satu baris per batch (dan satu baris batch NULL
     * untuk obat tanpa gerak), jadi total per obat dihitung dari baris itu.
     */
    public function testReportRowsMatchPerMedicineTotals(): void
    {
        $rows = (new StockRepository())->reportRows('2026-10-03');

        $totals = [];

        foreach ($rows as $row) {
            $id = (int) $row['medicine_id'];

            if ($row['batch_no'] === null) {
                continue;
            }

            $quantity = (int) $row['quantity'];

            $totals[$id]['physical'] = ($totals[$id]['physical'] ?? 0) + $quantity;
            $key                     = (int) $row['is_expired'] === 1 ? 'expired' : 'available';
            $totals[$id][$key]       = ($totals[$id][$key] ?? 0) + $quantity;
        }

        $this->assertSame(142, $totals[101]['physical']);
        $this->assertSame(134, $totals[101]['available']);
        $this->assertSame(8, $totals[101]['expired']);

        // Angka dari repository harus sama dengan laporan yang dilihat pengguna.
        $report = (new StockService())->report('2026-10-03');

        foreach ($report['medicines'] as $medicine) {
            $id = $medicine['medicine_id'];

            $this->assertSame($totals[$id]['physical'] ?? 0, $medicine['physical_quantity'], "physical {$id}");
            $this->assertSame($totals[$id]['available'] ?? 0, $medicine['available_quantity'], "available {$id}");
            $this->assertSame($totals[$id]['expired'] ?? 0, $medicine['expired_quantity'], "expired {$id}");
        }
    }

    public function testMovementsOfBatchIsNewestFirstAndFilterable(): void
    {
        $repo = new StockRepository();

        $all = $repo->movementsOf(101, 'PCT-2601');
        $this->assertSame(['usage', 'seed'], array_column($all, 'movement_type'));

        $out = $repo->movementsOf(101, 'PCT-2601', 'out');
        $this->assertCount(1, $out);
        $this->assertSame(6, $out[0]['quantity']);
        $this->assertSame('Poliklinik Umum', $out[0]['unit_name']);
    }

    public function testOverUsageIsReportedAsNegativePhysical(): void
    {
        $this->db->table('stock_movements')->insert([
            'medicine_id'   => 107,
            'batch_no'      => 'LOR-2501',
            'expires_on'    => '2026-09-30',
            'movement_type' => 'usage',
            'direction'     => 'out',
            'quantity'      => 9,
            'moved_at'      => '2026-10-04 08:00:00',
            'unit_name'     => 'IGD',
            'created_at'    => '2026-10-04 08:00:00',
        ]);

        // Stok awal 6, dipakai 9, tetap tampil apa adanya.
        $this->assertSame(-3, $this->medicine(107)['physical_quantity']);
    }

    public function testModelRejectsMismatchedDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StockMovementModel())->insertBatch([[
            'medicine_id'   => 101,
            'batch_no'      => 'PCT-2601',
            'expires_on'    => '2027-12-31',
            'movement_type' => 'receipt',
            'direction'     => 'out',
            'quantity'      => 1,
            'moved_at'      => '2026-10-03 10:00:00',
            'created_at'    => '2026-10-03 10:00:00',
        ]]);
    }

    public function testModelRejectsNonPositiveQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StockMovementModel())->insertBatch([[
            'medicine_id'   => 101,
            'batch_no'      => 'PCT-2601',
            'expires_on'    => '2027-12-31',
            'movement_type' => 'seed',
            'direction'     => 'in',
            'quantity'      => 0,
            'moved_at'      => '2026-10-03 10:00:00',
            'created_at'    => '2026-10-03 10:00:00',
        ]]);
    }
}
