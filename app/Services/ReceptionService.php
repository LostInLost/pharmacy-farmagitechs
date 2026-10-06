<?php

namespace App\Services;

use App\Policies\ReceptionPolicy;
use App\Repositories\ReceptionRepository;
use App\Validation\ReceptionValidator;
use CodeIgniter\I18n\Time;
use Throwable;

class ReceptionService
{
    public function __construct(
        private readonly ReceptionRepository $receptions = new ReceptionRepository(),
        private readonly ReceptionValidator $validator = new ReceptionValidator(),
        private readonly ReceptionPolicy $policy = new ReceptionPolicy(),
    ) {
    }

    public function list(): array
    {
        return $this->receptions->findAll();
    }

    public function detail(int $id): ?array
    {
        $reception = $this->receptions->find($id);

        if ($reception === null) {
            return null;
        }

        $reception['items'] = $this->receptions->itemsOf($id);
        $reception['logs']  = $this->receptions->logsOf($id);

        return $reception;
    }

    /**
     * @return array{ok: bool, id?: int, errors?: list<string>, status?: int}
     */
    public function create(array $payload, int $actorId): array
    {
        $errors = $this->validator->validate($payload);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'status' => 422];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $receptionId = $this->receptions->insertReception([
                'reference_no' => trim((string) $payload['reference_no']),
                'supplier_id'  => (int) $payload['supplier_id'],
                'received_at'  => Time::parse($payload['received_at'], 'Asia/Jakarta')->toDateTimeString(),
                'created_by'   => $actorId,
            ]);

            $this->receptions->replaceItems($receptionId, $this->mapItems($payload['items']));
            $this->receptions->log($receptionId, $actorId, 'CREATE');

            $db->transCommit();

            return ['ok' => true, 'id' => $receptionId];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => ['Gagal menyimpan penerimaan: ' . $e->getMessage()], 'status' => 500];
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
            $reception = $this->receptions->find($id);

            if ($reception === null) {
                $db->transRollback();

                return ['ok' => false, 'errors' => ['Penerimaan tidak ditemukan.'], 'status' => 404];
            }

            if (! $this->policy->canUpdate($actor, $reception)) {
                $db->transRollback();

                return ['ok' => false, 'errors' => ['Anda tidak berhak mengubah penerimaan ini.'], 'status' => 403];
            }

            $errors = $this->validator->validate($payload, $id);

            if ($errors !== []) {
                $db->transRollback();

                return ['ok' => false, 'errors' => $errors, 'status' => 422];
            }

            $this->receptions->updateReception($id, [
                'reference_no' => trim((string) $payload['reference_no']),
                'supplier_id'  => (int) $payload['supplier_id'],
                'received_at'  => Time::parse($payload['received_at'], 'Asia/Jakarta')->toDateTimeString(),
                'updated_by'   => (int) $actor['id'],
            ]);

            $this->receptions->replaceItems($id, $this->mapItems($payload['items']));
            $this->receptions->log($id, (int) $actor['id'], 'UPDATE');

            $db->transCommit();

            return ['ok' => true];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => ['Gagal memperbarui penerimaan: ' . $e->getMessage()], 'status' => 500];
        }
    }

    private function mapItems(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'medicine_id' => (int) $item['medicine_id'],
            'batch_no'    => trim((string) $item['batch_no']),
            'expires_on'  => $item['expires_on'],
            'quantity'    => (int) $item['quantity'],
        ], array_values($items));
    }
}
