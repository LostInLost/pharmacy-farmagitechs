<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAuditPayloadToReceptionLogs extends Migration
{
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
     *
     * Memakai Forge, bukan SQL mentah, supaya tetap jalan di SQLite:
     * MySQL memakai ALTER TABLE, SQLite membangun ulang tabel.
     */
    private function replaceReceptionForeignKey(string $onDelete): void
    {
        foreach ($this->db->getForeignKeyData('reception_logs') as $name => $foreignKey) {
            if (in_array('reception_id', (array) $foreignKey->column_name, true)) {
                $this->forge->dropForeignKey('reception_logs', $name);
            }
        }

        $this->forge->addForeignKey('reception_id', 'receptions', 'id', 'CASCADE', $onDelete);
        $this->forge->processIndexes('reception_logs');
    }
}
