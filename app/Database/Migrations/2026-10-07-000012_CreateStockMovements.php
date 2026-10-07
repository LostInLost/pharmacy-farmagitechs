<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ledger mutasi stok: satu baris per penambahan/pengurangan per batch.
 *
 * Tabel domain (`seed_batch_stock`, `reception_items`, `stock_usage`) tetap
 * menjadi sumber tulis; baris ledger ditulis bersamaan (write-through) dalam
 * transaksi yang sama, lalu seluruh laporan stok membaca tabel ini saja.
 *
 * `direction` menyimpan arah (`in`/`out`) dan `quantity` selalu positif,
 * sehingga arah tidak perlu ditebak dari tanda. `movement_type` menyimpan
 * alasan gerak (`seed`/`receipt`/`usage`). ENUM dihindari agar portabel
 * di MySQL maupun SQLite.
 *
 * Backfill menyalin data yang sudah ada: stok awal (2026-10-01), penerimaan
 * yang tercatat, dan pemakaian final. `created_at` disamakan dengan `moved_at`
 * agar backfill deterministik.
 */
class CreateStockMovements extends Migration
{
    private const SEED_MOVED_AT = '2026-10-01 00:00:00';

    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'medicine_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'batch_no'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'expires_on'    => ['type' => 'DATE', 'null' => true],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'direction'     => ['type' => 'VARCHAR', 'constraint' => 10],
            'quantity'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'reception_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'moved_at'      => ['type' => 'DATETIME'],
            'unit_name'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'    => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['medicine_id', 'batch_no']);
        $this->forge->addKey(['direction', 'moved_at']);
        $this->forge->addKey(['movement_type', 'moved_at']);
        $this->forge->addKey('reception_id');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reception_id', 'receptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('stock_movements', true);

        $this->backfill();
    }

    public function down()
    {
        $this->forge->dropTable('stock_movements', true);
    }

    private function backfill(): void
    {
        if ($this->db->table('stock_movements')->countAllResults() > 0) {
            return;
        }

        $rows = [];

        foreach ($this->db->table('seed_batch_stock')->get()->getResultArray() as $batch) {
            $rows[] = [
                'medicine_id'   => (int) $batch['medicine_id'],
                'batch_no'      => $batch['batch_no'],
                'expires_on'    => $batch['expires_on'],
                'movement_type' => 'seed',
                'direction'     => 'in',
                'quantity'      => (int) $batch['quantity'],
                'reception_id'  => null,
                'moved_at'      => self::SEED_MOVED_AT,
                'unit_name'     => null,
                'created_at'    => self::SEED_MOVED_AT,
            ];
        }

        $received = $this->db->table('reception_items AS ri')
            ->select('ri.medicine_id, ri.batch_no, ri.expires_on, ri.quantity, ri.reception_id, r.received_at')
            ->join('receptions AS r', 'r.id = ri.reception_id', 'inner')
            ->get()
            ->getResultArray();

        foreach ($received as $item) {
            $rows[] = [
                'medicine_id'   => (int) $item['medicine_id'],
                'batch_no'      => $item['batch_no'],
                'expires_on'    => $item['expires_on'],
                'movement_type' => 'receipt',
                'direction'     => 'in',
                'quantity'      => (int) $item['quantity'],
                'reception_id'  => (int) $item['reception_id'],
                'moved_at'      => $item['received_at'],
                'unit_name'     => null,
                'created_at'    => $item['received_at'],
            ];
        }

        foreach ($this->db->table('stock_usage')->get()->getResultArray() as $usage) {
            $movedAt = $usage['used_at'] ?? self::SEED_MOVED_AT;

            $rows[] = [
                'medicine_id'   => (int) $usage['medicine_id'],
                'batch_no'      => $usage['batch_no'],
                'expires_on'    => null,
                'movement_type' => 'usage',
                'direction'     => 'out',
                'quantity'      => (int) $usage['quantity'],
                'reception_id'  => null,
                'moved_at'      => $movedAt,
                'unit_name'     => $usage['unit_name'],
                'created_at'    => $movedAt,
            ];
        }

        if ($rows !== []) {
            $this->db->table('stock_movements')->insertBatch($rows);
        }
    }
}
