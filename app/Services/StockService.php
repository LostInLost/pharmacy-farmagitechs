<?php

namespace App\Services;

use App\Repositories\StockRepository;
use CodeIgniter\I18n\Time;

class StockService
{
    public function __construct(
        private readonly StockRepository $stocks = new StockRepository(),
    ) {
    }

    public function report(?string $onDate = null): array
    {
        $date = $onDate === null || trim($onDate) === ''
            ? Time::now()->toDateString()
            : trim($onDate);

        $batchesByMedicine = [];

        foreach ($this->stocks->batches() as $batch) {
            $batchesByMedicine[$batch['medicine_id']][] = $batch;
        }

        $report = [];

        foreach ($this->stocks->activeMedicines() as $medicine) {
            $available = [];
            $expired   = [];

            foreach ($batchesByMedicine[$medicine['id']] ?? [] as $batch) {
                $entry = [
                    'batch_no'   => $batch['batch_no'],
                    'expires_on' => $batch['expires_on'],
                    'quantity'   => $batch['quantity'],
                ];

                if ($batch['expires_on'] !== null && $batch['expires_on'] < $date) {
                    $expired[] = $entry;
                } else {
                    $available[] = $entry;
                }
            }

            $sum = static fn (array $batches): int => array_sum(array_column($batches, 'quantity'));

            $report[] = [
                'medicine_id'        => $medicine['id'],
                'code'               => $medicine['code'],
                'name'               => $medicine['name'],
                'unit'               => $medicine['unit'],
                'physical_quantity'  => $sum($available) + $sum($expired),
                'available_quantity' => $sum($available),
                'expired_quantity'   => $sum($expired),
                'available_batches'  => $available,
                'expired_batches'    => $expired,
            ];
        }

        return [
            'on_date'  => $date,
            'medicines' => $report,
        ];
    }
}
