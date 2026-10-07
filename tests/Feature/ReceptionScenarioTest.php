<?php

use App\Services\ReceptionService;
use App\Services\StockService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Skenario wajib soal: buat, ubah, idempoten, atribusi, dan hak akses.
 *
 * @internal
 */
final class ReceptionScenarioTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private ReceptionService $receptions;
    private int $petugasId;
    private int $supervisorId;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();

        $this->db->table('users')->insert([
            'name'          => 'Rina Supervisor',
            'username'      => 'supervisor',
            'email'         => 'supervisor@test.local',
            'password_hash' => 'x',
            'role'          => 'supervisor',
            'is_active'     => 1,
        ]);

        $this->petugasId = (int) $this->db->table('users')->where('username', 'petugas')->get()->getRowArray()['id'];
        $this->supervisorId = (int) $this->db->table('users')->where('username', 'supervisor')->get()->getRowArray()['id'];
        $this->receptions = new ReceptionService();
    }

    private function payload(array $items): array
    {
        return [
            'reference_no' => 'PB-001',
            'supplier_id'  => 1,
            'received_at'  => '2026-10-03T10:00:00+07:00',
            'items'        => $items,
        ];
    }

    private function availableQuantity(int $medicineId, string $onDate = '2026-10-03'): int
    {
        $report = (new StockService())->report($onDate);

        foreach ($report['medicines'] as $medicine) {
            if ($medicine['medicine_id'] === $medicineId) {
                return $medicine['available_quantity'];
            }
        }

        return -1;
    }

    public function testScenario1CreateAddsStock(): void
    {
        $result = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
        ]), $this->petugasId);

        $this->assertTrue($result['ok']);
        $this->assertSame(144, $this->availableQuantity(101));
        $this->assertSame(8, $this->availableQuantity(104));
    }

    public function testScenario2UpdateReplacesItemsAndStock(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
        ]), $this->petugasId);

        $actor  = ['id' => $this->petugasId, 'role' => 'reception'];
        $result = $this->receptions->update($created['id'], $this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 7],
            ['medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30', 'quantity' => 3],
        ]), $actor);

        $this->assertTrue($result['ok']);
        $this->assertSame(141, $this->availableQuantity(101));
        $this->assertSame(18, $this->availableQuantity(103));
        $this->assertSame(3, $this->availableQuantity(104));

        $detail = $this->receptions->detail($created['id']);
        $this->assertCount(2, $detail['items']);
    }

    public function testScenario3RepeatedIdenticalUpdateIsIdempotent(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $actor   = ['id' => $this->petugasId, 'role' => 'reception'];
        $payload = $this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 7],
        ]);

        $this->receptions->update($created['id'], $payload, $actor);
        $first = $this->availableQuantity(101);

        $this->receptions->update($created['id'], $payload, $actor);
        $this->receptions->update($created['id'], $payload, $actor);

        $this->assertSame($first, $this->availableQuantity(101));
        $this->assertSame(141, $this->availableQuantity(101));
        $this->assertSame(101, $this->batchQuantity(101, 'PCT-2601'));
    }

    private function batchQuantity(int $medicineId, string $batchNo, string $onDate = '2026-10-03'): int
    {
        foreach ((new StockService())->report($onDate)['medicines'] as $medicine) {
            if ($medicine['medicine_id'] !== $medicineId) {
                continue;
            }

            foreach (array_merge($medicine['available_batches'], $medicine['expired_batches']) as $batch) {
                if ($batch['batch_no'] === $batchNo) {
                    return $batch['quantity'];
                }
            }
        }

        return -1;
    }

    public function testScenario4AttributionKeepsCreatorAndTracksEditor(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $supervisor = ['id' => $this->supervisorId, 'role' => 'supervisor'];
        $result     = $this->receptions->update($created['id'], $this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 12],
        ]), $supervisor);

        $this->assertTrue($result['ok']);

        $detail = $this->receptions->detail($created['id']);

        $this->assertSame($this->petugasId, $detail['created_by']);
        $this->assertSame($this->supervisorId, $detail['updated_by']);
        $this->assertNotNull($detail['updated_at']);

        $actions = array_column($detail['logs'], 'action');
        $this->assertSame(['CREATE', 'UPDATE'], $actions);
        $this->assertSame($this->petugasId, $detail['logs'][0]['actor_id']);
        $this->assertSame($this->supervisorId, $detail['logs'][1]['actor_id']);
    }

    public function testScenario5ReceptionOfficerCannotUpdateOthersReception(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->supervisorId);

        $itemsBefore = $this->db->table('reception_items')->countAllResults();
        $logsBefore  = $this->db->table('audit_logs')->countAllResults();
        $stockBefore = $this->availableQuantity(101);

        $petugas = ['id' => $this->petugasId, 'role' => 'reception'];
        $result  = $this->receptions->update($created['id'], $this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 99],
        ]), $petugas);

        $this->assertFalse($result['ok']);
        $this->assertSame(403, $result['status']);

        $this->assertSame($itemsBefore, $this->db->table('reception_items')->countAllResults());
        $this->assertSame($logsBefore, $this->db->table('audit_logs')->countAllResults());
        $this->assertSame($stockBefore, $this->availableQuantity(101));

        $detail = $this->receptions->detail($created['id']);
        $this->assertNull($detail['updated_by']);
    }

    public function testRejectedValidationLeavesNothingBehind(): void
    {
        $receptionsBefore = $this->db->table('receptions')->countAllResults();
        $itemsBefore      = $this->db->table('reception_items')->countAllResults();
        $logsBefore       = $this->db->table('audit_logs')->countAllResults();

        $result = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ['medicine_id' => 999, 'batch_no' => 'X-1', 'expires_on' => '2027-12-31', 'quantity' => 5],
        ]), $this->petugasId);

        $this->assertFalse($result['ok']);
        $this->assertSame(422, $result['status']);

        $this->assertSame($receptionsBefore, $this->db->table('receptions')->countAllResults());
        $this->assertSame($itemsBefore, $this->db->table('reception_items')->countAllResults());
        $this->assertSame($logsBefore, $this->db->table('audit_logs')->countAllResults());
    }

    public function testBatchSharedAcrossReceptionsAccumulates(): void
    {
        $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31', 'quantity' => 5],
        ]), $this->petugasId);

        $second = [
            'reference_no' => 'PB-002',
            'supplier_id'  => 1,
            'received_at'  => '2026-10-04T10:00:00+07:00',
            'items'        => [
                ['medicine_id' => 101, 'batch_no' => 'PCT-2602', 'expires_on' => '2028-03-31', 'quantity' => 7],
            ],
        ];

        $result = $this->receptions->create($second, $this->supervisorId);

        $this->assertTrue($result['ok']);
        $this->assertSame(146, $this->availableQuantity(101));
    }
}
