<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceptionItems extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'reception_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'medicine_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'batch_no'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'expires_on'  => ['type' => 'DATE'],
            'quantity'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['reception_id', 'medicine_id', 'batch_no']);
        $this->forge->addKey(['medicine_id', 'batch_no']);
        $this->forge->addForeignKey('reception_id', 'receptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('reception_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('reception_items', true);
    }
}
