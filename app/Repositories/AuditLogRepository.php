<?php

namespace App\Repositories;

use App\Models\AuditLogModel;

/**
 * Tabel audit polimorfik: satu tabel untuk semua entitas yang diaudit,
 * dibedakan lewat pasangan `entity_type`/`entity_id`.
 */
class AuditLogRepository
{
    public function __construct(
        private readonly AuditLogModel $logs = new AuditLogModel(),
    ) {
    }

    public function forEntity(string $entityType, int $entityId): array
    {
        $rows = $this->logs
            ->select('audit_logs.*, users.name AS actor_name')
            ->join('users', 'users.id = audit_logs.actor_id', 'left')
            ->where('audit_logs.entity_type', $entityType)
            ->where('audit_logs.entity_id', $entityId)
            ->orderBy('audit_logs.created_at', 'ASC')
            ->orderBy('audit_logs.id', 'ASC')
            ->findAll();

        return array_map(static fn (array $row): array => [
            'id'          => (int) $row['id'],
            'actor_id'    => (int) $row['actor_id'],
            'actor_name'  => $row['actor_name'],
            'action'      => $row['action'],
            'data_before' => $row['data_before'],
            'data_after'  => $row['data_after'],
            'created_at'  => $row['created_at'],
        ], $rows);
    }

    public function record(string $entityType, int $entityId, int $actorId, string $action, ?array $before, ?array $after): void
    {
        $this->logs->insert([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'actor_id'    => $actorId,
            'action'      => $action,
            'data_before' => $before,
            'data_after'  => $after,
        ]);
    }
}
