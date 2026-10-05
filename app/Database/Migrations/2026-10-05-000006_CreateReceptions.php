<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceptions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 50],
            'supplier_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'received_at'  => ['type' => 'DATETIME'],
            'created_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'updated_by'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('reference_no');
        $this->forge->addKey(['supplier_id', 'received_at']);
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('receptions', true);
    }

    public function down()
    {
        $this->forge->dropTable('receptions', true);
    }
}
