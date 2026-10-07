<?php

use App\Services\ReceptionService;
use App\Services\StockService;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Jumlah query adalah bagian dari kontrak: semua yang dibaca untuk satu operasi
 * diambil sekaligus, sehingga ongkosnya tidak tumbuh mengikuti jumlah obat atau
 * jumlah baris item.
 *
 * Penghitung dipasang lewat event `DBQuery` — framework 4.7 tidak lagi menyimpan
 * daftar query di properti koneksi.
 *
 * @internal
 */
final class QueryCountTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private array $queries = [];

    private int $petugasId;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();

        $this->petugasId = (int) $this->db->table('users')->where('username', 'petugas')->get()->getRowArray()['id'];
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('DBQuery');

        $this->queries = [];

        parent::tearDown();
    }

    /**
     * @return list<string> SQL yang dieksekusi selama $work berjalan
     */
    private function countQueries(callable $work): array
    {
        $this->queries = [];

        Events::on('DBQuery', function (object $query): void {
            $this->queries[] = (string) $query->getQuery();
        });

        try {
            $work();
        } finally {
            Events::removeAllListeners('DBQuery');
        }

        return $this->queries;
    }

    public function testStockReportUsesSingleQuery(): void
    {
        $queries = $this->countQueries(static function (): void {
            (new StockService())->report('2026-10-03');
        });

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('medicines', $queries[0]);
        $this->assertStringContainsString('stock_movements', $queries[0]);
    }

    public function testStockReportQueryCountIsIndependentOfMedicineCount(): void
    {
        $before = count($this->countQueries(static function (): void {
            (new StockService())->report('2026-10-03');
        }));

        $this->db->table('medicines')->insertBatch([
            ['id' => 111, 'code' => 'OBT-011', 'name' => 'Obat tambahan satu', 'unit' => 'tablet', 'is_active' => 1],
            ['id' => 112, 'code' => 'OBT-012', 'name' => 'Obat tambahan dua', 'unit' => 'tablet', 'is_active' => 1],
        ]);

        $after = count($this->countQueries(static function (): void {
            (new StockService())->report('2026-10-03');
        }));

        $this->assertSame($before, $after);
    }

    public function testReceptionQueriesAreIndependentOfItemCount(): void
    {
        $one = count($this->countQueries(function (): void {
            (new ReceptionService())->create($this->payload('PB-SATU', [
                ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ]), $this->petugasId);
        }));

        $five = count($this->countQueries(function (): void {
            (new ReceptionService())->create($this->payload('PB-LIMA', [
                ['medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31', 'quantity' => 10],
                ['medicine_id' => 102, 'batch_no' => 'AMX-2601', 'expires_on' => '2027-05-31', 'quantity' => 4],
                ['medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30', 'quantity' => 3],
                ['medicine_id' => 104, 'batch_no' => 'IBU-2601', 'expires_on' => '2027-09-30', 'quantity' => 5],
                ['medicine_id' => 106, 'batch_no' => 'CTZ-2601', 'expires_on' => '2028-01-31', 'quantity' => 2],
            ]), $this->petugasId);
        }));

        $this->assertSame($one, $five);
    }

    private function payload(string $referenceNo, array $items): array
    {
        return [
            'reference_no' => $referenceNo,
            'supplier_id'  => 1,
            'received_at'  => '2026-10-03T10:00:00+07:00',
            'items'        => $items,
        ];
    }
}
