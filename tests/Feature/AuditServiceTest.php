<?php

use App\Services\AuditService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Kontrak AuditService: pemetaan aksi ke snapshot, dan kepatuhan pada
 * transaksi pemanggil.
 *
 * @internal
 */
final class AuditServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private AuditService $audit;
    private int $actorId;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();

        $this->actorId = (int) $this->db->table('users')->where('username', 'petugas')->get()->getRowArray()['id'];
        $this->audit   = new AuditService();
    }

    private function row(): array
    {
        return $this->db->table('audit_logs')->get()->getRowArray();
    }

    public function testLogCreatedStoresNullBefore(): void
    {
        $this->audit->logCreated('medicine', 101, $this->actorId, ['quantity' => 10]);

        $row = $this->row();

        $this->assertSame('Audit.medicines.action.create', $row['action']);
        $this->assertNull($row['data_before']);
        $this->assertSame(10, json_decode($row['data_after'], true)['quantity']);
    }

    public function testLogUpdatedStoresBothSnapshots(): void
    {
        $this->audit->logUpdated('medicine', 101, $this->actorId, ['quantity' => 10], ['quantity' => 7]);

        $row = $this->row();

        $this->assertSame('Audit.medicines.action.update', $row['action']);
        $this->assertSame(10, json_decode($row['data_before'], true)['quantity']);
        $this->assertSame(7, json_decode($row['data_after'], true)['quantity']);
    }

    public function testLogDeletedStoresNullAfter(): void
    {
        $this->audit->logDeleted('medicine', 101, $this->actorId, ['quantity' => 10]);

        $row = $this->row();

        $this->assertSame('Audit.medicines.action.delete', $row['action']);
        $this->assertSame(10, json_decode($row['data_before'], true)['quantity']);
        $this->assertNull($row['data_after']);
    }

    public function testRowsAreScopedPerEntity(): void
    {
        $this->audit->logCreated('medicine', 101, $this->actorId, ['quantity' => 10]);
        $this->audit->logCreated('medicine', 102, $this->actorId, ['quantity' => 20]);
        $this->audit->logCreated('supplier', 101, $this->actorId, ['name' => 'Farma Nusantara']);

        $this->assertCount(1, $this->audit->forEntity('medicine', 101));
        $this->assertCount(1, $this->audit->forEntity('medicine', 102));
        $this->assertCount(1, $this->audit->forEntity('supplier', 101));
        $this->assertSame([], $this->audit->forEntity('medicine', 999));
    }

    public function testForEntityReturnsActorNameAndChronologicalOrder(): void
    {
        $this->audit->logCreated('medicine', 101, $this->actorId, ['quantity' => 10]);
        $this->audit->logUpdated('medicine', 101, $this->actorId, ['quantity' => 10], ['quantity' => 7]);

        $logs = $this->audit->forEntity('medicine', 101);

        $this->assertSame(['Audit.medicines.action.create', 'Audit.medicines.action.update'], array_column($logs, 'action'));
        $this->assertSame('Dewi Petugas', $logs[0]['actor_name']);
    }

    public function testLogIsDiscardedWhenCallerRollsBack(): void
    {
        $db = db_connect();
        $db->transBegin();
        $this->audit->logCreated('medicine', 101, $this->actorId, ['quantity' => 10]);
        $db->transRollback();

        $this->assertSame(0, $this->db->table('audit_logs')->countAllResults());
    }
}
