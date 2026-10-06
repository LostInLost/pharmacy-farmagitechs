<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAuditPayloadToReceptionLogs extends Migration
{
    private const FK_NAME = 'reception_logs_reception_id_foreign';

    public function up()
    {
        $this->forge->addColumn('reception_logs', [
            'data_before' => ['type' => 'JSON', 'null' => true, 'after' => 'action'],
            'data_after'  => ['type' => 'JSON', 'null' => true, 'after' => 'data_before'],
        ]);

        $this->replaceReceptionForeignKey('RESTRICT');
    }

    public function down()
    {
        $this->replaceReceptionForeignKey('CASCADE');

        $this->forge->dropColumn('reception_logs', ['data_before', 'data_after']);
    }

    /**
     * Jejak audit tidak boleh ikut terhapus saat penerimaan dihapus,
     * sehingga ON DELETE dipisahkan dari CASCADE milik reception_items.
     */
    private function replaceReceptionForeignKey(string $onDelete): void
    {
        $table = $this->db->DBPrefix . 'reception_logs';

        if ($this->foreignKeyExists($table, self::FK_NAME)) {
            $this->db->query(sprintf(
                'ALTER TABLE %s DROP FOREIGN KEY %s',
                $this->db->escapeIdentifiers($table),
                $this->db->escapeIdentifiers(self::FK_NAME),
            ));
        }

        $this->db->query(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s ON UPDATE CASCADE',
            $this->db->escapeIdentifiers($table),
            $this->db->escapeIdentifiers(self::FK_NAME),
            $this->db->escapeIdentifiers('reception_id'),
            $this->db->escapeIdentifiers($this->db->DBPrefix . 'receptions'),
            $this->db->escapeIdentifiers('id'),
            $onDelete,
        ));
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $row = $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'FOREIGN KEY'],
        )->getRowArray();

        return $row !== null;
    }
}
