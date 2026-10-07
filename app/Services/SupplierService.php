<?php

namespace App\Services;

use App\Policies\SupplierPolicy;
use App\Repositories\SupplierRepository;
use App\Validation\SupplierValidator;
use Throwable;

/**
 * Master pemasok. Berbeda dari penerimaan, di sini tidak ada penghapusan:
 * `suppliers.id` dirujuk `receptions.supplier_id` dengan foreign key RESTRICT,
 * sehingga pemasok yang sudah pernah dipakai tidak dapat dihapus tanpa
 * merusak riwayat penerimaan. Pemasok yang tidak dipakai lagi ditandai
 * `is_active = 0` (nonaktif) dan otomatis hilang dari dropdown form
 * penerimaan, tetapi tetap tampil di halaman master agar riwayatnya terbaca.
 */
class SupplierService
{
    public function __construct(
        private readonly SupplierRepository $suppliers = new SupplierRepository(),
        private readonly SupplierValidator $validator = new SupplierValidator(),
        private readonly SupplierPolicy $policy = new SupplierPolicy(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    public function list(?string $q = null, string $status = 'all'): array
    {
        return $this->suppliers->findAll($q, $status);
    }

    /**
     * Detail + riwayat aksi. Daftar (`list()`) sengaja tanpa log: satu query
     * riwayat per baris tidak sepadan untuk halaman katalog.
     */
    public function detail(int $id): ?array
    {
        $supplier = $this->suppliers->find($id);

        if ($supplier === null) {
            return null;
        }

        $supplier['logs'] = $this->audit->forEntity(AuditService::ENTITY_SUPPLIER, $id);

        return $supplier;
    }

    /**
     * @return array{ok: bool, id?: int, errors?: list<string>, status?: int}
     */
    public function create(array $payload, array $actor): array
    {
        if (! $this->policy->canWrite($actor)) {
            return ['ok' => false, 'errors' => [lang('Supplier.api.forbidden')], 'status' => 403];
        }

        $errors = $this->validator->validate($payload);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'status' => 422];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $data = $this->map($payload);
            $id   = $this->suppliers->insert($data);

            $this->audit->logCreated(AuditService::ENTITY_SUPPLIER, $id, (int) $actor['id'], $data);

            $db->transCommit();

            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => [lang('Supplier.api.store_failed', [$e->getMessage()])], 'status' => 500];
        }
    }

    /**
     * @return array{ok: bool, errors?: list<string>, status?: int}
     */
    public function update(int $id, array $payload, array $actor): array
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $before = $this->suppliers->find($id);

            if ($before === null) {
                $db->transRollback();

                return ['ok' => false, 'errors' => [lang('Supplier.api.not_found')], 'status' => 404];
            }

            if (! $this->policy->canWrite($actor)) {
                $db->transRollback();

                return ['ok' => false, 'errors' => [lang('Supplier.api.forbidden')], 'status' => 403];
            }

            $errors = $this->validator->validate($payload, $id);

            if ($errors !== []) {
                $db->transRollback();

                return ['ok' => false, 'errors' => $errors, 'status' => 422];
            }

            $data = $this->map($payload, $before);

            $this->suppliers->update($id, $data);
            $this->audit->logUpdated(AuditService::ENTITY_SUPPLIER, $id, (int) $actor['id'], $before, $this->suppliers->find($id) ?? $data);

            $db->transCommit();

            return ['ok' => true];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => [lang('Supplier.api.update_failed', [$e->getMessage()])], 'status' => 500];
        }
    }

    /**
     * `is_active` opsional: permintaan yang tidak menyebutnya sama sekali
     * (mis. klien lama) mempertahankan status tersimpan alih-alih diam-diam
     * menonaktifkan pemasok.
     *
     * @param array<string, mixed>|null $current
     *
     * @return array{name: string, is_active: int}
     */
    private function map(array $payload, ?array $current = null): array
    {
        $isActive = array_key_exists('is_active', $payload)
            ? (bool) $payload['is_active']
            : ($current['is_active'] ?? true);

        return [
            'name'      => trim((string) $payload['name']),
            'is_active' => $isActive ? 1 : 0,
        ];
    }
}
