<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSeedBatchStock extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'medicine_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'batch_no'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'expires_on'  => ['type' => 'DATE'],
            'quantity'    => ['type' => 'INT', 'constraint' => 11],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['medicine_id', 'batch_no']);
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('seed_batch_stock', true);
    }

    public function down()
    {
        $this->forge->dropTable('seed_batch_stock', true);
    }
}
