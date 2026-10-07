<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nama pemasok menjadi unik (case-insensitive mengikuti collation `_ci`)
 * supaya validasi master pemasok dapat menolak duplikat dengan pesan 422,
 * bukan gagal di database sebagai error 500.
 */
class AddUniqueNameToSuppliers extends Migration
{
    public function up()
    {
        $this->forge->addUniqueKey('name', 'suppliers_name_unique');
        $this->forge->processIndexes('suppliers');
    }

    public function down()
    {
        $this->forge->dropKey('suppliers', 'suppliers_name_unique');
    }
}
