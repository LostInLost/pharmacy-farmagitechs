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

    /**
     * Laporan per obat aktif. Angka dihitung database lewat satu query agregat
     * (`StockRepository::reportRows()`); di sini barisnya hanya dirapikan dan
     * totalnya dijumlahkan dalam satu lintasan — tanpa query tambahan dan tanpa
     * penyaringan ulang batch per obat.
     *
     * Batch dikirim sebagai satu daftar `batches` ber-flag `is_expired`;
     * klasifikasi tersedia/kedaluwarsa cukup disaring dari flag itu, sehingga
     * tidak ada dua daftar yang bisa saling bertentangan. Tiga angka ringkasan
     * (`physical_quantity`, `available_quantity`, `expired_quantity`) dihitung
     * dari daftar yang sama, bukan query ringkasan terpisah.
     *
     * Baris datang terurut `medicine_id, expires_on, batch_no`, jadi obat baru
     * dibuka cukup dengan membandingkan id baris sebelumnya.
     */
    public function report(?string $onDate = null): array
    {
        $date = $onDate === null || trim($onDate) === ''
            ? Time::now()->toDateString()
            : trim($onDate);

        $report = [];
        $index  = -1;

        foreach ($this->stocks->reportRows($date) as $row) {
            $medicineId = (int) $row['medicine_id'];

            if ($index < 0 || $report[$index]['medicine_id'] !== $medicineId) {
                $report[] = [
                    'medicine_id'        => $medicineId,
                    'code'               => $row['code'],
                    'name'               => $row['name'],
                    'unit'               => $row['unit'],
                    'physical_quantity'  => 0,
                    'available_quantity' => 0,
                    'expired_quantity'   => 0,
                    'batches'            => [],
                ];

                $index = array_key_last($report);
            }

            if ($row['batch_no'] === null) {
                continue;
            }

            $quantity = (int) $row['quantity'];
            $expired  = (int) $row['is_expired'] === 1;

            $report[$index]['batches'][] = [
                'batch_no'   => $row['batch_no'],
                'expires_on' => $row['expires_on'],
                'quantity'   => $quantity,
                'is_expired' => $expired,
            ];

            $report[$index]['physical_quantity'] += $quantity;
            $report[$index][$expired ? 'expired_quantity' : 'available_quantity'] += $quantity;
        }

        return [
            'on_date'   => $date,
            'medicines' => $report,
        ];
    }
}
