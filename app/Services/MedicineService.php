<?php

namespace App\Services;

use App\Policies\MedicinePolicy;
use App\Repositories\MedicineRepository;
use App\Validation\MedicineValidator;
use Throwable;

/**
 * Master obat. Berbeda dari penerimaan, di sini tidak ada penghapusan:
 * `medicines.id` dirujuk `reception_items` dan `stock_movements` dengan
 * foreign key RESTRICT, sehingga baris yang sudah pernah dipakai tidak dapat
 * dihapus tanpa merusak jejak stok. Obat yang tidak dipakai lagi ditandai
 * `is_active = 0` (nonaktif) dan otomatis hilang dari dropdown form
 * penerimaan, tetapi tetap tampil di halaman master agar riwayatnya terbaca.
 */
class MedicineService
{
    public function __construct(
        private readonly MedicineRepository $medicines = new MedicineRepository(),
        private readonly MedicineValidator $validator = new MedicineValidator(),
        private readonly MedicinePolicy $policy = new MedicinePolicy(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    public function list(?string $q = null, string $status = 'all'): array
    {
        return $this->medicines->findAll($q, $status);
    }

    /**
     * Detail + riwayat aksi. Daftar (`list()`) sengaja tanpa log: satu query
     * riwayat per baris tidak sepadan untuk halaman katalog.
     */
    public function detail(int $id): ?array
    {
        $medicine = $this->medicines->find($id);

        if ($medicine === null) {
            return null;
        }

        $medicine['logs'] = $this->audit->forEntity(AuditService::ENTITY_MEDICINE, $id);

        return $medicine;
    }

    /**
     * @return array{ok: bool, id?: int, errors?: list<string>, status?: int}
     */
    public function create(array $payload, array $actor): array
    {
        if (! $this->policy->canWrite($actor)) {
            return ['ok' => false, 'errors' => [lang('Medicine.api.forbidden')], 'status' => 403];
        }

        $errors = $this->validator->validate($payload);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'status' => 422];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $data = $this->map($payload);
            $id   = $this->medicines->insert($data);

            $this->audit->logCreated(AuditService::ENTITY_MEDICINE, $id, (int) $actor['id'], $data);

            $db->transCommit();

            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => [lang('Medicine.api.store_failed', [$e->getMessage()])], 'status' => 500];
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
            $before = $this->medicines->find($id);

            if ($before === null) {
                $db->transRollback();

                return ['ok' => false, 'errors' => [lang('Medicine.api.not_found')], 'status' => 404];
            }

            if (! $this->policy->canWrite($actor)) {
                $db->transRollback();

                return ['ok' => false, 'errors' => [lang('Medicine.api.forbidden')], 'status' => 403];
            }

            $errors = $this->validator->validate($payload, $id);

            if ($errors !== []) {
                $db->transRollback();

                return ['ok' => false, 'errors' => $errors, 'status' => 422];
            }

            $data = $this->map($payload, $before);

            $this->medicines->update($id, $data);
            $this->audit->logUpdated(AuditService::ENTITY_MEDICINE, $id, (int) $actor['id'], $before, $this->medicines->find($id) ?? $data);

            $db->transCommit();

            return ['ok' => true];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => [lang('Medicine.api.update_failed', [$e->getMessage()])], 'status' => 500];
        }
    }

    /**
     * `is_active` opsional: permintaan yang tidak menyebutnya sama sekali
     * (mis. klien lama) mempertahankan status tersimpan alih-alih diam-diam
     * menonaktifkan obat.
     *
     * @param array<string, mixed>|null $current
     *
     * @return array{code: string, name: string, unit: string, is_active: int}
     */
    private function map(array $payload, ?array $current = null): array
    {
        $isActive = array_key_exists('is_active', $payload)
            ? (bool) $payload['is_active']
            : ($current['is_active'] ?? true);

        return [
            'code'      => trim((string) $payload['code']),
            'name'      => trim((string) $payload['name']),
            'unit'      => trim((string) $payload['unit']),
            'is_active' => $isActive ? 1 : 0,
        ];
    }
}
