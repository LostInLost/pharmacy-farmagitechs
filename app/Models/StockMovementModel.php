<?php

namespace App\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * Ledger mutasi stok. `quantity` selalu positif; arah dibawa `direction`
 * (`in`/`out`) dan alasan gerak dibawa `movement_type` (`seed`/`receipt`/`usage`).
 */
class StockMovementModel extends Model
{
    protected $table         = 'stock_movements';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['medicine_id', 'batch_no', 'expires_on', 'movement_type', 'direction', 'quantity', 'reception_id', 'moved_at', 'unit_name', 'created_at'];
    protected $useTimestamps = false;

    private const TYPE_DIRECTION = ['seed' => 'in', 'receipt' => 'in', 'usage' => 'out'];

    protected $beforeInsert      = ['assertMovement'];
    protected $beforeInsertBatch = ['assertMovementBatch'];

    protected function assertMovement(array $eventData): array
    {
        $this->guard($eventData['data']);

        return $eventData;
    }

    protected function assertMovementBatch(array $eventData): array
    {
        foreach ($eventData['data'] as $row) {
            $this->guard($row);
        }

        return $eventData;
    }

    private function guard(array $row): void
    {
        $type      = (string) ($row['movement_type'] ?? '');
        $direction = (string) ($row['direction'] ?? '');

        if ((self::TYPE_DIRECTION[$type] ?? null) !== $direction) {
            throw new InvalidArgumentException("Arah gerak tidak sesuai tipe mutasi: {$type}/{$direction}.");
        }

        if ((int) ($row['quantity'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Jumlah gerak harus bilangan positif.');
        }
    }
}
