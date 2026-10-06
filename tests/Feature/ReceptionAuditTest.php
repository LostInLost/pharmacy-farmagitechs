<?php

use App\Services\ReceptionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Audit trail: snapshot sebelum/sesudah pada reception_logs.
 *
 * @internal
 */
final class ReceptionAuditTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private ReceptionService $receptions;
    private int $petugasId;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();

        $this->petugasId  = (int) $this->db->table('users')->where('username', 'petugas')->get()->getRowArray()['id'];
        $this->receptions = new ReceptionService();
    }

    private function payload(array $items, string $referenceNo = 'PB-001'): array
    {
        return [
            'reference_no' => $referenceNo,
            'supplier_id'  => 1,
            'received_at'  => '2026-10-03T10:00:00+07:00',
            'items'        => $items,
        ];
    }

    public function testCreateLogHasNullBeforeAndSnapshotAfter(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
        ]), $this->petugasId);

        $logs = $this->receptions->detail($created['id'])['logs'];

        $this->assertCount(1, $logs);
        $this->assertSame('CREATE', $logs[0]['action']);
        $this->assertNull($logs[0]['data_before']);

        $after = $logs[0]['data_after'];
        $this->assertSame('PB-001', $after['reference_no']);
        $this->assertSame(1, $after['supplier_id']);
        $this->assertSame('2026-10-03 10:00:00', $after['received_at']);
        $this->assertCount(2, $after['items']);
        // Kolom JSON MySQL menormalkan urutan key, jadi bandingkan nilai saja.
        $this->assertEquals(
            [
                ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
                ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
            ],
            $after['items'],
        );
    }

    public function testUpdateLogKeepsBeforeAndAfterSnapshots(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
        ]), $this->petugasId);

        $actor = ['id' => $this->petugasId, 'role' => 'reception'];

        $this->receptions->update($created['id'], $this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 7],
            ['medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30', 'quantity' => 3],
        ]), $actor);

        $logs = $this->receptions->detail($created['id'])['logs'];

        $this->assertCount(2, $logs);
        $this->assertSame('UPDATE', $logs[1]['action']);

        $before = $logs[1]['data_before'];
        $after  = $logs[1]['data_after'];

        $this->assertEquals([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
            ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
        ], $before['items']);

        $this->assertEquals([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 7],
            ['medicine_id' => 103, 'batch_no' => 'SAL-2601', 'expires_on' => '2027-11-30', 'quantity' => 3],
        ], $after['items']);

        $this->assertNotSame($before, $after);
    }

    public function testSnapshotsAreOrderIndependent(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 104, 'batch_no' => 'IBU-2602', 'expires_on' => '2028-06-30', 'quantity' => 5],
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $after = $this->receptions->detail($created['id'])['logs'][0]['data_after'];

        $this->assertSame([101, 104], array_column($after['items'], 'medicine_id'));
    }

    public function testIdenticalUpdateIsRecordedWithEqualSnapshots(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $actor   = ['id' => $this->petugasId, 'role' => 'reception'];
        $payload = $this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]);

        $this->receptions->update($created['id'], $payload, $actor);
        $this->receptions->update($created['id'], $payload, $actor);

        $logs = $this->receptions->detail($created['id'])['logs'];

        $this->assertCount(3, $logs);

        foreach ([$logs[1], $logs[2]] as $log) {
            $this->assertSame($log['data_before'], $log['data_after'], 'PUT identik harus menghasilkan snapshot before == after.');
        }
    }

    public function testRejectedUpdateWritesNoAuditRow(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $logsBefore = $this->db->table('reception_logs')->countAllResults();

        $actor  = ['id' => $this->petugasId, 'role' => 'reception'];
        $result = $this->receptions->update($created['id'], $this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 0],
        ]), $actor);

        $this->assertFalse($result['ok']);
        $this->assertSame(422, $result['status']);
        $this->assertSame($logsBefore, $this->db->table('reception_logs')->countAllResults());
    }

    public function testAuditRowsSurviveDeletingReception(): void
    {
        $created = $this->receptions->create($this->payload([
            ['medicine_id' => 101, 'batch_no' => 'PCT-2601', 'expires_on' => '2027-12-31', 'quantity' => 10],
        ]), $this->petugasId);

        $this->db->table('reception_items')->where('reception_id', $created['id'])->delete();

        $this->expectException(CodeIgniter\Database\Exceptions\DatabaseException::class);

        $this->db->table('receptions')->where('id', $created['id'])->delete();
    }

    public function testAuditColumnsAreNullableJson(): void
    {
        $fields = array_column($this->db->getFieldData('reception_logs'), 'type', 'name');

        $this->assertSame('json', strtolower((string) $fields['data_before']));
        $this->assertSame('json', strtolower((string) $fields['data_after']));
    }
}
