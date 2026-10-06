<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kolom pelengkap pemakaian obat dari lampiran: waktu pakai dan unit pelayanan.
 * Aplikasi hanya membaca quantity untuk laporan stok; kolom ini menjaga data
 * lampiran tetap utuh. Dibuat nullable agar insert tanpa kolom ini tetap sah.
 */
class AddUsageDetailsToStockUsage extends Migration
{
    public function up()
    {
        $this->forge->addColumn('stock_usage', [
            'used_at'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'batch_no'],
            'unit_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'used_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('stock_usage', ['used_at', 'unit_name']);
    }
}
