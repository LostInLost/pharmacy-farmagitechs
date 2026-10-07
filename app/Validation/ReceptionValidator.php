<?php

namespace App\Validation;

use App\Models\SupplierModel;
use App\Repositories\MedicineRepository;
use App\Repositories\StockRepository;
use CodeIgniter\I18n\Time;

/**
 * Aturan payload penerimaan. Pemeriksaan bentuk tiap baris berjalan di memori;
 * seluruh data yang dibutuhkan diambil sekaligus sebelum loop (status obat dan
 * kedaluwarsa batch), sehingga jumlah query tidak tumbuh mengikuti jumlah item.
 */
class ReceptionValidator
{
    public function __construct(
        private readonly SupplierModel $suppliers = new SupplierModel(),
        private readonly MedicineRepository $medicines = new MedicineRepository(),
        private readonly StockRepository $stocks = new StockRepository(),
    ) {
    }

    /**
     * @return list<string> daftar pesan kesalahan; kosong berarti valid
     */
    public function validate(array $payload, ?int $receptionId = null): array
    {
        $errors = [];

        $referenceNo = trim((string) ($payload['reference_no'] ?? ''));
        if ($referenceNo === '') {
            $errors[] = lang('Reception.validation.reference_required');
        } elseif ($this->referenceNoTaken($referenceNo, $receptionId)) {
            $errors[] = lang('Reception.validation.reference_taken', [$referenceNo]);
        }

        $supplierId = (int) ($payload['supplier_id'] ?? 0);
        $supplier   = $supplierId > 0 ? $this->suppliers->find($supplierId) : null;
        if ($supplier === null) {
            $errors[] = lang('Reception.validation.supplier_not_found');
        } elseif ((int) $supplier['is_active'] !== 1) {
            $errors[] = lang('Reception.validation.supplier_inactive');
        }

        $receivedAt = $this->parseReceivedAt($payload['received_at'] ?? null);
        if ($receivedAt === null) {
            $errors[] = lang('Reception.validation.received_at_invalid');
        }

        $items = $payload['items'] ?? null;
        if (! is_array($items) || $items === []) {
            $errors[] = lang('Reception.validation.items_required');

            return $errors;
        }

        $activeFlags = $this->medicines->activeFlags($this->medicineIds($items));
        $seenBatches = [];

        foreach ($items as $index => $item) {
            $line = lang('Reception.validation.line', [$index + 1]);

            $medicineId = (int) ($item['medicine_id'] ?? 0);
            $isActive   = $activeFlags[$medicineId] ?? null;

            if ($isActive === null) {
                $errors[] = lang('Reception.validation.medicine_not_found', [$line]);

                continue;
            }

            if ($isActive === false) {
                $errors[] = lang('Reception.validation.medicine_inactive', [$line]);
            }

            $batchNo = trim((string) ($item['batch_no'] ?? ''));
            if ($batchNo === '') {
                $errors[] = lang('Reception.validation.batch_required', [$line]);
            }

            $quantity = $item['quantity'] ?? null;
            if (! is_int($quantity) && ! (is_string($quantity) && ctype_digit($quantity))) {
                $errors[] = lang('Reception.validation.quantity_invalid', [$line]);
            } elseif ((int) $quantity <= 0) {
                $errors[] = lang('Reception.validation.quantity_invalid', [$line]);
            }

            $expiresOn = $this->parseDate($item['expires_on'] ?? null);
            if ($expiresOn === null) {
                $errors[] = lang('Reception.validation.expires_on_invalid', [$line]);
            }

            if ($batchNo !== '' && $expiresOn !== null) {
                $key = $medicineId . '|' . $batchNo;

                if (isset($seenBatches[$key])) {
                    $errors[] = lang('Reception.validation.batch_duplicated', [$line, $batchNo]);
                }
                $seenBatches[$key] = $expiresOn;

                if ($receivedAt !== null && $expiresOn <= $receivedAt->toDateString()) {
                    $errors[] = lang('Reception.validation.expires_before_receipt', [$line]);
                }
            }
        }

        return array_merge($errors, $this->batchExpiryConflicts($seenBatches));
    }

    /**
     * @param array<int, mixed> $items
     *
     * @return list<int>
     */
    private function medicineIds(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $ids[(int) ($item['medicine_id'] ?? 0)] = true;
        }

        return array_values(array_filter(array_keys($ids), static fn (int $id): bool => $id > 0));
    }

    /**
     * @param array<string, string> $seenBatches
     *
     * @return list<string>
     */
    private function batchExpiryConflicts(array $seenBatches): array
    {
        if ($seenBatches === []) {
            return [];
        }

        $batches = [];

        foreach (array_keys($seenBatches) as $key) {
            [$medicineId, $batchNo] = explode('|', $key, 2);
            $batches[]               = ['medicine_id' => (int) $medicineId, 'batch_no' => $batchNo];
        }

        $known  = $this->stocks->knownExpiries($batches);
        $errors = [];

        foreach ($seenBatches as $key => $expiresOn) {
            $existing = $known[$key] ?? null;

            if ($existing !== null && $existing !== $expiresOn) {
                [$medicineId, $batchNo] = explode('|', $key, 2);
                $errors[]               = lang('Reception.validation.batch_expiry_conflict', [$batchNo, $medicineId, $existing, $expiresOn]);
            }
        }

        return $errors;
    }

    private function referenceNoTaken(string $referenceNo, ?int $receptionId): bool
    {
        $builder = db_connect()->table('receptions')->where('reference_no', $referenceNo);

        if ($receptionId !== null) {
            $builder->where('id !=', $receptionId);
        }

        return $builder->countAllResults() > 0;
    }

    private function parseReceivedAt(mixed $value): ?Time
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Time::parse($value, 'Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
