<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMedicines extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 200],
            'unit'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('medicines', true);
    }

    public function down()
    {
        $this->forge->dropTable('medicines', true);
    }
}
