<?php

use App\Models\AuditLogModel;
use App\Models\ReceptionModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class TimestampStampingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private function seedDependencies(): array
    {
        $this->db->table('users')->insertBatch([
            ['name' => 'Petugas', 'username' => 'petugas', 'email' => 'petugas@test.local', 'password_hash' => 'x', 'role' => 'reception', 'is_active' => 1],
            ['name' => 'Supervisor', 'username' => 'supervisor', 'email' => 'supervisor@test.local', 'password_hash' => 'x', 'role' => 'supervisor', 'is_active' => 1],
        ]);
        $this->db->table('suppliers')->insert(['name' => 'PT Uji', 'is_active' => 1]);

        return [
            'user'     => (int) $this->db->insertID(),
            'supplier' => 1,
        ];
    }

    public function testInsertStampsCreatedAt(): void
    {
        ['user' => $userId, 'supplier' => $supplierId] = $this->seedDependencies();

        $model = new ReceptionModel();
        $id    = $model->insert([
            'reference_no' => 'PB-STAMP-1',
            'supplier_id'  => $supplierId,
            'received_at'  => '2026-10-03 10:00:00',
            'created_by'   => $userId,
        ]);

        $row = $model->find($id);

        $this->assertNotNull($row['created_at']);
        $this->assertSame('Asia/Jakarta', Time::now()->getTimezoneName());
    }

    public function testInsertLeavesUpdatedAtNull(): void
    {
        ['user' => $userId, 'supplier' => $supplierId] = $this->seedDependencies();

        $model = new ReceptionModel();
        $id    = $model->insert([
            'reference_no' => 'PB-STAMP-2',
            'supplier_id'  => $supplierId,
            'received_at'  => '2026-10-03 10:00:00',
            'created_by'   => $userId,
        ]);

        $row = $model->find($id);

        $this->assertNull($row['updated_at']);
        $this->assertNull($row['updated_by']);
    }

    public function testUpdateStampsUpdatedAt(): void
    {
        ['user' => $userId, 'supplier' => $supplierId] = $this->seedDependencies();

        $model = new ReceptionModel();
        $id    = $model->insert([
            'reference_no' => 'PB-STAMP-3',
            'supplier_id'  => $supplierId,
            'received_at'  => '2026-10-03 10:00:00',
            'created_by'   => $userId,
        ]);

        $model->update($id, ['updated_by' => $userId]);

        $row = $model->find($id);

        $this->assertNotNull($row['updated_at']);
        $this->assertSame($userId, (int) $row['updated_by']);
    }

    public function testInsertBatchStampsCreatedAt(): void
    {
        ['user' => $userId, 'supplier' => $supplierId] = $this->seedDependencies();

        $model = new ReceptionModel();
        $model->insertBatch([
            [
                'reference_no' => 'PB-STAMP-4',
                'supplier_id'  => $supplierId,
                'received_at'  => '2026-10-03 10:00:00',
                'created_by'   => $userId,
            ],
            [
                'reference_no' => 'PB-STAMP-5',
                'supplier_id'  => $supplierId,
                'received_at'  => '2026-10-04 10:00:00',
                'created_by'   => $userId,
            ],
        ]);

        $rows = $model->whereIn('reference_no', ['PB-STAMP-4', 'PB-STAMP-5'])->findAll();

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertNotNull($row['created_at']);
            $this->assertNull($row['updated_at']);
        }
    }

    public function testExplicitTimestampIsNotOverwritten(): void
    {
        ['user' => $userId, 'supplier' => $supplierId] = $this->seedDependencies();

        $model = new ReceptionModel();
        $id    = $model->insert([
            'reference_no' => 'PB-STAMP-6',
            'supplier_id'  => $supplierId,
            'received_at'  => '2026-10-03 10:00:00',
            'created_by'   => $userId,
            'created_at'   => '2020-01-01 00:00:00',
        ]);

        $row = $model->find($id);

        $this->assertSame('2020-01-01 00:00:00', $row['created_at']);
    }

    public function testAuditLogStampsCreatedAt(): void
    {
        ['user' => $userId, 'supplier' => $supplierId] = $this->seedDependencies();

        $receptions = new ReceptionModel();
        $receptionId = $receptions->insert([
            'reference_no' => 'PB-STAMP-7',
            'supplier_id'  => $supplierId,
            'received_at'  => '2026-10-03 10:00:00',
            'created_by'   => $userId,
        ]);

        $logs = new AuditLogModel();
        $logId = $logs->insert([
            'entity_type' => 'reception',
            'entity_id'   => $receptionId,
            'actor_id'    => $userId,
            'action'      => 'Audit.receptions.action.create',
        ]);

        $row = $logs->find($logId);

        $this->assertNotNull($row['created_at']);
    }
}
