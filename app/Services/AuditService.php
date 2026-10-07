<?php

namespace App\Services;

use App\Repositories\AuditLogRepository;

/**
 * Satu pintu penulisan jejak audit. Tidak membuka transaksi sendiri:
 * pemanggil yang memegang transaksi, sehingga baris log ikut batal
 * saat operasi gagal.
 */
class AuditService
{
    public const ENTITY_RECEPTION = 'reception';

    public function __construct(
        private readonly AuditLogRepository $logs = new AuditLogRepository(),
    ) {
    }

    public function forEntity(string $entityType, int $entityId): array
    {
        return $this->logs->forEntity($entityType, $entityId);
    }

    public function logCreated(string $entityType, int $entityId, int $actorId, array $after): void
    {
        $this->logs->record($entityType, $entityId, $actorId, 'CREATE', null, $after);
    }

    public function logUpdated(string $entityType, int $entityId, int $actorId, array $before, array $after): void
    {
        $this->logs->record($entityType, $entityId, $actorId, 'UPDATE', $before, $after);
    }

    public function logDeleted(string $entityType, int $entityId, int $actorId, array $before): void
    {
        $this->logs->record($entityType, $entityId, $actorId, 'DELETE', $before, null);
    }
}
