<?php

namespace App\Repositories;

use App\Models\ReceptionItemModel;
use App\Models\ReceptionLogModel;
use App\Models\ReceptionModel;

class ReceptionRepository
{
    public function __construct(
        private readonly ReceptionModel $receptions = new ReceptionModel(),
        private readonly ReceptionItemModel $items = new ReceptionItemModel(),
        private readonly ReceptionLogModel $logs = new ReceptionLogModel(),
    ) {
    }

    public function find(int $id): ?array
    {
        $row = $this->receptions
            ->select('receptions.*, suppliers.name AS supplier_name, creator.name AS created_by_name, editor.name AS updated_by_name')
            ->join('suppliers', 'suppliers.id = receptions.supplier_id', 'left')
            ->join('users AS creator', 'creator.id = receptions.created_by', 'left')
            ->join('users AS editor', 'editor.id = receptions.updated_by', 'left')
            ->find($id);

        return $row === null ? null : $this->normalizeReception($row);
    }

    public function findAll(): array
    {
        $rows = $this->receptions
            ->select('receptions.*, suppliers.name AS supplier_name, creator.name AS created_by_name, editor.name AS updated_by_name')
            ->join('suppliers', 'suppliers.id = receptions.supplier_id', 'left')
            ->join('users AS creator', 'creator.id = receptions.created_by', 'left')
            ->join('users AS editor', 'editor.id = receptions.updated_by', 'left')
            ->orderBy('receptions.received_at', 'DESC')
            ->orderBy('receptions.id', 'DESC')
            ->findAll();

        return array_map(fn (array $row): array => $this->normalizeReception($row), $rows);
    }

    public function itemsOf(int $receptionId): array
    {
        $rows = $this->items
            ->select('reception_items.*, medicines.code AS medicine_code, medicines.name AS medicine_name, medicines.unit')
            ->join('medicines', 'medicines.id = reception_items.medicine_id', 'left')
            ->where('reception_items.reception_id', $receptionId)
            ->orderBy('reception_items.id', 'ASC')
            ->findAll();

        return array_map(static fn (array $row): array => [
            'id'            => (int) $row['id'],
            'medicine_id'   => (int) $row['medicine_id'],
            'medicine_code' => $row['medicine_code'],
            'medicine_name' => $row['medicine_name'],
            'unit'          => $row['unit'],
            'batch_no'      => $row['batch_no'],
            'expires_on'    => $row['expires_on'],
            'quantity'      => (int) $row['quantity'],
        ], $rows);
    }

    public function logsOf(int $receptionId): array
    {
        $rows = $this->logs
            ->select('reception_logs.*, users.name AS actor_name')
            ->join('users', 'users.id = reception_logs.actor_id', 'left')
            ->where('reception_logs.reception_id', $receptionId)
            ->orderBy('reception_logs.created_at', 'ASC')
            ->orderBy('reception_logs.id', 'ASC')
            ->findAll();

        return array_map(static fn (array $row): array => [
            'id'         => (int) $row['id'],
            'actor_id'   => (int) $row['actor_id'],
            'actor_name' => $row['actor_name'],
            'action'     => $row['action'],
            'created_at' => $row['created_at'],
        ], $rows);
    }

    public function insertReception(array $data): int
    {
        return (int) $this->receptions->insert($data);
    }

    public function updateReception(int $id, array $data): void
    {
        $this->receptions->update($id, $data);
    }

    public function replaceItems(int $receptionId, array $items): void
    {
        $this->items->where('reception_id', $receptionId)->delete();

        if ($items === []) {
            return;
        }

        $rows = array_map(static fn (array $item): array => $item + ['reception_id' => $receptionId], $items);

        $this->items->insertBatch($rows);
    }

    public function log(int $receptionId, int $actorId, string $action): void
    {
        $this->logs->insert([
            'reception_id' => $receptionId,
            'actor_id'     => $actorId,
            'action'       => $action,
        ]);
    }

    private function normalizeReception(array $row): array
    {
        return [
            'id'               => (int) $row['id'],
            'reference_no'     => $row['reference_no'],
            'supplier_id'      => (int) $row['supplier_id'],
            'supplier_name'    => $row['supplier_name'] ?? null,
            'received_at'      => $row['received_at'],
            'created_by'       => (int) $row['created_by'],
            'created_by_name'  => $row['created_by_name'] ?? null,
            'created_at'       => $row['created_at'],
            'updated_by'       => $row['updated_by'] === null ? null : (int) $row['updated_by'],
            'updated_by_name'  => $row['updated_by_name'] ?? null,
            'updated_at'       => $row['updated_at'],
        ];
    }
}
