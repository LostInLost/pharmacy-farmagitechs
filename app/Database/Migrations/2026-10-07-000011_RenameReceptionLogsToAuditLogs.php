<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * reception_logs -> audit_logs: log aksi menjadi generik lewat pasangan
 * (entity_type, entity_id) sehingga domain tulis lain dapat memakai tabel
 * yang sama tanpa membuat tabel log baru.
 *
 * Tabel dibangun ulang lewat Forge (bukan ALTER TABLE mentah) agar tetap
 * jalan di SQLite, dan `entity_id` sengaja tanpa foreign key: satu kolom
 * menunjuk ke banyak tabel, sehingga jejak audit tetap hidup ketika
 * entitasnya dihapus.
 */
class RenameReceptionLogsToAuditLogs extends Migration
{
    private const ENTITY_RECEPTION = 'reception';

    public function up()
    {
        $this->forge->addField($this->auditFields());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entity_type', 'entity_id', 'created_at']);
        $this->forge->addForeignKey('actor_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('audit_logs');

        $rows = [];

        foreach ($this->db->table('reception_logs')->get()->getResultArray() as $row) {
            $rows[] = [
                'id'          => (int) $row['id'],
                'entity_type' => self::ENTITY_RECEPTION,
                'entity_id'   => (int) $row['reception_id'],
                'actor_id'    => (int) $row['actor_id'],
                'action'      => $row['action'],
                'data_before' => $row['data_before'],
                'data_after'  => $row['data_after'],
                'created_at'  => $row['created_at'],
            ];
        }

        if ($rows !== []) {
            $this->db->table('audit_logs')->insertBatch($rows);
        }

        $this->forge->dropTable('reception_logs', true);
    }

    /**
     * Hanya baris `reception` yang punya padanan di reception_logs, dan hanya
     * selama reception-nya masih ada: skema lama mewajibkan FK ke `receptions`,
     * sehingga log yatim (reception sudah dihapus) tidak punya tempat dan
     * hilang bersama audit_logs.
     */
    public function down()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'reception_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'actor_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'action'       => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE', 'DELETE']],
            'data_before'  => ['type' => 'JSON', 'null' => true],
            'data_after'   => ['type' => 'JSON', 'null' => true],
            'created_at'   => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['reception_id', 'created_at']);
        $this->forge->addForeignKey('reception_id', 'receptions', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('actor_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('reception_logs');

        $rows = [];

        $logs = $this->db->table('audit_logs')
            ->select('audit_logs.*')
            ->join('receptions', 'receptions.id = audit_logs.entity_id')
            ->where('audit_logs.entity_type', self::ENTITY_RECEPTION)
            ->get()
            ->getResultArray();

        foreach ($logs as $row) {
            $rows[] = [
                'id'           => (int) $row['id'],
                'reception_id' => (int) $row['entity_id'],
                'actor_id'     => (int) $row['actor_id'],
                'action'       => $row['action'],
                'data_before'  => $row['data_before'],
                'data_after'   => $row['data_after'],
                'created_at'   => $row['created_at'],
            ];
        }

        if ($rows !== []) {
            $this->db->table('reception_logs')->insertBatch($rows);
        }

        $this->forge->dropTable('audit_logs', true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function auditFields(): array
    {
        return [
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'entity_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'actor_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'action'      => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE', 'DELETE']],
            'data_before' => ['type' => 'JSON', 'null' => true],
            'data_after'  => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME'],
        ];
    }
}
