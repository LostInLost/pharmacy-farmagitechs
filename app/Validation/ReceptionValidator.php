<?php

namespace App\Validation;

use App\Models\MedicineModel;
use App\Models\SupplierModel;
use CodeIgniter\I18n\Time;

class ReceptionValidator
{
    public function __construct(
        private readonly SupplierModel $suppliers = new SupplierModel(),
        private readonly MedicineModel $medicines = new MedicineModel(),
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

        $seenBatches = [];

        foreach ($items as $index => $item) {
            $line = lang('Reception.validation.line', [$index + 1]);

            $medicineId = (int) ($item['medicine_id'] ?? 0);
            $medicine   = $medicineId > 0 ? $this->medicines->find($medicineId) : null;

            if ($medicine === null) {
                $errors[] = lang('Reception.validation.medicine_not_found', [$line]);

                continue;
            }

            if ((int) $medicine['is_active'] !== 1) {
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

        $errors = array_merge($errors, $this->batchExpiryConflicts($seenBatches));

        return $errors;
    }

    /**
     * @param array<string, string> $seenBatches
     *
     * @return list<string>
     */
    private function batchExpiryConflicts(array $seenBatches): array
    {
        $errors = [];

        foreach ($seenBatches as $key => $expiresOn) {
            [$medicineId, $batchNo] = explode('|', $key, 2);

            $existing = $this->knownExpiry((int) $medicineId, $batchNo);

            if ($existing !== null && $existing !== $expiresOn) {
                $errors[] = lang('Reception.validation.batch_expiry_conflict', [$batchNo, $medicineId, $existing, $expiresOn]);
            }
        }

        return $errors;
    }

    private function knownExpiry(int $medicineId, string $batchNo): ?string
    {
        $db = db_connect();

        $seed = $db->table('seed_batch_stock')
            ->select('expires_on')
            ->where('medicine_id', $medicineId)
            ->where('batch_no', $batchNo)
            ->get()
            ->getRowArray();

        if ($seed !== null) {
            return $seed['expires_on'];
        }

        $received = $db->table('reception_items')
            ->select('expires_on')
            ->where('medicine_id', $medicineId)
            ->where('batch_no', $batchNo)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        return $received['expires_on'] ?? null;
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
