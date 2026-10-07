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
            $referenceNo = trim((string) $payload['reference_no']);
            $supplierId  = (int) $payload['supplier_id'];
            $receivedAt  = Time::parse($payload['received_at'], 'Asia/Jakarta')->toDateTimeString();
            $items       = $this->mapItems($payload['items']);

            $receptionId = $this->receptions->insertReception([
                'reference_no' => $referenceNo,
                'supplier_id'  => $supplierId,
                'received_at'  => $receivedAt,
                'created_by'   => $actorId,
            ]);

            $this->receptions->replaceItems($receptionId, $items);
            $this->receptions->replaceStockMovements($receptionId, $items, $receivedAt);
            $this->receptions->log(
                $receptionId,
                $actorId,
                'CREATE',
                null,
                $this->snapshot($referenceNo, $supplierId, $receivedAt, $items),
            );

            $db->transCommit();

            return ['ok' => true, 'id' => $receptionId];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => [lang('Reception.api.store_failed', [$e->getMessage()])], 'status' => 500];
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

                return ['ok' => false, 'errors' => [lang('Reception.api.not_found')], 'status' => 404];
            }

            if (! $this->policy->canUpdate($actor, $reception)) {
                $db->transRollback();

                return ['ok' => false, 'errors' => [lang('Reception.api.forbidden')], 'status' => 403];
            }

            $errors = $this->validator->validate($payload, $id);

            if ($errors !== []) {
                $db->transRollback();

                return ['ok' => false, 'errors' => $errors, 'status' => 422];
            }

            $referenceNo = trim((string) $payload['reference_no']);
            $supplierId  = (int) $payload['supplier_id'];
            $receivedAt  = Time::parse($payload['received_at'], 'Asia/Jakarta')->toDateTimeString();
            $items       = $this->mapItems($payload['items']);

            $before = $this->snapshot(
                $reception['reference_no'],
                $reception['supplier_id'],
                $reception['received_at'],
                $this->receptions->itemsOf($id),
            );

            $this->receptions->updateReception($id, [
                'reference_no' => $referenceNo,
                'supplier_id'  => $supplierId,
                'received_at'  => $receivedAt,
                'updated_by'   => (int) $actor['id'],
            ]);

            $this->receptions->replaceItems($id, $items);
            $this->receptions->replaceStockMovements($id, $items, $receivedAt);
            $this->receptions->log(
                $id,
                (int) $actor['id'],
                'UPDATE',
                $before,
                $this->snapshot($referenceNo, $supplierId, $receivedAt, $items),
            );

            $db->transCommit();

            return ['ok' => true];
        } catch (Throwable $e) {
            $db->transRollback();

            return ['ok' => false, 'errors' => [lang('Reception.api.update_failed', [$e->getMessage()])], 'status' => 500];
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

    /**
     * Snapshot ternormalisasi untuk audit: item diurutkan agar perbandingan
     * before/after stabil meski urutan kiriman berbeda.
     *
     * @param list<array{medicine_id: int, batch_no: string, expires_on: string, quantity: int}> $items
     */
    private function snapshot(string $referenceNo, int $supplierId, string $receivedAt, array $items): array
    {
        $items = array_map(static fn (array $item): array => [
            'medicine_id' => (int) $item['medicine_id'],
            'batch_no'    => $item['batch_no'],
            'expires_on'  => $item['expires_on'],
            'quantity'    => (int) $item['quantity'],
        ], $items);

        usort($items, static fn (array $a, array $b): int => [$a['medicine_id'], $a['batch_no']] <=> [$b['medicine_id'], $b['batch_no']]);

        return [
            'reference_no' => $referenceNo,
            'supplier_id'  => $supplierId,
            'received_at'  => $receivedAt,
            'items'        => $items,
        ];
    }
}
