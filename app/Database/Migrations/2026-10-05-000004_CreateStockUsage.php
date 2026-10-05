<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockUsage extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'medicine_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'batch_no'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'quantity'    => ['type' => 'INT', 'constraint' => 11],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['medicine_id', 'batch_no']);
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stock_usage', true);
    }

    public function down()
    {
        $this->forge->dropTable('stock_usage', true);
    }
}
