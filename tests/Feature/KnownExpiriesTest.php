<?php

use App\Repositories\StockRepository;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * `knownExpiries()` menggantikan pencarian per pasangan di validator dengan dua
 * query `whereIn`. Uji ini menjaga dua hal yang mudah rusak diam-diam:
 *
 * 1. Nilainya harus sama dengan pencarian per pasangan — termasuk saat payload
 *    menyebut batch milik obat lain (combobox batch memang lintas obat), yang
 *    tidak boleh menghasilkan kecocokan palsu.
 * 2. Jumlah query tidak boleh tumbuh mengikuti jumlah pasangan.
 *
 * @internal
 */
final class KnownExpiriesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();
    }

    /**
     * Implementasi lama: satu query per pasangan, seed menang atas penerimaan.
     */
    private function perPair(array $pairs): array
    {
        $expiries = [];

        foreach ($pairs as $pair) {
            $medicineId = (int) $pair['medicine_id'];
            $batchNo    = $pair['batch_no'];

            $seed = $this->db->table('seed_batch_stock')
                ->select('expires_on')
                ->where('medicine_id', $medicineId)
                ->where('batch_no', $batchNo)
                ->get()->getRowArray();

            if ($seed !== null) {
                $expiries[$medicineId . '|' . $batchNo] = $seed['expires_on'];

                continue;
            }

            $received = $this->db->table('reception_items')
                ->select('expires_on')
                ->where('medicine_id', $medicineId)
                ->where('batch_no', $batchNo)
                ->orderBy('id', 'ASC')
                ->get()->getRowArray();

            if ($received !== null) {
                $expiries[$medicineId . '|' . $batchNo] = $received['expires_on'];
            }
        }

        return $expiries;
    }

    private function countQueries(callable $work): array
    {
        $queries = [];

        Events::on('DBQuery', function (object $query) use (&$queries): void {
            $queries[] = (string) $query->getQuery();
        });

        try {
            $work();
        } finally {
            Events::removeAllListeners('DBQuery');
        }

        return $queries;
    }

    public function testMatchesPerPairLookupIncludingCrossMedicineBatches(): void
    {
        $repo  = new StockRepository();
        $pairs = [];

        foreach (['seed_batch_stock', 'reception_items'] as $table) {
            foreach ($this->db->table($table)->select('medicine_id, batch_no')->get()->getResultArray() as $row) {
                $pairs[(int) $row['medicine_id'] . '|' . $row['batch_no']] = [
                    'medicine_id' => (int) $row['medicine_id'],
                    'batch_no'    => $row['batch_no'],
                ];
            }
        }

        $this->assertNotSame([], $pairs);

        // Pasangan silang: batch milik satu obat diklaim untuk obat lain.
        $crossed = [];

        foreach ($pairs as $batch) {
            foreach ($pairs as $other) {
                if ($batch['medicine_id'] === $other['medicine_id']) {
                    continue;
                }

                $crossed[$other['medicine_id'] . '|' . $batch['batch_no']] = [
                    'medicine_id' => $other['medicine_id'],
                    'batch_no'    => $batch['batch_no'],
                ];
            }
        }

        $this->assertNotSame([], $crossed);

        $input     = array_values($pairs + $crossed);
        $reference = $this->perPair($input);
        $actual    = $repo->knownExpiries($input);

        foreach ($input as $pair) {
            $key = $pair['medicine_id'] . '|' . $pair['batch_no'];

            $this->assertSame(
                $reference[$key] ?? null,
                $actual[$key] ?? null,
                "kedaluwarsa untuk {$key} harus sama dengan pencarian per pasangan",
            );
        }
    }

    public function testDoesNotInventPairsThatWereNotAskedFor(): void
    {
        $repo = new StockRepository();

        // Obat 101 punya batch PCT-2601; obat 102 tidak pernah memakainya.
        $known = $repo->knownExpiries([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601'],
            ['medicine_id' => 102, 'batch_no' => 'PCT-2601'],
        ]);

        $this->assertArrayHasKey('101|PCT-2601', $known);
        $this->assertArrayNotHasKey('102|PCT-2601', $known);
    }

    public function testQueryCountDoesNotGrowWithBatchCount(): void
    {
        $repo = new StockRepository();

        $one = $this->countQueries(static fn (): array => $repo->knownExpiries([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601'],
        ]));

        $many = $this->countQueries(static fn (): array => $repo->knownExpiries([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601'],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2602'],
            ['medicine_id' => 102, 'batch_no' => 'AMX-2601'],
            ['medicine_id' => 103, 'batch_no' => 'SAL-2601'],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2601'],
        ]));

        $this->assertCount(2, $one);
        $this->assertCount(2, $many);
    }

    public function testEmptyInputRunsNoQuery(): void
    {
        $queries = $this->countQueries(static fn (): array => (new StockRepository())->knownExpiries([]));

        $this->assertSame([], $queries);
    }
}
