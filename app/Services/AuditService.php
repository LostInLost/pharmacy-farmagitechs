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
    public const ENTITY_MEDICINE  = 'medicine';

    /**
     * entity_type => grup kunci i18n di berkas `Audit.php`. Entitas yang
     * belum terdaftar memakai nama entity_type-nya sendiri; kuncinya tetap
     * tersimpan apa adanya dan tampil mentah sampai labelnya dibuat.
     *
     * @var array<string, string>
     */
    private const KEY_GROUPS = [
        self::ENTITY_RECEPTION => 'receptions',
        self::ENTITY_MEDICINE  => 'medicines',
    ];

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
        $this->logs->record($entityType, $entityId, $actorId, $this->key($entityType, 'create'), null, $after);
    }

    public function logUpdated(string $entityType, int $entityId, int $actorId, array $before, array $after): void
    {
        $this->logs->record($entityType, $entityId, $actorId, $this->key($entityType, 'update'), $before, $after);
    }

    public function logDeleted(string $entityType, int $entityId, int $actorId, array $before): void
    {
        $this->logs->record($entityType, $entityId, $actorId, $this->key($entityType, 'delete'), $before, null);
    }

    /**
     * Kunci i18n yang disimpan di `audit_logs.action`, mis.
     * `Audit.receptions.action.create`. Segmen berkas sengaja kapital:
     * `lang()` mencari `Language/{locale}/{file}.php` apa adanya, sehingga
     * kunci huruf kecil gagal dimuat di sistem berkas case-sensitive.
     */
    private function key(string $entityType, string $action): string
    {
        $group = self::KEY_GROUPS[$entityType] ?? $entityType;

        return 'Audit.' . $group . '.action.' . $action;
    }
}
