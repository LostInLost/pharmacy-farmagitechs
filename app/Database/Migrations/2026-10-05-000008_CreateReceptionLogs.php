<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceptionLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'reception_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'actor_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'action'       => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE']],
            'created_at'   => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['reception_id', 'created_at']);
        $this->forge->addForeignKey('reception_id', 'receptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('reception_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('reception_logs', true);
    }
}
