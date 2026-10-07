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

        foreach ($this->stocks->batches($date) as $batch) {
            $batchesByMedicine[$batch['medicine_id']][] = [
                'batch_no'   => $batch['batch_no'],
                'expires_on' => $batch['expires_on'],
                'quantity'   => $batch['quantity'],
                'is_expired' => $batch['is_expired'],
            ];
        }

        $summaries = $this->stocks->summaries($date);
        $report    = [];

        foreach ($this->stocks->activeMedicines() as $medicine) {
            $batches   = $batchesByMedicine[$medicine['id']] ?? [];
            $summary   = $summaries[$medicine['id']] ?? ['physical_quantity' => 0, 'available_quantity' => 0, 'expired_quantity' => 0];
            $available = array_values(array_filter($batches, static fn (array $batch): bool => $batch['is_expired'] === false));
            $expired   = array_values(array_filter($batches, static fn (array $batch): bool => $batch['is_expired'] === true));

            $report[] = [
                'medicine_id'        => $medicine['id'],
                'code'               => $medicine['code'],
                'name'               => $medicine['name'],
                'unit'               => $medicine['unit'],
                'physical_quantity'  => $summary['physical_quantity'],
                'available_quantity' => $summary['available_quantity'],
                'expired_quantity'   => $summary['expired_quantity'],
                'available_batches'  => $available,
                'expired_batches'    => $expired,
            ];
        }

        return [
            'on_date'   => $date,
            'medicines' => $report,
        ];
    }
}
