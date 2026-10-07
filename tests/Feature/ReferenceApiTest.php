<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Endpoint dropdown form: hanya baris aktif, field minimal, butuh login.
 *
 * @internal
 */
final class ReferenceApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();
    }

    private function actor(): array
    {
        return ['user_id' => 1, 'user_name' => 'Dewi Petugas', 'role' => 'reception'];
    }

    private function body($result): array
    {
        return json_decode((string) $result->getJSON(), true);
    }

    public function testSuppliersRequireLogin(): void
    {
        $this->withSession([])->get('/api/references/suppliers')->assertStatus(401);
        $this->withSession([])->get('/api/references/medicines')->assertStatus(401);
        $this->withSession([])->get('/api/references/batches')->assertStatus(401);
    }

    public function testBatchesReturnUniqueLedgerBatches(): void
    {
        $result = $this->withSession($this->actor())->get('/api/references/batches');

        $result->assertStatus(200);

        $data = $this->body($result)['data'];

        $this->assertSame([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2501', 'expires_on' => '2026-09-30'],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31'],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31'],
            ['medicine_id' => 102, 'batch_no' => 'AMX-2601', 'expires_on' => '2027-05-31'],
            ['medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30'],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2601', 'expires_on' => '2027-09-30'],
            ['medicine_id' => 107, 'batch_no' => 'LOR-2501', 'expires_on' => '2026-09-30'],
        ], $data);
    }

    public function testBatchesMergeRepeatedMovementsIntoOneRow(): void
    {
        // Gerakan lanjutan pada batch yang sama: kedaluwarsa mengambil MAX.
        $this->db->table('stock_movements')->insert([
            'medicine_id'   => 102,
            'batch_no'      => 'AMX-2601',
            'expires_on'    => '2027-06-30',
            'movement_type' => 'seed',
            'direction'     => 'in',
            'quantity'      => 5,
            'reception_id'  => null,
            'moved_at'      => '2026-10-03 08:00:00',
            'unit_name'     => null,
            'created_at'    => '2026-10-03 08:00:00',
        ]);

        $result = $this->withSession($this->actor())->get('/api/references/batches');

        $result->assertStatus(200);

        $data = $this->body($result)['data'];

        $this->assertSame(['medicine_id', 'batch_no', 'expires_on'], array_keys($data[0]));
        $this->assertSame(
            ['medicine_id' => 102, 'batch_no' => 'AMX-2601', 'expires_on' => '2027-06-30'],
            $data[3]
        );
    }

    public function testSuppliersReturnOnlyActiveWithMinimalFields(): void
    {
        $result = $this->withSession($this->actor())->get('/api/references/suppliers');

        $result->assertStatus(200);

        $data = $this->body($result)['data'];

        $this->assertSame([
            ['id' => 1, 'name' => 'Farma Nusantara'],
            ['id' => 2, 'name' => 'Medika Sentosa'],
        ], $data);
    }

    public function testMedicinesReturnOnlyActiveWithMinimalFields(): void
    {
        $result = $this->withSession($this->actor())->get('/api/references/medicines');

        $result->assertStatus(200);

        $data = $this->body($result)['data'];
        $ids  = array_column($data, 'id');

        $this->assertNotContains(105, $ids);
        $this->assertSame([102, 106, 104, 107, 101, 103], $ids);
        $this->assertSame(['id', 'name', 'unit'], array_keys($data[0]));
    }

    public function testReceiptIndexMarksEditableRows(): void
    {
        $created = (new App\Services\ReceptionService())->create([
            'reference_no' => 'PB-OWN-1',
            'supplier_id'  => 1,
            'received_at'  => '2026-10-03T10:00:00+07:00',
            'items'        => [[
                'medicine_id' => 101,
                'batch_no'    => 'PCT-2601',
                'expires_on'  => '2027-12-31',
                'quantity'    => 10,
            ]],
        ], 1);

        $this->assertTrue($created['ok']);

        $result = $this->withSession($this->actor())->get('/api/receipts');

        $result->assertStatus(200);

        $data = $this->body($result)['data'];

        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['can_update']);
    }
}
